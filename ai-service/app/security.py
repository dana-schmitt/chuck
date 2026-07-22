import hmac

from fastapi import Depends, Header, HTTPException, status

from app.config import Settings, get_settings


def require_shared_secret(
    x_internal_secret: str = Header(default=""),
    settings: Settings = Depends(get_settings),
) -> None:
    if not settings.shared_secret:
        # Misconfiguration, not a client error - fail closed rather than silently
        # accepting every request because no secret was ever set.
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Service is not configured with a shared secret.",
        )

    if not hmac.compare_digest(x_internal_secret, settings.shared_secret):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid or missing credentials.",
        )
