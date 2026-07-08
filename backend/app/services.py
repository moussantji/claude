"""Conversion helpers: anime-sama_api objects -> API response models."""

from __future__ import annotations

import asyncio

from anime_sama_api.catalogue import Catalogue
from anime_sama_api.episode import Episode
from anime_sama_api.season import Season

from .schemas import (
    CatalogueDetail,
    CatalogueSummary,
    EpisodeModel,
    SeasonSummary,
)


def catalogue_to_summary(catalogue: Catalogue) -> CatalogueSummary:
    return CatalogueSummary(
        url=catalogue.url,
        name=catalogue.name,
        alternative_names=list(catalogue.alternative_names),
        genres=list(catalogue.genres),
        categories=sorted(catalogue.categories),
        languages=sorted(catalogue.languages),
        image_url=catalogue.image_url,
    )


def season_to_summary(season: Season) -> SeasonSummary:
    return SeasonSummary(
        url=season.url,
        name=season.name,
        serie_name=season.serie_name,
    )


async def catalogue_to_detail(catalogue: Catalogue) -> CatalogueDetail:
    # Trigger a single page fetch, then read every derived field concurrently.
    synopsis, advancement, correspondence, is_mature, seasons = await asyncio.gather(
        catalogue.synopsis(),
        catalogue.advancement(),
        catalogue.correspondence(),
        catalogue.is_mature(),
        catalogue.seasons(),
    )

    summary = catalogue_to_summary(catalogue)
    return CatalogueDetail(
        **summary.model_dump(),
        synopsis=synopsis,
        advancement=advancement,
        correspondence=correspondence,
        is_mature=is_mature,
        seasons=[season_to_summary(season) for season in seasons],
    )


def episode_to_model(
    episode: Episode, prefer_languages: list[str]
) -> EpisodeModel:
    availables = episode.languages.availables
    languages: dict[str, list[list[str]]] = {
        lang: [list(players) for players in groups]
        for lang, groups in availables.items()
    }

    return EpisodeModel(
        index=episode.index,
        name=episode.name,
        season_name=episode.season_name,
        serie_name=episode.serie_name,
        long_name=episode.long_name,
        short_name=episode.short_name,
        languages=languages,
        available_languages=list(languages.keys()),
        best_player=episode.best(prefer_languages),  # type: ignore[arg-type]
    )
