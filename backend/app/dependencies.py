"""Shared FastAPI dependencies."""

from __future__ import annotations

from fastapi import Header, HTTPException, status

from .config import get_settings


async def verify_api_key(x_api_key: str | None = Header(default=None)) -> None:
    """Require the shared API key when one is configured.

    When ANIMESAMA_API_KEY is empty the backend is open (development mode).
    """
    settings = get_settings()
    if not settings.api_key:
        return
    if x_api_key != settings.api_key:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or missing API key.",
        )
