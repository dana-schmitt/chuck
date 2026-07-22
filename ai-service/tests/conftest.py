import pytest
from fastapi.testclient import TestClient

from app.config import Settings, get_settings
from app.main import app

SHARED_SECRET = "test-shared-secret"


@pytest.fixture
def settings() -> Settings:
    return Settings(
        ai_provider="fake",
        shared_secret=SHARED_SECRET,
        fake_embedding_dimensions=8,
    )


@pytest.fixture
def client(settings: Settings) -> TestClient:
    app.dependency_overrides[get_settings] = lambda: settings
    try:
        yield TestClient(app)
    finally:
        app.dependency_overrides.clear()


@pytest.fixture
def auth_headers() -> dict[str, str]:
    return {"X-Internal-Secret": SHARED_SECRET}
