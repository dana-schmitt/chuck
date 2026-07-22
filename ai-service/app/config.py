from functools import lru_cache
from typing import Literal

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_file=".env", env_prefix="", extra="ignore")

    # "openai" calls the real OpenAI API; "fake" returns deterministic canned
    # responses so the service (and everything calling it) works without a key,
    # for local development and CI.
    ai_provider: Literal["openai", "fake"] = "fake"

    openai_api_key: str = ""
    embedding_model: str = "text-embedding-3-small"
    completion_model: str = "gpt-4o-mini"

    # Checked against the `X-Internal-Secret` header on every request except /health.
    shared_secret: str = ""

    request_timeout_seconds: float = 20.0
    max_retries: int = 2

    # Real embedding vectors are 1536-dim; the fake provider uses a much smaller,
    # fixed dimension since tests only need it to be consistent, not realistic.
    fake_embedding_dimensions: int = 8

    embeddings_batch_size: int = 100


@lru_cache
def get_settings() -> Settings:
    return Settings()
