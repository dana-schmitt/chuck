from fastapi.testclient import TestClient

from app.config import Settings, get_settings
from app.main import app


def test_embeddings_requires_auth_header(client: TestClient) -> None:
    response = client.post("/embeddings", json={"texts": ["a joke"]})

    assert response.status_code == 401


def test_embeddings_rejects_wrong_secret(client: TestClient) -> None:
    response = client.post(
        "/embeddings",
        json={"texts": ["a joke"]},
        headers={"X-Internal-Secret": "not-the-right-secret"},
    )

    assert response.status_code == 401


def test_embeddings_accepts_correct_secret(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    response = client.post("/embeddings", json={"texts": ["a joke"]}, headers=auth_headers)

    assert response.status_code == 200


def test_missing_shared_secret_configuration_fails_closed(auth_headers: dict[str, str]) -> None:
    unconfigured_settings = Settings(ai_provider="fake", shared_secret="")
    app.dependency_overrides[get_settings] = lambda: unconfigured_settings
    try:
        client = TestClient(app)
        response = client.post("/embeddings", json={"texts": ["a joke"]}, headers=auth_headers)

        assert response.status_code == 503
    finally:
        app.dependency_overrides.clear()
