from abc import ABC, abstractmethod
from typing import Any


class EmbeddingBackend(ABC):
    @abstractmethod
    def embed(self, texts: list[str]) -> list[list[float]]:
        """Returns one vector per input text, in the same order."""


class CompletionBackend(ABC):
    @abstractmethod
    def complete(
        self,
        system: str,
        user: str,
        response_schema: dict[str, Any] | None,
        max_tokens: int,
    ) -> str | dict[str, Any]:
        """Returns plain text when response_schema is None, a dict matching it otherwise."""
