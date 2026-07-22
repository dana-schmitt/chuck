import httpx
import pytest

from app.client import JokeApiError
from tests.conftest import json_response, make_client


async def test_get_random_joke_sends_no_category_param_when_omitted() -> None:
    seen_requests: list[httpx.Request] = []

    joke_payload = {"id": 1, "joke": "A joke", "categories": [], "likeCount": 0}

    def handler(request: httpx.Request) -> httpx.Response:
        seen_requests.append(request)
        return httpx.Response(200, json=joke_payload)

    client = make_client(handler)
    result = await client.get_random_joke()

    assert result["joke"] == "A joke"
    assert seen_requests[0].url.path == "/api/jokes/random"
    assert "category" not in seen_requests[0].url.params


async def test_get_random_joke_forwards_the_category_param() -> None:
    seen_requests: list[httpx.Request] = []

    joke_payload = {"id": 1, "joke": "A dev joke", "categories": ["dev"], "likeCount": 0}

    def handler(request: httpx.Request) -> httpx.Response:
        seen_requests.append(request)
        return httpx.Response(200, json=joke_payload)

    client = make_client(handler)
    await client.get_random_joke(category="dev")

    assert seen_requests[0].url.params["category"] == "dev"


async def test_search_jokes_forwards_query_and_limit() -> None:
    seen_requests: list[httpx.Request] = []

    def handler(request: httpx.Request) -> httpx.Response:
        seen_requests.append(request)
        return httpx.Response(200, json={"semantic": True, "results": []})

    client = make_client(handler)
    await client.search_jokes("recursion", limit=3)

    assert seen_requests[0].url.path == "/api/jokes/search"
    assert seen_requests[0].url.params["q"] == "recursion"
    assert seen_requests[0].url.params["limit"] == "3"


async def test_get_joke_of_the_day_hits_the_right_path() -> None:
    payload = {"date": "2026-07-22", "id": 1, "joke": "x", "categories": [], "likeCount": 0}
    client = make_client(json_response(payload))

    result = await client.get_joke_of_the_day()

    assert result["date"] == "2026-07-22"


async def test_get_top_jokes_returns_the_parsed_list() -> None:
    client = make_client(json_response([{"id": 1, "joke": "a", "categories": [], "likeCount": 5}]))

    result = await client.get_top_jokes(limit=1)

    assert result == [{"id": 1, "joke": "a", "categories": [], "likeCount": 5}]


async def test_authorization_header_carries_the_bearer_token() -> None:
    seen_requests: list[httpx.Request] = []

    def handler(request: httpx.Request) -> httpx.Response:
        seen_requests.append(request)
        return httpx.Response(200, json={"id": 1, "joke": "x", "categories": [], "likeCount": 0})

    client = make_client(handler)
    await client.get_random_joke()

    assert seen_requests[0].headers["authorization"] == "Bearer test-token"


async def test_a_4xx_response_raises_joke_api_error() -> None:
    client = make_client(json_response({"error": "Invalid or missing API token."}, status_code=401))

    with pytest.raises(JokeApiError, match="401"):
        await client.get_random_joke()


async def test_a_5xx_response_raises_joke_api_error() -> None:
    client = make_client(json_response({"error": "boom"}, status_code=500))

    with pytest.raises(JokeApiError, match="500"):
        await client.get_top_jokes()


async def test_a_network_failure_raises_joke_api_error() -> None:
    def handler(request: httpx.Request) -> httpx.Response:
        raise httpx.ConnectError("connection refused", request=request)

    client = make_client(handler)

    with pytest.raises(JokeApiError, match="Could not reach"):
        await client.get_random_joke()
