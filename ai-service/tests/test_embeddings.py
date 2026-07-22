from fastapi.testclient import TestClient


def test_embeddings_returns_one_vector_per_text(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    response = client.post(
        "/embeddings",
        json={"texts": ["Chuck Norris counts to infinity twice.", "Another joke"]},
        headers=auth_headers,
    )

    assert response.status_code == 200
    body = response.json()
    assert len(body["vectors"]) == 2
    assert all(len(vector) == 8 for vector in body["vectors"])


def test_embeddings_are_deterministic_for_the_same_text(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    payload = {"texts": ["The same joke, twice"]}

    first = client.post("/embeddings", json=payload, headers=auth_headers).json()
    second = client.post("/embeddings", json=payload, headers=auth_headers).json()

    assert first["vectors"] == second["vectors"]


def test_embeddings_differ_for_different_text(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    response = client.post(
        "/embeddings",
        json={"texts": ["Joke A", "Completely different joke B"]},
        headers=auth_headers,
    )

    vectors = response.json()["vectors"]
    assert vectors[0] != vectors[1]


def test_embeddings_rejects_empty_text_list(
    client: TestClient, auth_headers: dict[str, str]
) -> None:
    response = client.post("/embeddings", json={"texts": []}, headers=auth_headers)

    assert response.status_code == 422
