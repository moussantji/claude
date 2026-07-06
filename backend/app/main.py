"""FastAPI application entrypoint."""

from __future__ import annotations

from contextlib import asynccontextmanager

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from . import __version__
from .client import get_client
from .config import get_settings
from .routers import anime
from .schemas import HealthResponse


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Startup: nothing eager to do; the client is created lazily on import.
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
