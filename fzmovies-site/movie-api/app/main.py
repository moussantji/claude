"""
FastAPI application exposing the fzmovies-api SDK over HTTP/JSON.

This service is the "backend" consumed by the Laravel frontend. It never
downloads files itself - it only resolves metadata and download links, which
are returned to the client.
"""

from __future__ import annotations

import logging

from fastapi import FastAPI, HTTPException, Query
from fastapi.middleware.cors import CORSMiddleware

from . import service

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("movie_api")

app = FastAPI(
    title="fzmovies API",
    version="1.0.0",
    description="Unofficial REST wrapper around the fzmovies-api Python SDK.",
)

# The Laravel frontend calls this server-side, but CORS is opened so the API
# can also be consumed directly from a browser during development.
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=False,
    allow_methods=["GET"],
    allow_headers=["*"],
)


def _guard(fn, *args, **kwargs):
    """Run an SDK call and translate failures into clean HTTP errors."""
    try:
        return fn(*args, **kwargs)
    except (ValueError, AssertionError) as exc:
        raise HTTPException(status_code=400, detail=str(exc)) from exc
    except Exception as exc:  # scraping / network failures
        logger.exception("fzmovies SDK call failed")
        raise HTTPException(
            status_code=502,
            detail=f"Upstream fzmovies request failed: {exc}",
        ) from exc


@app.get("/health", tags=["meta"])
def health() -> dict[str, str]:
    """Liveness probe."""
    return {"status": "ok"}


@app.get("/api/meta", tags=["meta"])
def meta() -> dict[str, object]:
    """Expose the enumerations supported by the SDK (for building UIs)."""
    return {
        "searchby": list(service.SEARCH_BY),
        "categories": list(service.CATEGORIES),
        "filters": sorted(service.FILTERS.keys()),
        "genres": list(service.GENRES),
        "oscar_categories": list(service.OSCAR_CATEGORIES),
    }


@app.get("/api/search", tags=["movies"])
def search(
    q: str = Query(..., min_length=1, description="Search query"),
    searchby: str = Query("Name", pattern="^(Name|Director|Starcast)$"),
    category: str = Query("All", pattern="^(All|Bollywood|Hollywood|DHollywood)$"),
    limit: int = Query(20, ge=1, le=200),
):
    """Search movies. Returns a `SearchResults` object."""
    return _guard(service.search_movies, q, searchby, category, limit)


@app.get("/api/browse", tags=["movies"])
def browse(
    filter: str = Query(..., description="One of the supported filters"),
    category: str = Query("Hollywood"),
    genre: str = Query("Action"),
    year: int | None = Query(None),
    oscar: str = Query("Best Picture"),
    tag: str = Query(""),
    limit: int = Query(20, ge=1, le=200),
):
    """Browse curated listings (genre, recently released, IMDB top 250, ...)."""
    return _guard(
        service.browse, filter, category, genre, year, oscar, tag, limit
    )


@app.get("/api/movie-files", tags=["movies"])
def movie_files(
    url: str = Query(..., description="Movie page URL from search results"),
    title: str = Query("N/A"),
    year: int = Query(0),
):
    """List the downloadable files for a movie. Returns a `MovieFiles` object."""
    return _guard(service.movie_files, url, title, year)


@app.get("/api/download-links", tags=["movies"])
def download_links(
    url: str = Query(..., description="File URL from the movie-files response"),
    title: str = Query("N/A"),
    size: str = Query("N/A"),
):
    """Resolve the direct download links for a movie file. Returns `DownloadMovie`."""
    return _guard(service.download_links, url, title, size)
