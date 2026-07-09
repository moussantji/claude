"""FastAPI application entrypoint."""

from __future__ import annotations

import logging
from contextlib import asynccontextmanager

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from . import __version__
from .client import get_client
from .config import get_settings
from .schemas import HealthResponse
from .routers import anime

logger = logging.getLogger("anime_sama_backend")


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Startup: the anime-sama domain rotates periodically and the configured
    # default may be stale, so best-effort auto-resolve the live domain. This
    # is non-fatal: if it fails we keep the configured site URL.
    try:
        resolved = await get_client().resolve_site_url()
        if resolved:
            logger.info("Resolved anime-sama domain: %s", resolved)
    except Exception as exc:  # noqa: BLE001 - never block startup on this
        logger.warning("Could not auto-resolve anime-sama domain: %s", exc)
    yield
    # Shutdown: close the shared HTTP client.
    await get_client().aclose()


def create_app() -> FastAPI:
    settings = get_settings()

    app = FastAPI(
        title="Anime-Sama Backend API",
        description=(
            "REST wrapper around the anime-sama_api library "
            "(https://github.com/Sky-NiniKo/anime-sama_api), consumed by the "
            "Laravel + Sanctum frontend."
        ),
        version=__version__,
        lifespan=lifespan,
    )

    app.add_middleware(
        CORSMiddleware,
        allow_origins=settings.cors_origin_list,
        allow_credentials=True,
        allow_methods=["*"],
        allow_headers=["*"],
    )

    app.include_router(anime.router)

    @app.get("/health", response_model=HealthResponse, tags=["health"])
    async def health() -> HealthResponse:
        return HealthResponse(
            status="ok",
            version=__version__,
            site_url=get_client().site_url,
        )

    return app


app = create_app()
