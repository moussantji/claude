"""Application configuration loaded from environment variables."""

from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_prefix="ANIMESAMA_",
        extra="ignore",
    )

    # Base URL of the anime-sama website used for scraping.
    # This changes from time to time; it can be auto-detected at runtime
    # via the /api/site/resolve endpoint.
    site_url: str = "https://anime-sama.fr/"

    # Provider used to auto-resolve the current anime-sama domain.
    provider_url: str = "https://anime-sama.pw/"

    # Comma separated list of origins allowed to call the API.
    # Use "*" to allow everything (development only).
    cors_origins: str = "*"

    # Shared secret required in the "X-API-Key" header. When empty the
    # backend is open (useful for local development). The Laravel gateway
    # should always set this in production.
    api_key: str = ""

    # Default language preference order used when resolving the "best" player.
    prefer_languages: str = "VF,VOSTFR"

    # HTTP timeout (seconds) for outgoing scraping requests.
    request_timeout: float = 20.0

    # --- TMDB (optional) --------------------------------------------------
    # anime-sama has no trailer data, so hero background trailers are looked
    # up on TMDB (which returns official YouTube trailer keys). Leave the key
    # empty to disable trailers entirely (the endpoint then returns null).
    tmdb_api_key: str = ""
    tmdb_language: str = "fr-FR"

    @property
    def cors_origin_list(self) -> list[str]:
        value = self.cors_origins.strip()
        if value == "*" or not value:
            return ["*"]
        return [origin.strip() for origin in value.split(",") if origin.strip()]

    @property
    def prefer_language_list(self) -> list[str]:
        return [lang.strip() for lang in self.prefer_languages.split(",") if lang.strip()]


@lru_cache
def get_settings() -> Settings:
    return Settings()
