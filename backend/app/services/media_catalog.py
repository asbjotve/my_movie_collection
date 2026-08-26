"""Read-only tjenester for media-katalogen (content + tilhørende tabeller).

Dette er starten på et API-lag som etter hvert skal erstatte den
direkte MySQL-tilgangen som website_template_example v15/v17 bruker
fra PHP (se app/media_db.py for tilkoblingen). Holdes bevisst enkelt
i første omgang - én spørring for content, én for fysiske utgaver, én
for eksterne kilder - og kan utvides med paginering/filtrering/sortering
senere etter hvert som mer av frontend flyttes over hit.
"""

import uuid

from sqlalchemy import text
from sqlalchemy.orm import Session


def _hex_id(raw: bytes) -> str:
    return uuid.UUID(bytes=raw).hex.upper()


def _parse_hex_id(content_id: str) -> bytes:
    return uuid.UUID(hex=content_id).bytes


def list_content(db: Session) -> list[dict]:
    """Henter alle content-rader, med tilhørende fysiske utgaver og
    eksterne kilder gruppert inn i hvert content-objekt.
    """

    content_rows = db.execute(
        text(
            """
            SELECT
                content_id,
                title,
                original_title,
                first_release,
                runtime,
                age_restriction,
                watched_flag,
                temporary_flag,
                content_type,
                imdb_id
            FROM content
            ORDER BY title ASC
            """
        )
    ).fetchall()

    collection_rows = db.execute(
        text(
            """
            SELECT
                cipc.content_id AS content_id,
                pc.collection_id AS collection_id,
                pc.format AS format,
                pc.barcode AS barcode,
                pc.box_set_barcode AS box_set_barcode
            FROM content_in_physical_collection cipc
            JOIN physical_collection pc ON pc.collection_id = cipc.collection_id
            """
        )
    ).fetchall()

    source_rows = db.execute(
        text(
            """
            SELECT
                content_id,
                source,
                external_id,
                fetched_at
            FROM content_external_source
            """
        )
    ).fetchall()

    collections_by_content: dict[str, list[dict]] = {}
    for row in collection_rows:
        key = _hex_id(row.content_id)
        collections_by_content.setdefault(key, []).append(
            {
                "collection_id": _hex_id(row.collection_id),
                "format": row.format,
                "barcode": row.barcode,
                "box_set_barcode": row.box_set_barcode,
            }
        )

    sources_by_content: dict[str, list[dict]] = {}
    for row in source_rows:
        key = _hex_id(row.content_id)
        sources_by_content.setdefault(key, []).append(
            {
                "source": row.source,
                "external_id": row.external_id,
                "fetched_at": (
                    str(row.fetched_at)[:10] if row.fetched_at is not None else None
                ),
            }
        )

    result = []
    for row in content_rows:
        content_id = _hex_id(row.content_id)
        result.append(
            {
                "content_id": content_id,
                "title": row.title,
                "original_title": row.original_title,
                "first_release": (
                    str(row.first_release)[:10] if row.first_release is not None else None
                ),
                "runtime": row.runtime,
                "age_restriction": row.age_restriction,
                "watched_flag": bool(row.watched_flag),
                "temporary_flag": bool(row.temporary_flag),
                "content_type": row.content_type,
                "imdb_id": row.imdb_id,
                "collections": collections_by_content.get(content_id, []),
                "sources": sources_by_content.get(content_id, []),
            }
        )

    return result


def get_content_by_id(db: Session, content_id: str) -> dict | None:
    """Henter én content-rad (med samme struktur som list_content sine
    elementer), for detaljsiden i website_template_example v18.

    NB: production_company/produksjonsselskap finnes ikke som eget felt
    i content-tabellen ennå - kan legges til her senere hvis kolonnen
    opprettes i databasen.
    """

    try:
        raw_id = _parse_hex_id(content_id)
    except (ValueError, AttributeError):
        return None

    row = db.execute(
        text(
            """
            SELECT
                content_id,
                title,
                original_title,
                first_release,
                runtime,
                age_restriction,
                watched_flag,
                temporary_flag,
                content_type,
                imdb_id
            FROM content
            WHERE content_id = :content_id
            """
        ),
        {"content_id": raw_id},
    ).fetchone()

    if row is None:
        return None

    collection_rows = db.execute(
        text(
            """
            SELECT
                pc.collection_id AS collection_id,
                pc.format AS format,
                pc.barcode AS barcode,
                pc.box_set_barcode AS box_set_barcode
            FROM content_in_physical_collection cipc
            JOIN physical_collection pc ON pc.collection_id = cipc.collection_id
            WHERE cipc.content_id = :content_id
            """
        ),
        {"content_id": raw_id},
    ).fetchall()

    source_rows = db.execute(
        text(
            """
            SELECT source, external_id, fetched_at
            FROM content_external_source
            WHERE content_id = :content_id
            """
        ),
        {"content_id": raw_id},
    ).fetchall()

    return {
        "content_id": _hex_id(row.content_id),
        "title": row.title,
        "original_title": row.original_title,
        "first_release": (
            str(row.first_release)[:10] if row.first_release is not None else None
        ),
        "runtime": row.runtime,
        "age_restriction": row.age_restriction,
        "watched_flag": bool(row.watched_flag),
        "temporary_flag": bool(row.temporary_flag),
        "content_type": row.content_type,
        "imdb_id": row.imdb_id,
        "collections": [
            {
                "collection_id": _hex_id(r.collection_id),
                "format": r.format,
                "barcode": r.barcode,
                "box_set_barcode": r.box_set_barcode,
            }
            for r in collection_rows
        ],
        "sources": [
            {
                "source": r.source,
                "external_id": r.external_id,
                "fetched_at": str(r.fetched_at)[:10] if r.fetched_at is not None else None,
            }
            for r in source_rows
        ],
    }
