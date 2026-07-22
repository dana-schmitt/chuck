from pydantic import BaseModel, ConfigDict, Field


class Joke(BaseModel):
    model_config = ConfigDict(populate_by_name=True)

    id: int
    joke: str
    categories: list[str] = Field(default_factory=list)
    like_count: int = Field(alias="likeCount")
    submitted_by: str | None = Field(default=None, alias="submittedBy")


class JokeOfTheDay(Joke):
    date: str


class SearchResult(BaseModel):
    model_config = ConfigDict(populate_by_name=True)

    semantic: bool
    results: list[Joke]
