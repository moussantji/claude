"""
Thin service layer around the `fzmovies_api` SDK.

The SDK exposes high level classes (`Search`, `Navigate`, `DownloadLinks`)
that work with pydantic models. This module reconstructs the minimal models
required to drive the SDK from plain HTTP parameters (only the `url` field is
actually used downstream by the SDK) and returns JSON-serialisable dicts.
"""

from __future__ import annotations

import logging
from typing import Any

from fzmovies_api import DownloadLinks, Navigate, Search
from fzmovies_api import filters as fz_filters
from fzmovies_api.models import FileMetadata, MovieInSearch

logger = logging.getLogger("movie_api.service")

SEARCH_BY = ("Name", "Director", "Starcast")
CATEGORIES = ("All", "Bollywood", "Hollywood", "DHollywood")

# filter_name -> (FilterClass, accepted kwargs)
FILTERS: dict[str, Any] = {
    "imdb_top250": fz_filters.IMDBTop250Filter,
    "most_downloaded": fz_filters.MostDownloadedFilter,
    "recently_released": fz_filters.RecentlyReleasedFilter,
    "recently_published": fz_filters.RecentlyPublishedFilter,
    "genre": fz_filters.MovieGenreFilter,
    "release_year": fz_filters.ReleaseYearFilter,
    "oscars": fz_filters.OscarsFilter,
    "tag": fz_filters.MovieTagFilter,
}

GENRES = fz_filters.MovieGenreFilter.available_genres
OSCAR_CATEGORIES = fz_filters.OscarsFilter.categories


def _dump(model) -> dict[str, Any]:
    """Serialise a pydantic model (v2) to a JSON-friendly dict."""
    return model.model_dump(mode="json")


def _results_to_dict(search: Search, limit: int) -> dict[str, Any]:
    """Return a single page (limit<=20) or aggregated results (limit>20)."""
    if limit and limit > 20:
        results = search.get_all_results(limit=limit)
    else:
        results = search.results
    return _dump(results)


def search_movies(
    query: str,
    searchby: str = "Name",
    category: str = "All",
    limit: int = 20,
) -> dict[str, Any]:
    """Search movies by name / director / starcast."""
    search = Search(query=query, searchby=searchby, category=category)
    return _results_to_dict(search, limit)


def _movie_from_url(url: str, title: str = "N/A", year: int = 0) -> MovieInSearch:
    """Build a minimal MovieInSearch. Only `url` is used by Navigate."""
    return MovieInSearch(
        url=url,
        title=title or "N/A",
        year=int(year or 0),
        distribution="N/A",
        about="N/A",
        cover_photo=url,
    )


def movie_files(url: str, title: str = "N/A", year: int = 0) -> dict[str, Any]:
    """Fetch the downloadable files, trailer and recommendations for a movie."""
    movie = _movie_from_url(url, title, year)
    return _dump(Navigate(movie).results)


def _file_from_url(url: str, title: str = "N/A", size: str = "N/A") -> FileMetadata:
    """Build a minimal FileMetadata. Only `url` is used by DownloadLinks."""
    return FileMetadata(
        url=url,
        title=title or "N/A",
        size=size or "N/A",
        hits=0,
        mediainfo=url,
    )


def download_links(url: str, title: str = "N/A", size: str = "N/A") -> dict[str, Any]:
    """Resolve the actual download links for a specific movie file."""
    movie_file = _file_from_url(url, title, size)
    return _dump(DownloadLinks(movie_file).results)


def _build_filter(name: str, category: str, genre: str, year: int | None, oscar: str, tag: str):
    """Instantiate the requested filter with the relevant arguments."""
    if name not in FILTERS:
        raise ValueError(
            f"Unknown filter '{name}'. Available: {sorted(FILTERS.keys())}"
        )
    cls = FILTERS[name]
    if name == "imdb_top250":
        return cls()
    if name == "genre":
        return cls(name=genre, category=category)
    if name == "release_year":
        return cls(year=year, category=category)
    if name == "oscars":
        return cls(category=oscar)
    if name == "tag":
        return cls(tag=tag)
    # most_downloaded / recently_released / recently_published
    return cls(category=category)


def browse(
    name: str,
    category: str = "Hollywood",
    genre: str = "Action",
    year: int | None = None,
    oscar: str = "Best Picture",
    tag: str = "",
    limit: int = 20,
) -> dict[str, Any]:
    """Browse movie listings using one of the supported fzmovies filters."""
    flt = _build_filter(name, category, genre, year, oscar, tag)
    search = Search(query=flt)
    return _results_to_dict(search, limit)
