"""Shared anime-sama client management.

Keeps a single httpx.AsyncClient alive for the whole application and an
`AnimeSama` instance bound to the currently configured site URL. The site
URL can be swapped at runtime (e.g. after auto-resolving the domain).
"""

from __future__ import annotations

import asyncio

from httpx import AsyncClient
from anime_sama_api.top_level import AnimeSama, find_site_url

from .config import get_settings


class AnimeSamaClient:
    def __init__(self) -> None:
        settings = get_settings()
        self._lock = asyncio.Lock()
        self._http = AsyncClient(
            timeout=settings.request_timeout,
            follow_redirects=True,
            headers={"User-Agent": "anime-sama-backend/1.0 (+https://github.com)"},
        )
        self._site_url = settings.site_url
        self._api = AnimeSama(self._site_url, client=self._http)

    @property
    def site_url(self) -> str:
        return self._site_url

    @property
    def api(self) -> AnimeSama:
        return self._api

    @property
    def http(self) -> AsyncClient:
        return self._http

    async def set_site_url(self, site_url: str) -> str:
        if not site_url.endswith("/"):
            site_url += "/"
        async with self._lock:
            self._site_url = site_url
            self._api = AnimeSama(site_url, client=self._http)
        return self._site_url

    async def resolve_site_url(self) -> str | None:
        """Auto-detect the current anime-sama domain via the provider."""
        settings = get_settings()
        resolved = await find_site_url(
            client=self._http, provider_url=settings.provider_url
        )
        if resolved:
            await self.set_site_url(resolved)
        return resolved

    async def aclose(self) -> None:
        await self._http.aclose()


# Module level singleton, created on first import.
client = AnimeSamaClient()


def get_client() -> AnimeSamaClient:
    return client
