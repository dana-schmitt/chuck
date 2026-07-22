from typing import Any

from pydantic import BaseModel, Field


class EmbeddingsRequest(BaseModel):
    texts: list[str] = Field(min_length=1)


class EmbeddingsResponse(BaseModel):
    vectors: list[list[float]]
    model: str


class CompleteRequest(BaseModel):
    system: str
    user: str
    response_schema: dict[str, Any] | None = None
    max_tokens: int = Field(default=500, gt=0, le=4000)


class CompleteResponse(BaseModel):
    # Plain text when no response_schema was given, a schema-validated object otherwise.
    text: str | None = None
    data: dict[str, Any] | None = None
    model: str


class HealthResponse(BaseModel):
    status: str = "ok"
