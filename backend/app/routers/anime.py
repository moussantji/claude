"""Anime-sama REST endpoints."""

from __future__ import annotations

from fastapi import APIRouter, Depends, HTTPException, Query, status
from httpx import HTTPError

from anime_sama_api.catalogue import Catalogue
from anime_sama_api.season import Season

from ..client import AnimeSamaClient, get_client
from ..config import get_settings
from ..dependencies import verify_api_key
from ..schemas import (
    CatalogueDetail,
    CatalogueSummary,
    EpisodeModel,
    PlanningDay,
    SiteInfo,
    TrailerInfo,
)
from ..services import (
    catalogue_to_detail,
    catalogue_to_summary,
    episode_to_model,
    season_to_summary,
)
from ..tmdb import trailer_key_for

router = APIRouter(prefix="/api", dependencies=[Depends(verify_api_key)])


def _prefer_languages(prefer: str | None) -> list[str]:
    if prefer:
        return [lang.strip() for lang in prefer.split(",") if lang.strip()]
    return get_settings().prefer_language_list


@router.get("/site", response_model=SiteInfo, tags=["site"])
async def get_site(client: AnimeSamaClient = Depends(get_client)) -> SiteInfo:
    return SiteInfo(site_url=client.site_url)


@router.post("/site/resolve", response_model=SiteInfo, tags=["site"])
async def resolve_site(client: AnimeSamaClient = Depends(get_client)) -> SiteInfo:
    resolved = await client.resolve_site_url()
    if not resolved:
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail="Could not resolve the anime-sama domain from the provider.",
        )
    return SiteInfo(site_url=resolved)


@router.get("/search", response_model=list[CatalogueSummary], tags=["catalogue"])
async def search(
    q: str = Query(..., min_length=1, description="Search query"),
    client: AnimeSamaClient = Depends(get_client),
) -> list[CatalogueSummary]:
    try:
        catalogues = await client.api.search(q)
    except HTTPError as exc:
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail=f"Upstream error while searching: {exc}",
        ) from exc
    return [catalogue_to_summary(c) for c in catalogues]


@router.get("/new-episodes", response_model=list[dict], tags=["catalogue"])
async def new_episodes(
    client: AnimeSamaClient = Depends(get_client),
) -> list[dict]:
    """Latest episode releases shown on the anime-sama homepage."""
    try:
        releases = await client.api.new_episodes()
    except HTTPError as exc:
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail=f"Upstream error while fetching new episodes: {exc}",
        ) from exc

    return [
        {
            "url": release.page_url,
            "name": release.serie_name,
            "categories": list(release.categories),
            "languages": [release.language],
            "image_url": release.image_url,
            "alternative_names": [],
            "genres": [],
            "descriptive": release.descriptive,
            "fancy_name": release.fancy_name,
        }
        for release in releases
    ]


@router.get("/planning", response_model=list[PlanningDay], tags=["catalogue"])
async def planning(
    client: AnimeSamaClient = Depends(get_client),
) -> list[PlanningDay]:
    """The anime-sama release planning, grouped into scheduled slots.

    Note: ``planning()`` is currently a stub in the upstream anime-sama_api
    library (it raises ``NotImplementedError``); we surface that as a clean
    501 rather than a 500 until it lands upstream.
    """
    try:
        groups = await client.api.planning()
    except NotImplementedError as exc:
        raise HTTPException(
            status_code=status.HTTP_501_NOT_IMPLEMENTED,
            detail="Planning is not implemented in the upstream anime-sama_api library yet.",
        ) from exc
    except HTTPError as exc:
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail=f"Upstream error while fetching planning: {exc}",
        ) from exc

    return [
        PlanningDay(seasons=[season_to_summary(s) for s in group])
        for group in groups
    ]


@router.get("/catalogue", response_model=CatalogueDetail, tags=["catalogue"])
async def catalogue_detail(
    url: str = Query(..., description="Full catalogue URL"),
    client: AnimeSamaClient = Depends(get_client),
) -> CatalogueDetail:
    catalogue = Catalogue(url=url, client=client.http)
    try:
        return await catalogue_to_detail(catalogue)
    except HTTPError as exc:
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail=f"Upstream error while loading catalogue: {exc}",
        ) from exc


@router.get("/episodes", response_model=list[EpisodeModel], tags=["episodes"])
async def season_episodes(
    url: str = Query(..., description="Full season URL"),
    prefer: str | None = Query(
        default=None,
        description="Comma separated language preference, e.g. 'VF,VOSTFR'",
    ),
    client: AnimeSamaClient = Depends(get_client),
) -> list[EpisodeModel]:
    season = Season(url=url, client=client.http)
    prefer_languages = _prefer_languages(prefer)
    try:
        episodes = await season.episodes()
    except HTTPError as exc:
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail=f"Upstream error while loading episodes: {exc}",
        ) from exc
    return [episode_to_model(episode, prefer_languages) for episode in episodes]


@router.get("/trailer", response_model=TrailerInfo, tags=["trailer"])
async def trailer(
    title: str = Query(..., min_length=1, max_length=200, description="Anime title"),
    client: AnimeSamaClient = Depends(get_client),
) -> TrailerInfo:
    """Best-effort YouTube trailer key for a title (via TMDB, optional).

    Returns ``{"youtube_key": null}`` when trailers are disabled (no TMDB key)
    or when nothing relevant was found. anime-sama itself has no trailer data.
    """
    key = await trailer_key_for(title, client.http)
    return TrailerInfo(youtube_key=key)
