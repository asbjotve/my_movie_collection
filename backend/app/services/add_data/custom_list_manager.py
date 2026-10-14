import uuid

from sqlalchemy import bindparam, text
from sqlalchemy.exc import IntegrityError, SQLAlchemyError
from sqlalchemy.orm import Session

from app.services.external_apis import fetch_tmdb_poster_url
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


def _parse_content_id(content_id: str) -> bytes:
    try:
        return uuid.UUID(content_id).bytes
    except (ValueError, AttributeError, TypeError):
        raise ListItemUploadError("Ugyldig content_id.")


def _normalize_group_label(group_label: str | None) -> str | None:
    if group_label is None:
        return None
    normalized = group_label.strip()
    return normalized or None


def list_custom_lists(
    db: Session,
    exclude_names: list[str] | None = None,
    only_visible: bool = False,
) -> list[dict[str, str | int | bool]]:
    exclude_names = exclude_names or []

    # item_count telles med en LEFT JOIN (ikke en subquery per rad), slik
    # at tomme lister fortsatt vises (med item_count=0) - brukes bl.a. av
    # "Andre lister"-panelet i website_template_example for å vise antall
    # elementer uten å måtte gjøre ett ekstra kall pr. liste.
    query = """
        SELECT cl.list_id, cl.list_name, cl.show_on_frontend,
               COUNT(cle.list_item_id) AS item_count
        FROM custom_lists cl
        LEFT JOIN custom_list_entries cle ON cle.list_id = cl.list_id
    """
    params: dict[str, object] = {}
    conditions: list[str] = []

    if exclude_names:
        placeholders = ", ".join(f":exclude_{i}" for i in range(len(exclude_names)))
        conditions.append(f"cl.list_name NOT IN ({placeholders})")
        for i, name in enumerate(exclude_names):
            params[f"exclude_{i}"] = name

    # Used by the public "Andre lister" panel (website_template_example/v19)
    # to only ever see lists an admin has explicitly opted in - see
    # set_list_visibility() below.
    if only_visible:
        conditions.append("cl.show_on_frontend = 1")

    if conditions:
        query += " WHERE " + " AND ".join(conditions)

    query += " GROUP BY cl.list_id, cl.list_name, cl.show_on_frontend ORDER BY cl.list_name ASC"

    rows = db.execute(text(query), params).fetchall()

    return [
        {
            "list_id": str(uuid.UUID(bytes=row.list_id)),
            "list_name": row.list_name,
            "item_count": int(row.item_count),
            "show_on_frontend": bool(row.show_on_frontend),
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


def set_list_visibility(db: Session, list_id: str, show_on_frontend: bool) -> dict[str, str | bool]:
    """Toggle whether a list appears in the public "Andre lister" panel -
    see list_custom_lists()'s only_visible filter."""
    list_id_bytes = _parse_list_id(list_id)

    result = db.execute(
        text(
            """
            UPDATE custom_lists
            SET show_on_frontend = :show_on_frontend
            WHERE list_id = :list_id
            """
        ),
        {"list_id": list_id_bytes, "show_on_frontend": 1 if show_on_frontend else 0},
    )
    if result.rowcount == 0:
        db.rollback()
        raise ListItemUploadError("Listen finnes ikke.", status_code=404)
    db.commit()

    return {"list_id": list_id, "show_on_frontend": show_on_frontend}


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
    group_label: str | None = None,
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
    normalized_group_label = _normalize_group_label(group_label)

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
                    list_item_id,
                    group_label
                )
                VALUES (
                    :list_id,
                    :list_item_id,
                    :group_label
                )
                """
            ),
            {
                "list_id": parsed_list_id,
                "list_item_id": list_item_id,
                "group_label": normalized_group_label,
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


def add_content_to_custom_list(
    db: Session,
    list_id: str,
    content_id: str,
    group_label: str | None = None,
) -> dict[str, str | int | bool | None]:
    """Adds a movie/series already owned in the main collection
    ("content") to a custom list, without going through the TMDB search
    modal - pulls title/original_title/year/imdb_id/cover_image straight
    from the content row (and tmdb_id/tvdb_id from content_external_source)
    into a brand new, independent list_items row.

    content.cover_image is already a full, directly-usable URL (the TMDB
    CDN URL set by set_content_cover_image() in media_catalog.py), so it
    can be copied over as-is - no re-upload/re-hosting needed, unlike the
    normal "paste a TMDB result + manually upload a cover" add-item flow.
    """
    parsed_list_id = _parse_list_id(list_id)
    parsed_content_id = _parse_content_id(content_id)
    normalized_group_label = _normalize_group_label(group_label)

    list_row = db.execute(
        text("SELECT list_id, list_name FROM custom_lists WHERE list_id = :list_id"),
        {"list_id": parsed_list_id},
    ).fetchone()

    if list_row is None:
        raise ListItemUploadError("Fant ikke listen. Velg en gyldig liste.", status_code=404)

    content_row = db.execute(
        text(
            """
            SELECT content_id, title, original_title, first_release, imdb_id, cover_image
            FROM content
            WHERE content_id = :content_id
            """
        ),
        {"content_id": parsed_content_id},
    ).fetchone()

    if content_row is None:
        raise ListItemUploadError("Fant ikke filmen/serien i samlingen.", status_code=404)

    external_rows = db.execute(
        text(
            """
            SELECT source, external_id
            FROM content_external_source
            WHERE content_id = :content_id AND source IN ('tmdb', 'tvdb')
            """
        ),
        {"content_id": parsed_content_id},
    ).fetchall()
    external_ids = {row.source: row.external_id for row in external_rows}

    normalized_title = normalize_title(content_row.title)
    normalized_original_title = normalize_original_title(content_row.original_title)
    normalized_first_release_year = (
        content_row.first_release.year if content_row.first_release else None
    )
    normalized_imdb_id = normalize_external_id(content_row.imdb_id)
    normalized_tmdb_id = normalize_external_id(external_ids.get("tmdb"))
    normalized_tvdb_id = normalize_external_id(external_ids.get("tvdb"))
    cover_image = content_row.cover_image

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
                "cover_image": cover_image,
            },
        )

        db.execute(
            text(
                """
                INSERT INTO custom_list_entries (
                    list_id,
                    list_item_id,
                    group_label
                )
                VALUES (
                    :list_id,
                    :list_item_id,
                    :group_label
                )
                """
            ),
            {
                "list_id": parsed_list_id,
                "list_item_id": list_item_id,
                "group_label": normalized_group_label,
            },
        )
        db.commit()
    except SQLAlchemyError:
        db.rollback()
        raise

    return {
        "list_item_id": str(uuid.UUID(bytes=list_item_id)),
        "title": normalized_title,
        "original_title": normalized_original_title,
        "first_release_year": normalized_first_release_year,
        "imdb_id": normalized_imdb_id,
        "tmdb_id": normalized_tmdb_id,
        "tvdb_id": normalized_tvdb_id,
        "season": None,
        "cover_image": cover_image,
        "group_label": normalized_group_label,
        "stored_in": list_row.list_name,
    }


def get_list_items(
    db: Session, list_id: str, current_user_id: int | None = None
) -> list[dict[str, str | int | None]]:
    """Returns all items in a custom list (used by the inline "edit list" view
    in custom_list_manager v4 - v3's separate "add item" form/tab did not
    have a way to see or edit items already in a list).

    current_user_id: hvis satt (innlogget bruker kjent for kallet, se
    GET /lists/{list_id}/items sin username-parameter - samme mønster
    som list_content() i media_catalog.py), fylles watched_flag ut med
    DENNE brukerens sett-status (via content_watched) for elementer som
    matcher en rad i content (imdb_id/tmdb_id, se matched_content_id
    under - samme matching som owned-feltet bruker). Er
    current_user_id None, eller matcher ikke elementet noen content-rad,
    er watched_flag alltid False - det finnes ingen enkel måte å
    registrere/redigere sett-datoer herfra (kun en visningsbadge), for
    det må man inn på selve filmens detaljside/Mine filmer."""
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
                cle.sort_order,
                cle.group_label,
                (
                    SELECT c.content_id FROM content c
                    WHERE li.imdb_id IS NOT NULL AND li.imdb_id <> ''
                      AND c.imdb_id = li.imdb_id
                    LIMIT 1
                ) AS matched_content_id_by_imdb,
                (
                    SELECT c.content_id FROM content c
                    JOIN content_external_source ces ON ces.content_id = c.content_id
                    WHERE li.tmdb_id IS NOT NULL AND li.tmdb_id <> ''
                      AND ces.source = 'tmdb' AND ces.external_id = li.tmdb_id
                    LIMIT 1
                ) AS matched_content_id_by_tmdb
            FROM custom_list_entries cle
            JOIN list_items li ON li.list_item_id = cle.list_item_id
            WHERE cle.list_id = :list_id
            ORDER BY (cle.sort_order IS NULL) ASC, cle.sort_order ASC, li.title ASC
            """
        ),
        {"list_id": parsed_list_id},
    ).fetchall()

    # Matchede content_id-er for DENNE brukerens sett-status slås opp i
    # én samlet spørring (i stedet for én spørring pr. rad) - samme
    # "hent alt i ett kall, bygg et dict"-mønster som watched_dates_by_content
    # i list_content() (media_catalog.py).
    watched_content_ids: set[bytes] = set()
    if current_user_id is not None:
        matched_ids = {
            row.matched_content_id_by_imdb or row.matched_content_id_by_tmdb
            for row in rows
            if (row.matched_content_id_by_imdb or row.matched_content_id_by_tmdb) is not None
        }
        if matched_ids:
            watched_content_ids = {
                row.content_id
                for row in db.execute(
                    text(
                        """
                        SELECT DISTINCT content_id FROM content_watched
                        WHERE user_id = :user_id AND content_id IN :content_ids
                        """
                    ).bindparams(bindparam("content_ids", expanding=True)),
                    {"user_id": current_user_id, "content_ids": list(matched_ids)},
                ).fetchall()
            }

    # Fysisk eierskap (content_in_physical_collection) vs. kun Plex
    # (content_in_digital_library) - samme to kilder/distinksjon som
    # "owned"/"plex"-filteret i Mine filmer (se list_content() i
    # media_catalog.py og dens in_digital_library/collections-felter).
    # Slås opp i to samlede spørringer (samme batch-mønster som
    # watched_content_ids over), uavhengig av om noen elementer i det
    # hele tatt matcher en content-rad.
    physical_content_ids: set[bytes] = {
        row.content_id
        for row in db.execute(
            text("SELECT DISTINCT content_id FROM content_in_physical_collection")
        ).fetchall()
    }
    digital_library_content_ids: set[bytes] = {
        row.content_id
        for row in db.execute(
            text("SELECT DISTINCT content_id FROM content_in_digital_library")
        ).fetchall()
    }

    result = []
    for row in rows:
        matched_content_id = row.matched_content_id_by_imdb or row.matched_content_id_by_tmdb
        owned_physical = matched_content_id is not None and matched_content_id in physical_content_ids
        # "in_plex" utelukker elementer som også er fysisk eid, slik at
        # badgen i frontend ikke viser "I Plex" for noe som allerede
        # vises som "Eid" (samme eksklusivitet som ownership-filteret
        # i Mine filmer bruker for "plex"-valget).
        in_plex = (
            matched_content_id is not None
            and matched_content_id in digital_library_content_ids
            and not owned_physical
        )
        result.append(
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
                "group_label": row.group_label,
                "owned": matched_content_id is not None,
                "watched_flag": matched_content_id is not None and matched_content_id in watched_content_ids,
            }
        )
    return result



def get_list_item_cover_preview(db: Session, list_item_id: str) -> dict[str, str | bool | None]:
    """Returns a cover image to show for a single list item - its own
    stored cover_image if it has one, otherwise a temporary (not
    persisted) poster fetched live from TMDB via tmdb_id, used by the
    "Vis cover" button in website_template_example/v19's "Andre lister"
    panel for items that were added without ever getting a cover_image
    (e.g. picked straight from a TMDB search instead of copied from the
    collection)."""
    parsed_item_id = _parse_list_item_id(list_item_id)

    row = db.execute(
        text(
            "SELECT cover_image, tmdb_id, season FROM list_items WHERE list_item_id = :list_item_id"
        ),
        {"list_item_id": parsed_item_id},
    ).fetchone()

    if row is None:
        raise ListItemUploadError("Fant ikke elementet.", status_code=404)

    if row.cover_image:
        return {"cover_image": row.cover_image, "temporary": False}

    if not row.tmdb_id:
        raise ListItemUploadError("Ingen cover tilgjengelig for dette elementet.", status_code=404)

    content_type = "tv" if row.season else "movie"
    poster_url = fetch_tmdb_poster_url(row.tmdb_id, content_type)

    if not poster_url:
        raise ListItemUploadError("Fant ingen cover på TMDB for dette elementet.", status_code=404)

    return {"cover_image": poster_url, "temporary": True}


def set_list_item_group_label(
    db: Session, list_id: str, list_item_id: str, group_label: str | None
) -> dict[str, str | None]:
    """Sets (or clears, if group_label is empty) the group label of
    a single entry within a list - e.g. for Marvel-style ordered
    lists. Stored on custom_list_entries, not list_items, since it's a
    property of the item's placement within this specific list."""
    parsed_list_id = _parse_list_id(list_id)
    parsed_item_id = _parse_list_item_id(list_item_id)
    normalized_group_label = _normalize_group_label(group_label)

    entry_row = db.execute(
        text(
            "SELECT 1 FROM custom_list_entries WHERE list_id = :list_id AND list_item_id = :list_item_id"
        ),
        {"list_id": parsed_list_id, "list_item_id": parsed_item_id},
    ).fetchone()

    if entry_row is None:
        raise ListItemUploadError("Fant ikke elementet i denne listen.", status_code=404)

    try:
        db.execute(
            text(
                """
                UPDATE custom_list_entries
                SET group_label = :group_label
                WHERE list_id = :list_id AND list_item_id = :list_item_id
                """
            ),
            {
                "group_label": normalized_group_label,
                "list_id": parsed_list_id,
                "list_item_id": parsed_item_id,
            },
        )
        db.commit()
    except SQLAlchemyError:
        db.rollback()
        raise

    return {"list_item_id": list_item_id, "group_label": normalized_group_label}


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
