"""HTML parsing helpers for the current anime-sama site design.

The `anime_sama_api` library was written for an older layout; on the current
site the synopsis/image/genres live in different places, so we re-extract them
from the catalogue page HTML.
"""

from __future__ import annotations

import html as _html
import re

_META_DESCRIPTION = re.compile(
    r'<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\']',
    re.I | re.S,
)
_META_DESCRIPTION_ALT = re.compile(
    r'<meta[^>]+content=["\'](.*?)["\'][^>]+name=["\']description["\']',
    re.I | re.S,
)
_SYNOPSIS_HEADING = re.compile(r">\s*Synopsis\s*<[\W\w]{0,120}?<p[^>]*>(.*?)</p>", re.I | re.S)
_OG_IMAGE = re.compile(
    r'<meta[^>]+property=["\']og:image["\'][^>]+content=["\'](.*?)["\']',
    re.I | re.S,
)
_GENRE_PILL = re.compile(r'class=["\'][^"\']*genre-pill[^"\']*["\'][^>]*>(.*?)</', re.I | re.S)
_TAG = re.compile(r"<[^>]+>")


def _clean(value: str) -> str:
    value = _TAG.sub("", value)
    value = _html.unescape(value)
    return re.sub(r"\s+", " ", value).strip()


def extract_synopsis(page: str) -> str:
    """Best-effort synopsis: the meta description, then a Synopsis heading."""
    for pattern in (_META_DESCRIPTION, _META_DESCRIPTION_ALT):
        match = pattern.search(page)
        if match:
            text = _clean(match.group(1))
            if len(text) >= 15:
                return text

    match = _SYNOPSIS_HEADING.search(page)
    if match:
        return _clean(match.group(1))

    return ""


def extract_image(page: str) -> str:
    match = _OG_IMAGE.search(page)
    return _clean(match.group(1)) if match else ""


def extract_genres(page: str) -> list[str]:
    genres: list[str] = []
    for raw in _GENRE_PILL.findall(page):
        genre = _clean(raw)
        if genre and genre not in genres:
            genres.append(genre)
    return genres
