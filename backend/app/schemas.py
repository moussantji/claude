"""Pydantic response models exposed by the API."""

from pydantic import BaseModel, Field


class CatalogueSummary(BaseModel):
    url: str
    name: str
    alternative_names: list[str] = Field(default_factory=list)
    genres: list[str] = Field(default_factory=list)
    categories: list[str] = Field(default_factory=list)
    languages: list[str] = Field(default_factory=list)
    image_url: str = ""


class SeasonSummary(BaseModel):
    url: str
    name: str
    serie_name: str


class CatalogueDetail(CatalogueSummary):
    synopsis: str = ""
    advancement: str = ""
    correspondence: str = ""
    is_mature: bool = False
    seasons: list[SeasonSummary] = Field(default_factory=list)


class EpisodeModel(BaseModel):
    index: int
    name: str
    season_name: str
    serie_name: str
    long_name: str
    short_name: str
    # Mapping of language -> list of player groups. Each player group is a
    # list of mirror URLs pointing to the same episode.
    languages: dict[str, list[list[str]]] = Field(default_factory=dict)
    available_languages: list[str] = Field(default_factory=list)
    # Best single player URL resolved using the preferred language order.
    best_player: str | None = None


class SiteInfo(BaseModel):
    site_url: str


class HealthResponse(BaseModel):
    status: str = "ok"
    version: str
    site_url: str
