import os
import re
import uuid
from pathlib import Path
from typing import Final

from sqlalchemy import text
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.orm import Session

DEFAULT_UPLOAD_DIR: Final[Path] = Path(
    os.getenv(
        "WISHLIST_COVER_UPLOAD_DIR",
        "/var/www/mmc.plexcity.net/public/uploads/wishlist-covers",
    )
)
DEFAULT_PUBLIC_BASE_URL: Final[str] = os.getenv(
    "WISHLIST_COVER_PUBLIC_BASE_URL",
    "/uploads/wishlist-covers",
).rstrip("/")
MAX_UPLOAD_BYTES: Final[int] = int(
    os.getenv("WISHLIST_COVER_MAX_BYTES", str(10 * 1024 * 1024))
)
ALLOWED_IMAGE_TYPES: Final[dict[str, str]] = {
    "image/jpeg": ".jpg",
    "image/jpg": ".jpg",
    "image/png": ".png",
    "image/pjpeg": ".jpg",
    "image/webp": ".webp",
    "image/heic": ".heic",
    "image/heif": ".heif",
}


class WishlistMovieUploadError(ValueError):
    def __init__(self, message: str, status_code: int = 400) -> None:
        super().__init__(message)
        self.status_code = status_code


def _slugify(value: str) -> str:
    slug = re.sub(r"[^a-z0-9]+", "-", value.lower()).strip("-")
    return slug or "movie"


def _build_cover_filename(title: str, suffix: str) -> str:
    return f"{_slugify(title)}-{uuid.uuid4().hex[:12]}{suffix}"


def _normalize_title(value: str) -> str:
    normalized = value.strip()
    if not normalized:
        raise WishlistMovieUploadError("Tittel er påkrevd.")
    return normalized


def _normalize_original_title(value: str | None) -> str | None:
    if value is None:
        return None

    normalized = value.strip()
    return normalized or None


def _normalize_first_release(first_release_year: int | None) -> str | None:
    if first_release_year is None:
        return None

    if first_release_year < 1888 or first_release_year > 2100:
        raise WishlistMovieUploadError("Årstall må være mellom 1888 og 2100.")

    return f"{first_release_year}-01-01 00:00:00"


def _read_cover_bytes(cover_bytes: bytes, content_type: str | None) -> tuple[bytes, str]:
    normalized_content_type = (content_type or "").lower().strip()
    suffix = ALLOWED_IMAGE_TYPES.get(normalized_content_type)
    if suffix is None:
        allowed = ", ".join(sorted(ALLOWED_IMAGE_TYPES))
        raise WishlistMovieUploadError(
            f"Ugyldig bildefil. Tillatte typer er: {allowed}."
        )

    if not cover_bytes:
        raise WishlistMovieUploadError("Bildefilen er tom.")

    if len(cover_bytes) > MAX_UPLOAD_BYTES:
        raise WishlistMovieUploadError(
            "Bildefilen er for stor. Maks størrelse er 10 MB.",
            status_code=413,
        )

    return cover_bytes, suffix


def create_wishlist_movie_with_cover(
    db: Session,
    title: str,
    cover_bytes: bytes,
    cover_content_type: str | None,
    original_title: str | None = None,
    first_release_year: int | None = None,
) -> dict[str, str | bool | None]:
    normalized_title = _normalize_title(title)
    normalized_original_title = _normalize_original_title(original_title)
    _normalize_first_release(first_release_year)
    normalized_cover_bytes, cover_suffix = _read_cover_bytes(
        cover_bytes=cover_bytes,
        content_type=cover_content_type,
    )

    DEFAULT_UPLOAD_DIR.mkdir(parents=True, exist_ok=True)

    filename = _build_cover_filename(normalized_title, cover_suffix)
    public_path = f"{DEFAULT_PUBLIC_BASE_URL}/{filename}"
    absolute_path = DEFAULT_UPLOAD_DIR / filename
    content_id = uuid.uuid4().bytes

    absolute_path.write_bytes(normalized_cover_bytes)

    try:
        db.execute(
            text(
                """
                INSERT INTO wishlist (
                    content_id,
                    title,
                    cover_image
                )
                VALUES (
                    :content_id,
                    :title,
                    :cover_image
                )
                """
            ),
            {
                "content_id": content_id,
                "title": normalized_title,
                "cover_image": public_path,
            },
        )
        db.commit()
    except SQLAlchemyError:
        db.rollback()
        absolute_path.unlink(missing_ok=True)
        raise

    return {
        "content_id": str(uuid.UUID(bytes=content_id)),
        "title": normalized_title,
        "original_title": normalized_original_title,
        "cover_image": public_path,
        "stored_in": "wishlist",
    }
