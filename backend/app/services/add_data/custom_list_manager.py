import uuid

from sqlalchemy import text
from sqlalchemy.exc import IntegrityError, SQLAlchemyError
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


def _parse_list_id(list_id: str) -> bytes:
    try:
        return uuid.UUID(list_id).bytes
    except (ValueError, AttributeError, TypeError):
        raise ListItemUploadError("Ugyldig list_id.")


def list_custom_lists(
    db: Session, exclude_names: list[str] | None = None
) -> list[dict[str, str]]:
    exclude_names = exclude_names or []

    query = "SELECT list_id, list_name FROM custom_lists"
    params: dict[str, object] = {}

    if exclude_names:
        placeholders = ", ".join(f":exclude_{i}" for i in range(len(exclude_names)))
        query += f" WHERE list_name NOT IN ({placeholders})"
        for i, name in enumerate(exclude_names):
            params[f"exclude_{i}"] = name

    query += " ORDER BY list_name ASC"

    rows = db.execute(text(query), params).fetchall()

    return [
        {
            "list_id": str(uuid.UUID(bytes=row.list_id)),
            "list_name": row.list_name,
        }
        for row in rows
    ]


def create_custom_list(db: Session, list_name: str) -> dict[str, str]:
    normalized_name = list_name.strip()
    if not normalized_name:
        raise ListItemUploadError("Listenavn er påkrevd.")

    list_id = uuid.uuid4().bytes

    try:
        db.execute(
            text(
                """
                INSERT INTO custom_lists (list_id, list_name)
                VALUES (:list_id, :list_name)
                """
            ),
            {"list_id": list_id, "list_name": normalized_name},
        )
        db.commit()
    except IntegrityError:
        db.rollback()
        raise ListItemUploadError(
            f'En liste med navnet "{normalized_name}" finnes allerede.',
            status_code=409,
        )
    except SQLAlchemyError:
        db.rollback()
        raise

    return {
        "list_id": str(uuid.UUID(bytes=list_id)),
        "list_name": normalized_name,
    }


def add_item_to_custom_list(
    db: Session,
    list_id: str,
    title: str,
    cover_bytes: bytes | None = None,
    cover_content_type: str | None = None,
    original_title: str | None = None,
    first_release_year: int | None = None,
    imdb_id: str | None = None,
    tmdb_id: str | None = None,
    tvdb_id: str | None = None,
) -> dict[str, str | int | bool | None]:
    parsed_list_id = _parse_list_id(list_id)

    list_row = db.execute(
        text("SELECT list_id, list_name FROM custom_lists WHERE list_id = :list_id"),
        {"list_id": parsed_list_id},
    ).fetchone()

    if list_row is None:
        raise ListItemUploadError("Fant ikke listen. Velg en gyldig liste.", status_code=404)

    normalized_title = normalize_title(title)
    normalized_original_title = normalize_original_title(original_title)
    normalized_first_release_year = normalize_first_release(first_release_year)
    normalized_imdb_id = normalize_external_id(imdb_id)
    normalized_tmdb_id = normalize_external_id(tmdb_id)
    normalized_tvdb_id = normalize_external_id(tvdb_id)

    # Cover image is optional for custom lists (unlike the wishlist upload flow).
    has_cover = cover_bytes is not None and len(cover_bytes) > 0
    public_path = None
    absolute_path = None

    if has_cover:
        normalized_cover_bytes, cover_suffix = read_cover_bytes(
            cover_bytes=cover_bytes,
            content_type=cover_content_type,
        )

        DEFAULT_UPLOAD_DIR.mkdir(parents=True, exist_ok=True)

        filename = build_cover_filename(normalized_title, cover_suffix)
        public_path = f"{DEFAULT_PUBLIC_BASE_URL}/{filename}"
        absolute_path = DEFAULT_UPLOAD_DIR / filename
        absolute_path.write_bytes(normalized_cover_bytes)

    list_item_id = uuid.uuid4().bytes

    try:
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
                "list_id": parsed_list_id,
                "list_item_id": list_item_id,
            },
        )
        db.commit()
    except SQLAlchemyError:
        db.rollback()
        if absolute_path is not None:
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
        "stored_in": list_row.list_name,
    }
