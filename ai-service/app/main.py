import logging

import jsonschema
from fastapi import Depends, FastAPI, HTTPException, status

from app.config import Settings, get_settings
from app.providers import get_completion_backend, get_embedding_backend
from app.providers.openai_backend import AiBackendError
from app.schemas import (
    CompleteRequest,
    CompleteResponse,
    EmbeddingsRequest,
    EmbeddingsResponse,
    HealthResponse,
)
from app.security import require_shared_secret

logger = logging.getLogger("ai_service")

app = FastAPI(title="Chuckify AI Service")


@app.get("/health", response_model=HealthResponse)
def health() -> HealthResponse:
    return HealthResponse()


@app.post(
    "/embeddings",
    response_model=EmbeddingsResponse,
    dependencies=[Depends(require_shared_secret)],
)
def create_embeddings(
    request: EmbeddingsRequest,
    settings: Settings = Depends(get_settings),
) -> EmbeddingsResponse:
    backend = get_embedding_backend(settings)

    try:
        vectors = backend.embed(request.texts)
    except AiBackendError as exc:
        raise HTTPException(status_code=status.HTTP_502_BAD_GATEWAY, detail=str(exc)) from exc

    return EmbeddingsResponse(vectors=vectors, model=settings.embedding_model)


@app.post(
    "/complete",
    response_model=CompleteResponse,
    dependencies=[Depends(require_shared_secret)],
)
def create_completion(
    request: CompleteRequest,
    settings: Settings = Depends(get_settings),
) -> CompleteResponse:
    backend = get_completion_backend(settings)

    try:
        result = backend.complete(
            system=request.system,
            user=request.user,
            response_schema=request.response_schema,
            max_tokens=request.max_tokens,
        )
    except AiBackendError as exc:
        raise HTTPException(status_code=status.HTTP_502_BAD_GATEWAY, detail=str(exc)) from exc

    if request.response_schema is None:
        return CompleteResponse(text=str(result), model=settings.completion_model)

    try:
        jsonschema.validate(result, request.response_schema)
    except jsonschema.ValidationError as exc:
        logger.error("Completion response failed schema validation: %s", exc.message)
        raise HTTPException(
            status_code=status.HTTP_502_BAD_GATEWAY,
            detail="The model's response did not match the requested schema.",
        ) from exc

    return CompleteResponse(data=result, model=settings.completion_model)
