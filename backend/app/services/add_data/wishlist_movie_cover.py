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


WISHLIST_LIST_NAME: Final[str] = "Wishlist"


class WishlistMovieUploadError(ValueError):
    def __init__(self, message: str, status_code: int = 400) -> None:
        super().__init__(message)
        self.status_code = status_code


def _get_or_create_wishlist_list_id(db: Session) -> bytes:
    row = db.execute(
        text("SELECT list_id FROM custom_lists WHERE list_name = :list_name"),
        {"list_name": WISHLIST_LIST_NAME},
    ).fetchone()

    if row is not None:
        return row[0]

    list_id = uuid.uuid4().bytes
    db.execute(
        text(
            """
            INSERT INTO custom_lists (list_id, list_name)
            VALUES (:list_id, :list_name)
            """
        ),
        {"list_id": list_id, "list_name": WISHLIST_LIST_NAME},
    )

    return list_id


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


def _normalize_external_id(value: str | None) -> str | None:
    if value is None:
        return None

    normalized = value.strip()
    return normalized or None


def _normalize_first_release(first_release_year: int | None) -> int | None:
    if first_release_year is None:
        return None

    if first_release_year < 1888 or first_release_year > 2100:
        raise WishlistMovieUploadError("Årstall må være mellom 1888 og 2100.")

    return first_release_year


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
        max_mb = MAX_UPLOAD_BYTES / (1024 * 1024)
        raise WishlistMovieUploadError(
            f"Bildefilen er for stor. Maks størrelse er {max_mb:.0f} MB.",
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
    imdb_id: str | None = None,
    tmdb_id: str | None = None,
    tvdb_id: str | None = None,
) -> dict[str, str | int | bool | None]:
    normalized_title = _normalize_title(title)
    normalized_original_title = _normalize_original_title(original_title)
    normalized_first_release_year = _normalize_first_release(first_release_year)
    normalized_imdb_id = _normalize_external_id(imdb_id)
    normalized_tmdb_id = _normalize_external_id(tmdb_id)
    normalized_tvdb_id = _normalize_external_id(tvdb_id)
    normalized_cover_bytes, cover_suffix = _read_cover_bytes(
        cover_bytes=cover_bytes,
        content_type=cover_content_type,
    )

    DEFAULT_UPLOAD_DIR.mkdir(parents=True, exist_ok=True)

    filename = _build_cover_filename(normalized_title, cover_suffix)
    public_path = f"{DEFAULT_PUBLIC_BASE_URL}/{filename}"
    absolute_path = DEFAULT_UPLOAD_DIR / filename
    list_item_id = uuid.uuid4().bytes

    absolute_path.write_bytes(normalized_cover_bytes)

    try:
        list_id = _get_or_create_wishlist_list_id(db)

        db.execute(
            text(
                """
                INSERT INTO list_items (
                    list_item_id,
                    title,
                    original_title,
                    first_release_year,
                    imdb_id,
                    tmdb_id,
                    tvdb_id,
                    cover_image
                )
                VALUES (
                    :list_item_id,
                    :title,
                    :original_title,
                    :first_release_year,
                    :imdb_id,
                    :tmdb_id,
                    :tvdb_id,
                    :cover_image
                )
                """
            ),
            {
                "list_item_id": list_item_id,
                "title": normalized_title,
                "original_title": normalized_original_title,
                "first_release_year": normalized_first_release_year,
                "imdb_id": normalized_imdb_id,
                "tmdb_id": normalized_tmdb_id,
                "tvdb_id": normalized_tvdb_id,
                "cover_image": public_path,
            },
        )

        db.execute(
            text(
                """
                INSERT INTO custom_list_entries (
                    list_id,
                    list_item_id
                )
                VALUES (
                    :list_id,
                    :list_item_id
                )
                """
            ),
            {
                "list_id": list_id,
                "list_item_id": list_item_id,
            },
        )
        db.commit()
    except SQLAlchemyError:
        db.rollback()
        absolute_path.unlink(missing_ok=True)
        raise

    return {
        "list_item_id": str(uuid.UUID(bytes=list_item_id)),
        "title": normalized_title,
        "original_title": normalized_original_title,
        "first_release_year": normalized_first_release_year,
        "imdb_id": normalized_imdb_id,
        "tmdb_id": normalized_tmdb_id,
        "tvdb_id": normalized_tvdb_id,
        "cover_image": public_path,
        "stored_in": WISHLIST_LIST_NAME,
    }
