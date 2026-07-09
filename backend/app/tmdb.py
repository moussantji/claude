"""Optional TMDB lookup for anime trailers.

anime-sama itself exposes no trailer information, so the hero background
trailer is sourced from TMDB, which returns official YouTube trailer keys.
When no API key is configured every lookup simply returns ``None`` and the
frontend falls back to the animated poster background.
"""

from __future__ import annotations

from httpx import AsyncClient, HTTPError

from .config import get_settings

TMDB_BASE = "https://api.themoviedb.org/3"

# Prefer official trailers, then any trailer, then teasers.
_TYPE_RANK = {"Trailer": 0, "Teaser": 1, "Clip": 2, "Featurette": 3}


async def trailer_key_for(title: str, client: AsyncClient) -> str | None:
    """Return the best YouTube trailer key for ``title`` or ``None``."""
    settings = get_settings()
    api_key = settings.tmdb_api_key.strip()
    if not api_key:
        return None

    params = {
        "api_key": api_key,
        "query": title,
        "language": settings.tmdb_language,
        "include_adult": "false",
    }

    try:
        # Multi-search first (covers both TV series and movies).
        search = await client.get(
            f"{TMDB_BASE}/search/multi", params=params, timeout=15.0
        )
        search.raise_for_status()
        results = [
            r
            for r in search.json().get("results", [])
            if r.get("media_type") in {"tv", "movie"}
        ]
        if not results:
            return None

        media_type = results[0]["media_type"]
        media_id = results[0]["id"]

        # Fetch videos, trying the requested language then falling back to en.
        for language in (settings.tmdb_language, "en-US"):
            videos = await client.get(
                f"{TMDB_BASE}/{media_type}/{media_id}/videos",
                params={"api_key": api_key, "language": language},
                timeout=15.0,
            )
            videos.raise_for_status()
            key = _pick_youtube_key(videos.json().get("results", []))
            if key:
                return key
    except HTTPError:
        return None

    return None


def _pick_youtube_key(videos: list[dict]) -> str | None:
    youtube = [v for v in videos if v.get("site") == "YouTube" and v.get("key")]
    if not youtube:
        return None

    youtube.sort(
        key=lambda v: (
            _TYPE_RANK.get(v.get("type", ""), 9),
            0 if v.get("official") else 1,
        )
    )
    return youtube[0]["key"]
