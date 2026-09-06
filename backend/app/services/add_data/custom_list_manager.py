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
    normalize_season,
    normalize_title,
    read_cover_bytes,
)


def _parse_list_id(list_id: str) -> bytes:
    try:
        return uuid.UUID(list_id).bytes
    except (ValueError, AttributeError, TypeError):
        raise ListItemUploadError("Ugyldig list_id.")


def _parse_list_item_id(list_item_id: str) -> bytes:
    try:
        return uuid.UUID(list_item_id).bytes
    except (ValueError, AttributeError, TypeError):
        raise ListItemUploadError("Ugyldig list_item_id.")


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
    season: str | None = None,
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
    normalized_season = normalize_season(season)

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
                    season,
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
                    :season,
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
                "season": normalized_season,
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
        "season": normalized_season,
        "cover_image": public_path,
        "stored_in": list_row.list_name,
    }


def get_list_items(db: Session, list_id: str) -> list[dict[str, str | int | None]]:
    """Returns all items in a custom list (used by the inline "edit list" view
    in custom_list_manager v4 - v3's separate "add item" form/tab did not
    have a way to see or edit items already in a list)."""
    parsed_list_id = _parse_list_id(list_id)

    list_row = db.execute(
        text("SELECT list_id FROM custom_lists WHERE list_id = :list_id"),
        {"list_id": parsed_list_id},
    ).fetchone()

    if list_row is None:
        raise ListItemUploadError("Fant ikke listen.", status_code=404)

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
                li.season,
                li.cover_image,
                cle.sort_order
            FROM custom_list_entries cle
            JOIN list_items li ON li.list_item_id = cle.list_item_id
            WHERE cle.list_id = :list_id
            ORDER BY (cle.sort_order IS NULL) ASC, cle.sort_order ASC, li.title ASC
            """
        ),
        {"list_id": parsed_list_id},
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
            "season": row.season,
            "cover_image": row.cover_image,
            "sort_order": row.sort_order,
        }
        for row in rows
    ]


def reorder_list_items(db: Session, list_id: str, list_item_ids: list[str]) -> dict[str, bool | int]:
    """Persists a new manual order for a list's items (drag-and-drop
    reordering / "sort by X" quick actions in the v4 "Rediger liste" view).
    Assigns sort_order = 1, 2, 3... in the given order; items not present
    in list_item_ids (shouldn't normally happen) keep their existing
    sort_order untouched."""
    parsed_list_id = _parse_list_id(list_id)

    list_row = db.execute(
        text("SELECT list_id FROM custom_lists WHERE list_id = :list_id"),
        {"list_id": parsed_list_id},
    ).fetchone()

    if list_row is None:
        raise ListItemUploadError("Fant ikke listen.", status_code=404)

    try:
        updated = 0
        for position, list_item_id in enumerate(list_item_ids, start=1):
            parsed_item_id = _parse_list_item_id(list_item_id)
            result = db.execute(
                text(
                    """
                    UPDATE custom_list_entries
                    SET sort_order = :sort_order
                    WHERE list_id = :list_id AND list_item_id = :list_item_id
                    """
                ),
                {
                    "sort_order": position,
                    "list_id": parsed_list_id,
                    "list_item_id": parsed_item_id,
                },
            )
            updated += result.rowcount
        db.commit()
    except SQLAlchemyError:
        db.rollback()
        raise

    return {"reordered": True, "updated": updated}


def update_list_item(
    db: Session,
    list_item_id: str,
    title: str,
    cover_bytes: bytes | None = None,
    cover_content_type: str | None = None,
    original_title: str | None = None,
    first_release_year: int | None = None,
    imdb_id: str | None = None,
    tmdb_id: str | None = None,
    tvdb_id: str | None = None,
    season: str | None = None,
) -> dict[str, str | int | None]:
    """Updates all editable fields of an existing list item (full replace,
    not a partial patch) - mirrors add_item_to_custom_list()'s validation/
    normalization, but UPDATEs list_items instead of INSERTing a new row.
    Cover image is only replaced when a new file is actually uploaded; the
    old file is only deleted from disk after the DB commit succeeds."""
    parsed_item_id = _parse_list_item_id(list_item_id)

    existing_row = db.execute(
        text("SELECT list_item_id, cover_image FROM list_items WHERE list_item_id = :list_item_id"),
        {"list_item_id": parsed_item_id},
    ).fetchone()

    if existing_row is None:
        raise ListItemUploadError("Fant ikke elementet.", status_code=404)

    normalized_title = normalize_title(title)
    normalized_original_title = normalize_original_title(original_title)
    normalized_first_release_year = normalize_first_release(first_release_year)
    normalized_imdb_id = normalize_external_id(imdb_id)
    normalized_tmdb_id = normalize_external_id(tmdb_id)
    normalized_tvdb_id = normalize_external_id(tvdb_id)
    normalized_season = normalize_season(season)

    has_new_cover = cover_bytes is not None and len(cover_bytes) > 0
    old_cover_image = existing_row.cover_image
    new_public_path = None
    new_absolute_path = None

    if has_new_cover:
        normalized_cover_bytes, cover_suffix = read_cover_bytes(
            cover_bytes=cover_bytes,
            content_type=cover_content_type,
        )

        DEFAULT_UPLOAD_DIR.mkdir(parents=True, exist_ok=True)

        filename = build_cover_filename(normalized_title, cover_suffix)
        new_public_path = f"{DEFAULT_PUBLIC_BASE_URL}/{filename}"
        new_absolute_path = DEFAULT_UPLOAD_DIR / filename
        new_absolute_path.write_bytes(normalized_cover_bytes)

    update_params = {
        "list_item_id": parsed_item_id,
        "title": normalized_title,
        "original_title": normalized_original_title,
        "first_release_year": normalized_first_release_year,
        "imdb_id": normalized_imdb_id,
        "tmdb_id": normalized_tmdb_id,
        "tvdb_id": normalized_tvdb_id,
        "season": normalized_season,
    }

    cover_set_clause = ""
    if has_new_cover:
        cover_set_clause = ", cover_image = :cover_image"
        update_params["cover_image"] = new_public_path

    try:
        db.execute(
            text(
                f"""
                UPDATE list_items SET
                    title = :title,
                    original_title = :original_title,
                    first_release_year = :first_release_year,
                    imdb_id = :imdb_id,
                    tmdb_id = :tmdb_id,
                    tvdb_id = :tvdb_id,
                    season = :season
                    {cover_set_clause}
                WHERE list_item_id = :list_item_id
                """
            ),
            update_params,
        )
        db.commit()
    except SQLAlchemyError:
        db.rollback()
        if new_absolute_path is not None:
            new_absolute_path.unlink(missing_ok=True)
        raise

    if has_new_cover and old_cover_image:
        old_filename = old_cover_image.rsplit("/", 1)[-1]
        (DEFAULT_UPLOAD_DIR / old_filename).unlink(missing_ok=True)

    return {
        "list_item_id": list_item_id,
        "title": normalized_title,
        "original_title": normalized_original_title,
        "first_release_year": normalized_first_release_year,
        "imdb_id": normalized_imdb_id,
        "tmdb_id": normalized_tmdb_id,
        "tvdb_id": normalized_tvdb_id,
        "season": normalized_season,
        "cover_image": new_public_path if has_new_cover else old_cover_image,
    }


def delete_list_item(db: Session, list_id: str, list_item_id: str) -> dict[str, bool | str]:
    """Removes an item from a list. If the item isn't referenced by any
    other list afterwards, the list_items row itself (and its cover image
    file, if any) is deleted too - list_items has no other owner/reference
    once its last custom_list_entries row is gone."""
    parsed_list_id = _parse_list_id(list_id)
    parsed_item_id = _parse_list_item_id(list_item_id)

    entry_row = db.execute(
        text(
            "SELECT 1 FROM custom_list_entries WHERE list_id = :list_id AND list_item_id = :list_item_id"
        ),
        {"list_id": parsed_list_id, "list_item_id": parsed_item_id},
    ).fetchone()

    if entry_row is None:
        raise ListItemUploadError("Fant ikke elementet i denne listen.", status_code=404)

    cover_image_to_delete = None

    try:
        db.execute(
            text(
                "DELETE FROM custom_list_entries WHERE list_id = :list_id AND list_item_id = :list_item_id"
            ),
            {"list_id": parsed_list_id, "list_item_id": parsed_item_id},
        )

        remaining_row = db.execute(
            text(
                "SELECT COUNT(*) AS remaining_count FROM custom_list_entries WHERE list_item_id = :list_item_id"
            ),
            {"list_item_id": parsed_item_id},
        ).fetchone()

        if remaining_row is not None and remaining_row.remaining_count == 0:
            cover_row = db.execute(
                text("SELECT cover_image FROM list_items WHERE list_item_id = :list_item_id"),
                {"list_item_id": parsed_item_id},
            ).fetchone()
            cover_image_to_delete = cover_row.cover_image if cover_row is not None else None

            db.execute(
                text("DELETE FROM list_items WHERE list_item_id = :list_item_id"),
                {"list_item_id": parsed_item_id},
            )

        db.commit()
    except SQLAlchemyError:
        db.rollback()
        raise

    if cover_image_to_delete:
        filename = cover_image_to_delete.rsplit("/", 1)[-1]
        (DEFAULT_UPLOAD_DIR / filename).unlink(missing_ok=True)

    return {"deleted": True, "list_item_id": list_item_id}
