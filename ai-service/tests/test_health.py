from fastapi.testclient import TestClient


def test_health_does_not_require_auth(client: TestClient) -> None:
    response = client.get("/health")

    assert response.status_code == 200
    assert response.json() == {"status": "ok"}
