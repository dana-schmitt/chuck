import json
import logging
import time
from typing import Any

import openai

from app.config import Settings
from app.providers.base import CompletionBackend, EmbeddingBackend

logger = logging.getLogger("ai_service")

# Transient errors worth retrying; anything else (bad request, auth, etc.) fails fast.
_RETRYABLE_ERRORS = (
    openai.APITimeoutError,
    openai.APIConnectionError,
    openai.RateLimitError,
    openai.InternalServerError,
)


class AiBackendError(RuntimeError):
    """Raised when the OpenAI API call ultimately fails, after retries."""


def _call_with_retry(operation, *, max_retries: int, description: str):
    attempt = 0
    while True:
        started_at = time.monotonic()
        try:
            result = operation()
            logger.info(
                "%s succeeded",
                description,
                extra={"duration_ms": round((time.monotonic() - started_at) * 1000)},
            )
            return result
        except _RETRYABLE_ERRORS as exc:
            attempt += 1
            logger.warning(
                "%s failed (attempt %d/%d): %s",
                description,
                attempt,
                max_retries + 1,
                exc.__class__.__name__,
                extra={"duration_ms": round((time.monotonic() - started_at) * 1000)},
            )
            if attempt > max_retries:
                raise AiBackendError(f"{description} failed after {attempt} attempts.") from exc
            time.sleep(0.5 * (2 ** (attempt - 1)))
        except openai.OpenAIError as exc:
            logger.error(
                "%s failed with a non-retryable error: %s",
                description,
                exc.__class__.__name__,
            )
            raise AiBackendError(f"{description} failed: {exc.__class__.__name__}") from exc


def _chunked(items: list[str], size: int) -> list[list[str]]:
    return [items[i : i + size] for i in range(0, len(items), size)]


class OpenAiEmbeddingBackend(EmbeddingBackend):
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._client = openai.OpenAI(
            api_key=settings.openai_api_key,
            timeout=settings.request_timeout_seconds,
        )

    def embed(self, texts: list[str]) -> list[list[float]]:
        vectors: list[list[float]] = []

        for batch in _chunked(texts, self._settings.embeddings_batch_size):
            response = _call_with_retry(
                lambda batch=batch: self._client.embeddings.create(
                    model=self._settings.embedding_model,
                    input=batch,
                ),
                max_retries=self._settings.max_retries,
                description=f"OpenAI embeddings call ({len(batch)} texts)",
            )
            vectors.extend(item.embedding for item in response.data)

        return vectors


class OpenAiCompletionBackend(CompletionBackend):
    def __init__(self, settings: Settings) -> None:
        self._settings = settings
        self._client = openai.OpenAI(
            api_key=settings.openai_api_key,
            timeout=settings.request_timeout_seconds,
        )

    def complete(
        self,
        system: str,
        user: str,
        response_schema: dict[str, Any] | None,
        max_tokens: int,
    ) -> str | dict[str, Any]:
        kwargs: dict[str, Any] = {}
        if response_schema is not None:
            kwargs["response_format"] = {
                "type": "json_schema",
                "json_schema": {
                    "name": "response",
                    "schema": response_schema,
                    "strict": True,
                },
            }

        response = _call_with_retry(
            lambda: self._client.chat.completions.create(
                model=self._settings.completion_model,
                messages=[
                    {"role": "system", "content": system},
                    {"role": "user", "content": user},
                ],
                max_tokens=max_tokens,
                **kwargs,
            ),
            max_retries=self._settings.max_retries,
            description="OpenAI completion call",
        )

        content = response.choices[0].message.content or ""

        if response_schema is None:
            return content

        try:
            return json.loads(content)
        except json.JSONDecodeError as exc:
            raise AiBackendError("The model's response was not valid JSON.") from exc
