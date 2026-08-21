import uuid
from typing import Final

from sqlalchemy import text
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.orm import Session

from app.services.add_data.list_item_shared import (
    DEFAULT_PUBLIC_BASE_URL,
    DEFAULT_UPLOAD_DIR,
    ListItemUploadError,
    build_cover_filename,
    normalize_external_id,
    normalize_first_release,
    normalize_original_title,
    normalize_title,
    read_cover_bytes,
)

# Kept as an alias for backwards compatibility with existing imports/callers.
WishlistMovieUploadError = ListItemUploadError

WISHLIST_LIST_NAME: Final[str] = "Wishlist"


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


def list_wishlist_movies(db: Session) -> list[dict[str, str | int | None]]:
    rows = db.execute(
        text(
            """
            SELECT
                li.list_item_id,
                li.title,
                li.original_title,
                li.first_release_year,
                li.imdb_id,
                li.tmdb_id,
                li.tvdb_id,
                li.cover_image
            FROM list_items li
            JOIN custom_list_entries cle ON cle.list_item_id = li.list_item_id
            JOIN custom_lists cl ON cl.list_id = cle.list_id
            WHERE cl.list_name = :list_name
            ORDER BY li.title ASC
            """
        ),
        {"list_name": WISHLIST_LIST_NAME},
    ).fetchall()

    return [
        {
            "list_item_id": str(uuid.UUID(bytes=row.list_item_id)),
            "title": row.title,
            "original_title": row.original_title,
            "first_release_year": row.first_release_year,
            "imdb_id": row.imdb_id,
            "tmdb_id": row.tmdb_id,
            "tvdb_id": row.tvdb_id,
            "cover_image": row.cover_image,
        }
        for row in rows
    ]


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
    normalized_title = normalize_title(title)
    normalized_original_title = normalize_original_title(original_title)
    normalized_first_release_year = normalize_first_release(first_release_year)
    normalized_imdb_id = normalize_external_id(imdb_id)
    normalized_tmdb_id = normalize_external_id(tmdb_id)
    normalized_tvdb_id = normalize_external_id(tvdb_id)
    normalized_cover_bytes, cover_suffix = read_cover_bytes(
        cover_bytes=cover_bytes,
        content_type=cover_content_type,
    )

    DEFAULT_UPLOAD_DIR.mkdir(parents=True, exist_ok=True)

    filename = build_cover_filename(normalized_title, cover_suffix)
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
