"""Shared helpers for creating list_items (used by wishlist and custom lists)."""

import os
import re
import uuid
from pathlib import Path
from typing import Final

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


class ListItemUploadError(ValueError):
    def __init__(self, message: str, status_code: int = 400) -> None:
        super().__init__(message)
        self.status_code = status_code


def slugify(value: str) -> str:
    slug = re.sub(r"[^a-z0-9]+", "-", value.lower()).strip("-")
    return slug or "movie"


def build_cover_filename(title: str, suffix: str) -> str:
    return f"{slugify(title)}-{uuid.uuid4().hex[:12]}{suffix}"


def normalize_title(value: str) -> str:
    normalized = value.strip()
    if not normalized:
        raise ListItemUploadError("Tittel er påkrevd.")
    return normalized


def normalize_original_title(value: str | None) -> str | None:
    if value is None:
        return None

    normalized = value.strip()
    return normalized or None


def normalize_external_id(value: str | None) -> str | None:
    if value is None:
        return None

    normalized = value.strip()
    return normalized or None


def normalize_season(value: str | None) -> str | None:
    """Free-text season note (e.g. "3", "1-3", "Alle sesonger").

    Purely a manual/personal field, unrelated to TVDB/TMDB data.
    """
    if value is None:
        return None

    normalized = value.strip()
    return normalized or None


def normalize_first_release(first_release_year: int | None) -> int | None:
    if first_release_year is None:
        return None

    if first_release_year < 1888 or first_release_year > 2100:
        raise ListItemUploadError("Årstall må være mellom 1888 og 2100.")

    return first_release_year


def read_cover_bytes(cover_bytes: bytes, content_type: str | None) -> tuple[bytes, str]:
    normalized_content_type = (content_type or "").lower().strip()
    suffix = ALLOWED_IMAGE_TYPES.get(normalized_content_type)
    if suffix is None:
        allowed = ", ".join(sorted(ALLOWED_IMAGE_TYPES))
        raise ListItemUploadError(
            f"Ugyldig bildefil. Tillatte typer er: {allowed}."
        )

    if not cover_bytes:
        raise ListItemUploadError("Bildefilen er tom.")

    if len(cover_bytes) > MAX_UPLOAD_BYTES:
        max_mb = MAX_UPLOAD_BYTES / (1024 * 1024)
        raise ListItemUploadError(
            f"Bildefilen er for stor. Maks størrelse er {max_mb:.0f} MB.",
            status_code=413,
        )

    return cover_bytes, suffix
