from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", env_prefix="", extra="ignore")

    api_base_url: str = "http://127.0.0.1:8000"

    # A Chuckify API token created via `php bin/console app:api-token:create` - sent as
    # `Authorization: Bearer <token>` on every request.
    api_token: str = ""

    request_timeout_seconds: float = 10.0


@lru_cache
def get_settings() -> Settings:
    return Settings()
