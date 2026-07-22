from typing import Any

import httpx

from app.config import Settings


class JokeApiError(Exception):
    """Raised when the Chuckify JSON API returns an error, or can't be reached at all."""


class JokeApiClient:
    """Thin wrapper around Chuckify's read-only JSON API (/api/jokes/...), authenticated with
    the bearer token created via `php bin/console app:api-token:create` on the app side."""

    def __init__(
        self, settings: Settings, transport: httpx.AsyncBaseTransport | None = None
    ) -> None:
        self._client = httpx.AsyncClient(
            base_url=settings.api_base_url,
            headers={"Authorization": f"Bearer {settings.api_token}"},
            timeout=settings.request_timeout_seconds,
            transport=transport,
        )

    async def aclose(self) -> None:
        await self._client.aclose()

    async def get_random_joke(self, category: str | None = None) -> dict[str, Any]:
        params = {"category": category} if category else None
        return await self._get("/api/jokes/random", params=params)

    async def search_jokes(self, query: str, limit: int = 5) -> dict[str, Any]:
        return await self._get("/api/jokes/search", params={"q": query, "limit": limit})

    async def get_joke_of_the_day(self) -> dict[str, Any]:
        return await self._get("/api/jokes/of-the-day")

    async def get_top_jokes(self, limit: int = 10) -> list[dict[str, Any]]:
        return await self._get("/api/jokes/top", params={"limit": limit})

    async def _get(self, path: str, params: dict[str, Any] | None = None) -> Any:
        try:
            response = await self._client.get(path, params=params)
        except httpx.HTTPError as exc:
            raise JokeApiError(f"Could not reach the Chuckify API: {exc}") from exc

        if response.status_code >= 400:
            raise JokeApiError(
                f"Chuckify API returned {response.status_code} for GET {path}: {response.text}"
            )

        return response.json()
