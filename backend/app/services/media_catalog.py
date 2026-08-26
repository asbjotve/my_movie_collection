"""Read-only tjenester for media-katalogen (content + tilhørende tabeller).

Dette er starten på et API-lag som etter hvert skal erstatte den
direkte MySQL-tilgangen som website_template_example v15/v17 bruker
fra PHP (se app/media_db.py for tilkoblingen). Holdes bevisst enkelt
i første omgang - én spørring for content, én for fysiske utgaver, én
for eksterne kilder - og kan utvides med paginering/filtrering/sortering
senere etter hvert som mer av frontend flyttes over hit.
"""

import uuid

from sqlalchemy import bindparam, text
from sqlalchemy.orm import Session


def _hex_id(raw: bytes) -> str:
    return uuid.UUID(bytes=raw).hex.upper()


def _parse_hex_id(content_id: str) -> bytes:
    return uuid.UUID(hex=content_id).bytes


def _load_physical_copies(
    db: Session, collections: list[dict], raw_collection_ids: list[bytes]
) -> list[dict]:
    """Bygger en flat liste over fysiske eksemplarer ("Samlingsopplysninger")
    for en films samlinger: ett `physical_copy` = ett eksemplar = én
    oppføring i listen, uavhengig av om det er en enkeltplate eller et
    fleir-plate box-sett (box-settet vises da bare som ett eksemplar med
    flere plater, ikke som én oppføring pr. plate).

    Brukes kun av get_content_by_id (detaljsiden) - listevisningen
    trenger ikke dette dybdenivået.
    """

    if not raw_collection_ids:
        return []

    # En "box-samling" (samme collection_id delt av flere filmer via
    # content_in_physical_collection) representerer bare grupperingen
    # av boksen, ikke denne filmens egen fysiske plate - selve platen
    # ligger i en egen collection-rad spesifikt for denne filmen (som
    # regel med samme box_set_barcode). Slike "beholder"-samlinger
    # filtreres derfor bort her, ellers ville f.eks. "Politiskolen" i
    # en 3-filmsboks vist to oppføringer (boksen + filmens egen plate)
    # i stedet for én. Faller tilbake til å vise dem uansett hvis det
    # ikke finnes noen andre samlinger å vise (bedre enn en tom liste).
    title_count_rows = db.execute(
        text(
            """
            SELECT collection_id, COUNT(DISTINCT content_id) AS n_titles
            FROM content_in_physical_collection
            WHERE collection_id IN :ids
            GROUP BY collection_id
            """
        ).bindparams(bindparam("ids", expanding=True)),
        {"ids": raw_collection_ids},
    ).fetchall()
    container_ids = {row.collection_id for row in title_count_rows if row.n_titles > 1}
    filtered_ids = [cid for cid in raw_collection_ids if cid not in container_ids]
    if filtered_ids:
        raw_collection_ids = filtered_ids

    collection_by_hex = {c["collection_id"]: c for c in collections}

    copy_rows = db.execute(
        text(
            """
            SELECT copy_id, collection_id
            FROM physical_copy
            WHERE collection_id IN :ids
            ORDER BY collection_id, copy_id
            """
        ).bindparams(bindparam("ids", expanding=True)),
        {"ids": raw_collection_ids},
    ).fetchall()

    disc_stmt = text(
        """
        SELECT
            di.copy_id AS copy_id,
            di.collection_id AS collection_id,
            di.disc_id AS disc_id,
            di.box_set_disc_order AS box_set_disc_order,
            d.type_disc AS type_disc,
            d.format AS format,
            d.label AS label
        FROM disc_in di
        JOIN disc d ON d.disc_id = di.disc_id
        WHERE di.collection_id IN :ids
        ORDER BY di.collection_id, di.copy_id, di.box_set_disc_order
        """
    ).bindparams(bindparam("ids", expanding=True))
    disc_rows = db.execute(disc_stmt, {"ids": raw_collection_ids}).fetchall()

    disc_id_by_hex: dict[str, bytes] = {}
    discs_by_copy: dict[tuple[str, int], list[dict]] = {}
    for row in disc_rows:
        ck = _hex_id(row.collection_id)
        dk = _hex_id(row.disc_id)
        disc_id_by_hex[dk] = row.disc_id
        discs_by_copy.setdefault((ck, row.copy_id), []).append(
            {
                "disc_id": dk,
                "box_set_disc_order": row.box_set_disc_order,
                "type_disc": row.type_disc,
                "format": row.format,
                "label": row.label,
                "bonus_items": [],
            }
        )

    if disc_id_by_hex:
        bonus_stmt = text(
            """
            SELECT disc_id, seq_no, title, item_type, runtime_seconds, notes
            FROM disc_bonus_item
            WHERE disc_id IN :ids
            ORDER BY disc_id, seq_no
            """
        ).bindparams(bindparam("ids", expanding=True))
        bonus_rows = db.execute(
            bonus_stmt, {"ids": list(disc_id_by_hex.values())}
        ).fetchall()

        bonus_by_disc: dict[str, list[dict]] = {}
        for row in bonus_rows:
            dk = _hex_id(row.disc_id)
            bonus_by_disc.setdefault(dk, []).append(
                {
                    "title": row.title,
                    "item_type": row.item_type,
                    "runtime_seconds": row.runtime_seconds,
                    "notes": row.notes,
                }
            )

        for discs in discs_by_copy.values():
            for d in discs:
                d["bonus_items"] = bonus_by_disc.get(d["disc_id"], [])

    physical_copies: list[dict] = []
    for row in copy_rows:
        ck = _hex_id(row.collection_id)
        collection = collection_by_hex.get(ck, {})
        discs = discs_by_copy.get((ck, row.copy_id), [])
        physical_copies.append(
            {
                "collection_id": ck,
                "copy_id": row.copy_id,
                "format": collection.get("format"),
                "barcode": collection.get("barcode"),
                "box_set_barcode": collection.get("box_set_barcode"),
                "is_box_set": bool(collection.get("box_set_barcode")),
                "disc_count": len(discs),
                "discs": discs,
            }
        )

    return physical_copies


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

    collections = [
        {
            "collection_id": _hex_id(r.collection_id),
            "format": r.format,
            "barcode": r.barcode,
            "box_set_barcode": r.box_set_barcode,
        }
        for r in collection_rows
    ]
    physical_copies = _load_physical_copies(
        db, collections, [r.collection_id for r in collection_rows]
    )

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
        "collections": collections,
        "physical_copies": physical_copies,
        "sources": [
            {
                "source": r.source,
                "external_id": r.external_id,
                "fetched_at": str(r.fetched_at)[:10] if r.fetched_at is not None else None,
            }
            for r in source_rows
        ],
    }
