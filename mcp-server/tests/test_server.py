import pytest

from app import server
from app.models import Joke, JokeOfTheDay, SearchResult
from tests.conftest import json_response, make_client


@pytest.fixture(autouse=True)
def _reset_client_singleton(monkeypatch: pytest.MonkeyPatch) -> None:
    # server._client is a lazily-created module-level singleton; make sure each test starts
    # from a clean slate and doesn't leak a client (or its mock transport) into the next one.
    monkeypatch.setattr(server, "_client", None)


async def test_get_random_joke_returns_a_joke_model(monkeypatch: pytest.MonkeyPatch) -> None:
    payload = {"id": 1, "joke": "A joke", "categories": ["dev"], "likeCount": 3}
    client = make_client(json_response(payload))
    monkeypatch.setattr(server, "_get_client", lambda: client)

    result = await server.get_random_joke()

    assert result == Joke(id=1, joke="A joke", categories=["dev"], likeCount=3)


async def test_search_jokes_returns_a_search_result_model(monkeypatch: pytest.MonkeyPatch) -> None:
    payload = {
        "semantic": True,
        "results": [{"id": 1, "joke": "A joke", "categories": [], "likeCount": 0}],
    }
    client = make_client(json_response(payload))
    monkeypatch.setattr(server, "_get_client", lambda: client)

    result = await server.search_jokes("recursion")

    assert isinstance(result, SearchResult)
    assert result.semantic is True
    assert result.results == [Joke(id=1, joke="A joke", categories=[], likeCount=0)]


async def test_get_joke_of_the_day_returns_a_joke_of_the_day_model(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    payload = {
        "date": "2026-07-22",
        "id": 1,
        "joke": "Today's joke",
        "categories": [],
        "likeCount": 0,
    }
    client = make_client(json_response(payload))
    monkeypatch.setattr(server, "_get_client", lambda: client)

    result = await server.get_joke_of_the_day()

    assert result == JokeOfTheDay(**payload)


async def test_get_top_jokes_returns_a_list_of_joke_models(monkeypatch: pytest.MonkeyPatch) -> None:
    payload = [
        {"id": 1, "joke": "Most liked", "categories": [], "likeCount": 10},
        {"id": 2, "joke": "Second most liked", "categories": [], "likeCount": 5},
    ]
    client = make_client(json_response(payload))
    monkeypatch.setattr(server, "_get_client", lambda: client)

    result = await server.get_top_jokes(limit=2)

    assert result == [
        Joke(id=1, joke="Most liked", categories=[], likeCount=10),
        Joke(id=2, joke="Second most liked", categories=[], likeCount=5),
    ]


async def test_get_client_reuses_the_same_instance_across_calls() -> None:
    first = server._get_client()
    second = server._get_client()

    assert first is second
