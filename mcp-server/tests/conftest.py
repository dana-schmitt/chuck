from collections.abc import Callable

import httpx
import pytest

from app.client import JokeApiClient
from app.config import Settings


def make_client(handler: Callable[[httpx.Request], httpx.Response]) -> JokeApiClient:
    """Builds a JokeApiClient backed by a MockTransport instead of a real connection, so tests
    can assert on the request that was made and control the response without a live API."""
    settings = Settings(api_base_url="http://testserver", api_token="test-token")
    return JokeApiClient(settings, transport=httpx.MockTransport(handler))


def json_response(
    payload: object, status_code: int = 200
) -> Callable[[httpx.Request], httpx.Response]:
    def handler(request: httpx.Request) -> httpx.Response:
        return httpx.Response(status_code, json=payload)

    return handler


@pytest.fixture
def settings() -> Settings:
    return Settings(api_base_url="http://testserver", api_token="test-token")
