from app.config import Settings
from app.providers.base import CompletionBackend, EmbeddingBackend
from app.providers.fake_backend import FakeCompletionBackend, FakeEmbeddingBackend
from app.providers.openai_backend import OpenAiCompletionBackend, OpenAiEmbeddingBackend

__all__ = [
    "CompletionBackend",
    "EmbeddingBackend",
    "get_completion_backend",
    "get_embedding_backend",
]


def get_embedding_backend(settings: Settings) -> EmbeddingBackend:
    if settings.ai_provider == "fake":
        return FakeEmbeddingBackend(dimensions=settings.fake_embedding_dimensions)

    return OpenAiEmbeddingBackend(settings)


def get_completion_backend(settings: Settings) -> CompletionBackend:
    if settings.ai_provider == "fake":
        return FakeCompletionBackend()

    return OpenAiCompletionBackend(settings)
