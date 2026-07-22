import hashlib
import math
from typing import Any

from app.providers.base import CompletionBackend, EmbeddingBackend


class FakeEmbeddingBackend(EmbeddingBackend):
    """Deterministic, dependency-free stand-in for local dev and CI.

    Same text always produces the same (L2-normalized) vector, and different
    texts produce different vectors, so cosine-similarity-based code exercised
    against this backend behaves sensibly without ever calling OpenAI.
    """

    def __init__(self, dimensions: int) -> None:
        self._dimensions = dimensions

    def embed(self, texts: list[str]) -> list[list[float]]:
        return [self._embed_one(text) for text in texts]

    def _embed_one(self, text: str) -> list[float]:
        digest = hashlib.sha256(text.encode("utf-8")).digest()
        repeats = (self._dimensions // len(digest)) + 1
        raw = (digest * repeats)[: self._dimensions]
        vector = [(byte / 255.0) * 2 - 1 for byte in raw]

        norm = math.sqrt(sum(component * component for component in vector)) or 1.0
        return [component / norm for component in vector]


class FakeCompletionBackend(CompletionBackend):
    """Deterministic stand-in: echoes the input for plain-text completions, and
    fills in a minimal value per field for schema-constrained ones so callers can
    exercise their structured-output handling without a real model.
    """

    def complete(
        self,
        system: str,
        user: str,
        response_schema: dict[str, Any] | None,
        max_tokens: int,
    ) -> str | dict[str, Any]:
        if response_schema is None:
            return f"[fake completion] {user[:200]}"

        return self._fill_schema(response_schema)

    def _fill_schema(self, schema: dict[str, Any]) -> Any:
        schema_type = schema.get("type", "object")

        if schema_type == "object":
            properties = schema.get("properties", {})
            return {name: self._fill_schema(subschema) for name, subschema in properties.items()}
        if schema_type == "array":
            return []
        if schema_type == "string":
            return schema.get("enum", ["fake"])[0]
        if schema_type in ("number", "integer"):
            return 0
        if schema_type == "boolean":
            return False

        return None
