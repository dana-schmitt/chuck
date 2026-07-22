from mcp.server.fastmcp import FastMCP

from app.client import JokeApiClient
from app.config import get_settings
from app.models import Joke, JokeOfTheDay, SearchResult

mcp = FastMCP(
    "chuckify",
    instructions="Tools for browsing and searching Chuck Norris jokes from the Chuckify app.",
)

# A single client, lazily created on first use and reused for the life of the process - this
# server is a short-lived stdio subprocess launched per MCP session, so there's no long-running
# lifecycle to manage beyond that.
_client: JokeApiClient | None = None


def _get_client() -> JokeApiClient:
    global _client
    if _client is None:
        _client = JokeApiClient(get_settings())
    return _client


@mcp.tool()
async def get_random_joke(category: str | None = None) -> Joke:
    """Get a random Chuck Norris joke from Chuckify, optionally filtered by category."""
    data = await _get_client().get_random_joke(category)
    return Joke.model_validate(data)


@mcp.tool()
async def search_jokes(query: str, limit: int = 5) -> SearchResult:
    """Search Chuckify for Chuck Norris jokes matching a query (semantic search when available,
    falling back to keyword search otherwise - see the "semantic" field on the result)."""
    data = await _get_client().search_jokes(query, limit)
    return SearchResult.model_validate(data)


@mcp.tool()
async def get_joke_of_the_day() -> JokeOfTheDay:
    """Get today's featured Chuck Norris joke of the day from Chuckify."""
    data = await _get_client().get_joke_of_the_day()
    return JokeOfTheDay.model_validate(data)


@mcp.tool()
async def get_top_jokes(limit: int = 10) -> list[Joke]:
    """Get the most-liked Chuck Norris jokes on Chuckify, ranked by like count."""
    data = await _get_client().get_top_jokes(limit)
    return [Joke.model_validate(entry) for entry in data]


def main() -> None:
    mcp.run()


if __name__ == "__main__":
    main()
