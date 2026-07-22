from typing import Any

import pytest
from fastapi.testclient import TestClient

import app.main as main_module
from app.providers.base import CompletionBackend

MODERATION_SCHEMA = {
    "type": "object",
    "properties": {
        "recommendation": {"type": "string", "enum": ["approve", "reject", "unsure"]},
        "confidence": {"type": "number"},
        "reasons": {"type": "array"},
    },
    "required": ["recommendation", "confidence", "reasons"],
}


def test_complete_without_schema_returns_plain_text(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    response = client.post(
        "/complete",
        json={"system": "You are terse.", "user": "Say hi", "max_tokens": 50},
        headers=auth_headers,
    )

    assert response.status_code == 200
    body = response.json()
    assert body["text"] is not None
    assert body["data"] is None


def test_complete_with_schema_returns_validated_structured_data(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    response = client.post(
        "/complete",
        json={
            "system": "You moderate jokes.",
            "user": "Evaluate this joke.",
            "response_schema": MODERATION_SCHEMA,
            "max_tokens": 200,
        },
        headers=auth_headers,
    )

    assert response.status_code == 200
    data = response.json()["data"]
    assert data["recommendation"] in ("approve", "reject", "unsure")
    assert isinstance(data["confidence"], (int, float))
    assert isinstance(data["reasons"], list)


def test_complete_rejects_backend_response_that_fails_schema_validation(
    client: TestClient, auth_headers: dict[str, str], monkeypatch: pytest.MonkeyPatch
) -> None:
    class BrokenBackend(CompletionBackend):
        def complete(
            self,
            system: str,
            user: str,
            response_schema: dict[str, Any] | None,
            max_tokens: int,
        ) -> dict[str, Any]:
            return {"totally": "wrong shape"}

    monkeypatch.setattr(main_module, "get_completion_backend", lambda settings: BrokenBackend())

    response = client.post(
        "/complete",
        json={
            "system": "You moderate jokes.",
            "user": "Evaluate this joke.",
            "response_schema": MODERATION_SCHEMA,
            "max_tokens": 200,
        },
        headers=auth_headers,
    )

    assert response.status_code == 502
