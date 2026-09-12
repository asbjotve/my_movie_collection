"""Tjenester for media-katalogen (content + tilhørende tabeller).

Dette er starten på et API-lag som etter hvert skal erstatte den
direkte MySQL-tilgangen som website_template_example v15/v17 bruker
fra PHP (se app/media_db.py for tilkoblingen). Holdes bevisst enkelt
i første omgang - én spørring for content, én for fysiske utgaver, én
for eksterne kilder - og kan utvides med paginering/filtrering/sortering
senere etter hvert som mer av frontend flyttes over hit.

Stort sett lesende (read-only), men update_content_external_source()
er et unntak - se den funksjonen for begrunnelse.
"""

import json
import re
import time
import uuid
from datetime import datetime, timezone

from sqlalchemy import bindparam, text
from sqlalchemy.orm import Session

from app.services.external_apis import (
    ExternalApiError,
    fetch_tmdb_details,
    fetch_tvdb_details,
)


class ContentExternalSourceError(ValueError):
    """Feil ved oppdatering av en content_external_source-rad."""

    def __init__(self, message: str, status_code: int = 400) -> None:
        super().__init__(message)
        self.status_code = status_code


def _hex_id(raw: bytes) -> str:
    return uuid.UUID(bytes=raw).hex.upper()


def _parse_hex_id(content_id: str) -> bytes:
    return uuid.UUID(hex=content_id).bytes


# Egen selvhostet cache-/proxy-tjeneste (se ~/program_packs/tmdb-cache)
# foran image.tmdb.org - forsinker/skjuler direkte avhengighet av TMDB
# sin bilde-CDN og cacher lokalt. Kun TMDB-bilder rutes om her; TVDB sin
# cover_image (data["image"]) er allerede en direkte, fullstendig URL og
# proxies ikke.
TMDB_IMAGE_HOST_PATTERN = re.compile(r"^https://image\.tmdb\.org/t/p/([^/]+)/(.+)$")
TMDB_IMAGE_PROXY_BASE_URL = "https://tmdb.media.plexcity.net/poster"


def _to_proxied_cover_image(cover_image: str | None) -> str | None:
    if not cover_image:
        return cover_image

    match = TMDB_IMAGE_HOST_PATTERN.match(cover_image)
    if not match:
        return cover_image

    size, file_name = match.groups()
    return f"{TMDB_IMAGE_PROXY_BASE_URL}/{size}/{file_name}"


def _load_physical_copies(
    db: Session,
    collections: list[dict],
    raw_collection_ids: list[bytes],
    raw_content_id: bytes,
    default_currency: str = "NOK",
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
            SELECT
                pc.copy_id AS copy_id,
                pc.collection_id AS collection_id,
                pc.owner_id AS owner_id,
                o.name AS owner_name,
                pc.store_id AS store_id,
                s.name AS store_name,
                pc.purchased_at AS purchased_at,
                pc.price AS price,
                pc.currency AS currency
            FROM physical_copy pc
            LEFT JOIN owner o ON o.owner_id = pc.owner_id
            LEFT JOIN store s ON s.store_id = pc.store_id
            WHERE pc.collection_id IN :ids
            ORDER BY pc.collection_id, pc.copy_id
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
            di.related_content_id AS related_content_id,
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
    disc_legacy_related: dict[str, bytes] = {}
    discs_by_copy: dict[tuple[str, int], list[dict]] = {}
    for row in disc_rows:
        ck = _hex_id(row.collection_id)
        dk = _hex_id(row.disc_id)
        disc_id_by_hex[dk] = row.disc_id
        if row.related_content_id is not None:
            disc_legacy_related[dk] = row.related_content_id
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

    if disc_id_by_hex:
        storage_stmt = text(
            """
            SELECT disc_id, number_in_storage
            FROM disc_in_storage
            WHERE disc_id IN :ids
            """
        ).bindparams(bindparam("ids", expanding=True))
        storage_rows = db.execute(
            storage_stmt, {"ids": list(disc_id_by_hex.values())}
        ).fetchall()
        storage_by_disc_hex = {_hex_id(row.disc_id): row.number_in_storage for row in storage_rows}

        for discs in discs_by_copy.values():
            for d in discs:
                d["number_in_storage"] = storage_by_disc_hex.get(d["disc_id"])

    # En delt boks-samling (content_in_physical_collection med flere
    # titler) inneholder platene til ALLE filmene i boksen. Uten
    # filtrering her ville f.eks. Wallace & Gromit (4 filmer, 2 plater
    # a 2 filmer) vist begge platene på alle 4 filmenes detaljside.
    # disc_related_content sier hvilke(n) film(er) hver plate faktisk
    # tilhører - plater uten noen rader der regnes som "hele boksen"
    # (f.eks. en ekstramaterialeplate) og vises for alle titler.
    related_content_by_disc: dict[str, set[str]] = {}
    if disc_id_by_hex:
        related_stmt = text(
            """
            SELECT disc_id, content_id
            FROM disc_related_content
            WHERE disc_id IN :ids
            """
        ).bindparams(bindparam("ids", expanding=True))
        related_rows = db.execute(
            related_stmt, {"ids": list(disc_id_by_hex.values())}
        ).fetchall()
        for row in related_rows:
            dk = _hex_id(row.disc_id)
            related_content_by_disc.setdefault(dk, set()).add(_hex_id(row.content_id))

    content_id_hex = _hex_id(raw_content_id)
    for key, discs in discs_by_copy.items():
        kept = []
        for d in discs:
            dk = d["disc_id"]
            related = related_content_by_disc.get(dk)
            if related is None and dk in disc_legacy_related:
                related = {_hex_id(disc_legacy_related[dk])}

            if not related or content_id_hex in related:
                kept.append(d)
        discs_by_copy[key] = kept

    physical_copies: list[dict] = []
    for row in copy_rows:
        ck = _hex_id(row.collection_id)
        collection = collection_by_hex.get(ck, {})
        discs = discs_by_copy.get((ck, row.copy_id), [])
        box_set_barcode = collection.get("box_set_barcode")
        physical_copies.append(
            {
                "collection_id": ck,
                "copy_id": row.copy_id,
                "format": collection.get("format"),
                "barcode": collection.get("barcode"),
                "box_set_barcode": box_set_barcode,
                "is_box_set": bool(box_set_barcode),
                "disc_count": len(discs),
                "discs": discs,
                "box_set_items": (
                    _load_box_set_items(db, box_set_barcode) if box_set_barcode else []
                ),
                # Eier-/kjøpsinformasjon (owner/store-tabellene) - se
                # "Samlingsopplysninger" (owner) og "Kjøpsinformasjon"
                # (store/purchased_at/price) på detaljsiden.
                "owner": row.owner_name,
                "store": row.store_name,
                "purchased_at": (
                    str(row.purchased_at) if row.purchased_at is not None else None
                ),
                "price": float(row.price) if row.price is not None else None,
                # Valuta er ikke obligatorisk pr. eksemplar - hvis ikke
                # satt, faller vi tilbake til default_currency (styrt av
                # /settings/default-currency, se app_settings_route.py).
                "currency": row.currency or default_currency,
            }
        )

    return physical_copies


def _load_box_set_items(db: Session, box_set_barcode: str) -> list[dict]:
    """Henter alle filmer/plater som hører til samme box-sett (samme
    `box_set_barcode`), til bruk i "vis innhold i boksen"-tabellen på
    detaljsiden. Beholder-samlingen (som binder titlene sammen, se
    `_load_physical_copies`) holdes utenfor - kun de film-spesifikke
    samlingene med egen plate telles med.
    """

    collection_rows = db.execute(
        text(
            """
            SELECT collection_id
            FROM physical_collection
            WHERE box_set_barcode = :box_set_barcode
            """
        ),
        {"box_set_barcode": box_set_barcode},
    ).fetchall()
    all_ids = [row.collection_id for row in collection_rows]
    if not all_ids:
        return []

    title_count_rows = db.execute(
        text(
            """
            SELECT collection_id, COUNT(DISTINCT content_id) AS n_titles
            FROM content_in_physical_collection
            WHERE collection_id IN :ids
            GROUP BY collection_id
            """
        ).bindparams(bindparam("ids", expanding=True)),
        {"ids": all_ids},
    ).fetchall()
    container_ids = {row.collection_id for row in title_count_rows if row.n_titles > 1}
    item_ids = [cid for cid in all_ids if cid not in container_ids] or all_ids

    # Rekkefølgen (box_set_title_sort) er registrert på beholder-
    # samlingens content_in_physical_collection-rader, ikke på filmens
    # egen plate-samling (der ligger alltid sort_order=1). Bruk derfor
    # beholderen som kilde til rekkefølge når den finnes.
    sort_source_ids = list(container_ids) if container_ids else item_ids
    title_rows = db.execute(
        text(
            """
            SELECT
                cipc.content_id AS content_id,
                cipc.box_set_title_sort AS sort_order,
                c.title AS title
            FROM content_in_physical_collection cipc
            JOIN content c ON c.content_id = cipc.content_id
            WHERE cipc.collection_id IN :ids
            """
        ).bindparams(bindparam("ids", expanding=True)),
        {"ids": sort_source_ids},
    ).fetchall()

    # Kobling fra content_id til filmens egen plate-samling (for å
    # finne disc-/lagringsinfo), hentet fra de film-spesifikke
    # samlingene (item_ids).
    content_to_item_collection = db.execute(
        text(
            """
            SELECT content_id, collection_id
            FROM content_in_physical_collection
            WHERE collection_id IN :ids
            """
        ).bindparams(bindparam("ids", expanding=True)),
        {"ids": item_ids},
    ).fetchall()
    item_collection_by_content = {
        row.content_id: row.collection_id for row in content_to_item_collection
    }

    disc_rows = db.execute(
        text(
            """
            SELECT
                di.collection_id AS collection_id,
                di.disc_id AS disc_id,
                di.related_content_id AS related_content_id,
                d.format AS format,
                d.label AS label
            FROM disc_in di
            JOIN disc d ON d.disc_id = di.disc_id
            WHERE di.collection_id IN :ids
            """
        ).bindparams(bindparam("ids", expanding=True)),
        {"ids": item_ids},
    ).fetchall()
    discs_by_collection: dict[bytes, list] = {}
    for row in disc_rows:
        discs_by_collection.setdefault(row.collection_id, []).append(row)

    storage_ids = [row.disc_id for row in disc_rows]
    storage_by_disc: dict[bytes, int] = {}
    if storage_ids:
        storage_rows = db.execute(
            text(
                """
                SELECT disc_id, number_in_storage
                FROM disc_in_storage
                WHERE disc_id IN :ids
                """
            ).bindparams(bindparam("ids", expanding=True)),
            {"ids": storage_ids},
        ).fetchall()
        storage_by_disc = {row.disc_id: row.number_in_storage for row in storage_rows}

    # Når flere titler deler samme collection_id (samme fysiske boks
    # uten egne innerkasser, f.eks. Wallace & Gromit), holder det ikke
    # å slå opp disc rett fra collection_id alene - da får alle
    # filmene i boksen den samme (tilfeldige) platen. disc_related_content
    # sier hvilken plate som faktisk tilhører hvilken film.
    related_content_by_disc: dict[bytes, set[bytes]] = {}
    if storage_ids:
        related_rows = db.execute(
            text(
                """
                SELECT disc_id, content_id
                FROM disc_related_content
                WHERE disc_id IN :ids
                """
            ).bindparams(bindparam("ids", expanding=True)),
            {"ids": storage_ids},
        ).fetchall()
        for row in related_rows:
            related_content_by_disc.setdefault(row.disc_id, set()).add(row.content_id)

    def _pick_disc(collection_id: bytes, content_id: bytes):
        for candidate in discs_by_collection.get(collection_id, []):
            related = related_content_by_disc.get(candidate.disc_id)
            if related is None and candidate.related_content_id is not None:
                related = {candidate.related_content_id}
            if not related or content_id in related:
                return candidate
        return None

    items = []
    for row in title_rows:
        item_collection_id = item_collection_by_content.get(row.content_id)
        disc = (
            _pick_disc(item_collection_id, row.content_id)
            if item_collection_id
            else None
        )
        items.append(
            {
                "content_id": _hex_id(row.content_id),
                "title": row.title,
                "sort_order": row.sort_order,
                "format": disc.format if disc else None,
                "disc_label": disc.label if disc else None,
                "number_in_storage": (
                    storage_by_disc.get(disc.disc_id) if disc else None
                ),
            }
        )

    items.sort(key=lambda i: (i["sort_order"] is None, i["sort_order"]))
    return items


def _extract_genres_and_cast(data: dict) -> tuple[list[str], list[str]]:
    """Henter sjangernavn og topp-billede skuespillere (sortert på
    TMDBs egen "order"-felt, maks 8 stk) ut av en lagret TMDB
    data_json-blob. Brukes både av get_collection_stats() og
    list_content()'s søk/facett-felter.
    """
    genres = [g.get("name") for g in (data.get("genres") or []) if g.get("name")]
    cast_entries = sorted(
        (data.get("credits", {}) or {}).get("cast") or [],
        key=lambda c: c.get("order", 999),
    )
    cast = [c.get("name") for c in cast_entries[:8] if c.get("name")]
    return genres, cast


def list_content(db: Session) -> list[dict]:
    """Henter alle content-rader, med tilhørende fysiske utgaver og
    eksterne kilder gruppert inn i hvert content-objekt.

    Inkluderer også genres/cast/release_year/decade (utledet fra TMDBs
    lagrede data_json, se _extract_genres_and_cast()) slik at
    frontend kan tilby fritekst-/facett-søk på tittel, skuespiller,
    sjanger og år - ikke bare tittel - uten et eget søke-endepunkt,
    siden hele listen uansett lastes ned samlet av index.php i dag.
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
                imdb_id,
                cover_image
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

    owner_rows = db.execute(
        text(
            """
            SELECT DISTINCT
                cipc.content_id AS content_id,
                o.name AS owner_name
            FROM content_in_physical_collection cipc
            JOIN physical_copy pcp ON pcp.collection_id = cipc.collection_id
            JOIN owner o ON o.owner_id = pcp.owner_id
            """
        )
    ).fetchall()

    tmdb_json_rows = db.execute(
        text(
            """
            SELECT content_id, data_json
            FROM content_external_source
            WHERE source = 'tmdb' AND data_json IS NOT NULL
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

    owners_by_content: dict[str, list[str]] = {}
    for row in owner_rows:
        key = _hex_id(row.content_id)
        owners_by_content.setdefault(key, []).append(row.owner_name)

    search_facets_by_content: dict[str, dict] = {}
    for row in tmdb_json_rows:
        key = _hex_id(row.content_id)
        try:
            data = json.loads(row.data_json)
        except (TypeError, ValueError):
            data = {}
        genres, cast = _extract_genres_and_cast(data)
        release_date = data.get("release_date")
        tmdb_year = (
            int(release_date[:4]) if release_date and release_date[:4].isdigit() else None
        )
        search_facets_by_content[key] = {
            "genres": genres,
            "cast": cast,
            "tmdb_year": tmdb_year,
        }

    result = []
    for row in content_rows:
        content_id = _hex_id(row.content_id)
        facets = search_facets_by_content.get(content_id, {})
        release_year = (
            row.first_release.year if row.first_release is not None else facets.get("tmdb_year")
        )
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
                "cover_image": _to_proxied_cover_image(row.cover_image),
                "collections": collections_by_content.get(content_id, []),
                "sources": sources_by_content.get(content_id, []),
                "owners": owners_by_content.get(content_id, []),
                "genres": facets.get("genres", []),
                "cast": facets.get("cast", []),
                "release_year": release_year,
                "decade": (release_year // 10) * 10 if release_year else None,
            }
        )

    return result


def get_content_by_id(db: Session, content_id: str, default_currency: str = "NOK") -> dict | None:
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
                c.content_id,
                c.title,
                c.original_title,
                c.first_release,
                c.runtime,
                c.age_restriction,
                c.watched_flag,
                c.temporary_flag,
                c.content_type,
                c.imdb_id,
                c.overview,
                c.cover_image,
                c.last_merged_source,
                c.last_merged_at,
                c.locked_fields
            FROM content c
            WHERE c.content_id = :content_id
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
        db, collections, [r.collection_id for r in collection_rows], raw_id, default_currency
    )

    # Alle filmgrupper denne filmen tilhører (f.eks. både "Tilbake til
    # fremtiden"-trilogien OG en "Filmer fra 1985"-gruppe samtidig) - en
    # film kan tilhøre 0, 1 eller flere grupper (many-to-many via
    # content_group_membership, se get_groups_for_content()).
    groups = get_groups_for_content(db, raw_id)

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
        "overview": row.overview,
        "cover_image": _to_proxied_cover_image(row.cover_image),
        "last_merged_source": row.last_merged_source,
        "last_merged_at": (
            str(row.last_merged_at)[:19] if row.last_merged_at is not None else None
        ),
        "locked_fields": (
            json.loads(row.locked_fields) if row.locked_fields else []
        ),
        "groups": groups,
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


def list_content_covers(db: Session, content_id: str) -> dict | None:
    """Lister alle tilgjengelige TMDB-postere for en content-rad, hentet
    fra den sist lagrede content_external_source.data_json (source='tmdb').

    Krever ingen nye TMDB-kall - fetch_tmdb_details() ber allerede om
    append_to_response=images, så data_json inneholder som regel et
    images.posters-array med flere posterbilder (ikke bare det ene som
    ligger i content.cover_image).

    Returnerer None hvis content_id ikke finnes. Returnerer en tom
    posters-liste (ikke None) hvis content finnes, men enten mangler en
    tmdb-kobling eller data_json ikke har noen postere.
    """

    try:
        raw_id = _parse_hex_id(content_id)
    except (ValueError, AttributeError):
        return None

    content_row = db.execute(
        text("SELECT content_id, cover_image FROM content WHERE content_id = :content_id"),
        {"content_id": raw_id},
    ).fetchone()

    if content_row is None:
        return None

    source_row = db.execute(
        text(
            """
            SELECT data_json
            FROM content_external_source
            WHERE content_id = :content_id AND source = 'tmdb'
            """
        ),
        {"content_id": raw_id},
    ).fetchone()

    current_cover = _to_proxied_cover_image(content_row.cover_image)

    posters: list[dict] = []
    if source_row is not None and source_row.data_json is not None:
        data = json.loads(source_row.data_json)
        for poster in data.get("images", {}).get("posters", []):
            file_path = poster.get("file_path")
            if not file_path:
                continue
            proxied = _to_proxied_cover_image(f"{TMDB_IMAGE_BASE_URL}{file_path}")
            posters.append(
                {
                    "file_path": file_path,
                    "cover_image": proxied,
                    "width": poster.get("width"),
                    "height": poster.get("height"),
                    "language": poster.get("iso_639_1"),
                    "is_current": proxied == current_cover,
                }
            )

    return {
        "content_id": content_id,
        "current_cover_image": current_cover,
        "posters": posters,
    }


def set_content_cover_image(db: Session, content_id: str, file_path: str) -> dict:
    """Setter content.cover_image til et av posterbildene fra
    list_content_covers() (identifisert via TMDB sin file_path, f.eks.
    '/abc123.jpg'), og legger 'cover_image' til i content.locked_fields
    slik at senere merge/backfill fra TMDB ikke overskriver det manuelle
    valget.

    Kaster ContentExternalSourceError (404) hvis content ikke finnes.
    """

    try:
        raw_id = _parse_hex_id(content_id)
    except (ValueError, AttributeError):
        raise ContentExternalSourceError("Ugyldig content_id", status_code=404)

    content_row = db.execute(
        text("SELECT content_id, locked_fields FROM content WHERE content_id = :content_id"),
        {"content_id": raw_id},
    ).fetchone()

    if content_row is None:
        raise ContentExternalSourceError(
            "Fant ikke content med denne IDen", status_code=404
        )

    if not file_path.startswith("/"):
        file_path = f"/{file_path}"

    cover_image = f"{TMDB_IMAGE_BASE_URL}{file_path}"

    locked_fields = set(
        json.loads(content_row.locked_fields) if content_row.locked_fields else []
    )
    locked_fields.add("cover_image")

    db.execute(
        text(
            """
            UPDATE content
            SET cover_image = :cover_image, locked_fields = :locked_fields
            WHERE content_id = :content_id
            """
        ),
        {
            "cover_image": cover_image,
            "locked_fields": json.dumps(sorted(locked_fields)),
            "content_id": raw_id,
        },
    )
    db.commit()

    return {
        "status": "ok",
        "content_id": content_id,
        "cover_image": _to_proxied_cover_image(cover_image),
    }


def update_content_fields(db: Session, content_id: str, fields: dict) -> dict:
    """Oppdaterer ett eller flere content-felter manuelt (penne-ikon-
    redigering på detaljsiden), og legger hvert redigerte felt til i
    content.locked_fields - slik at en senere "flett inn fra TMDB/TVDB"
    ikke overskriver den manuelle rettingen (samme mønster som
    set_content_cover_image() bruker for cover_image).

    `fields` skal komme fra
    ContentFieldUpdateRequest.model_dump(exclude_unset=True) i
    route-laget, slik at kun feltene brukeren faktisk endret er med her
    (også hvis en verdi bevisst settes til None/tom streng).

    Kaster ContentExternalSourceError (404) hvis content ikke finnes,
    (400) hvis et feltnavn i `fields` ikke er blant de redigerbare
    feltene (se EDITABLE_CONTENT_FIELDS).
    """

    if not fields:
        raise ContentExternalSourceError("Ingen felt å oppdatere", status_code=400)

    unknown_fields = set(fields) - EDITABLE_CONTENT_FIELDS
    if unknown_fields:
        raise ContentExternalSourceError(
            f"Feltene {sorted(unknown_fields)} kan ikke redigeres her",
            status_code=400,
        )

    try:
        raw_id = _parse_hex_id(content_id)
    except (ValueError, AttributeError):
        raise ContentExternalSourceError("Ugyldig content_id", status_code=404)

    content_row = db.execute(
        text("SELECT content_id, locked_fields FROM content WHERE content_id = :content_id"),
        {"content_id": raw_id},
    ).fetchone()

    if content_row is None:
        raise ContentExternalSourceError(
            "Fant ikke content med denne IDen", status_code=404
        )

    locked_fields = set(
        json.loads(content_row.locked_fields) if content_row.locked_fields else []
    )

    locked_fields.update(fields.keys())

    # first_release er en `date` her (fra Pydantic) - SQLAlchemy/DBAPI
    # håndterer det direkte mot content.first_release (timestamp), så
    # ingen manuell str()-konvertering trengs.
    set_clauses = [f"{name} = :{name}" for name in fields]
    params = dict(fields)
    params["content_id"] = raw_id
    params["locked_fields"] = json.dumps(sorted(locked_fields))

    db.execute(
        text(
            f"""
            UPDATE content
            SET {", ".join(set_clauses)}, locked_fields = :locked_fields
            WHERE content_id = :content_id
            """
        ),
        params,
    )
    db.commit()

    return {"status": "ok", "content_id": content_id, "updated_fields": sorted(fields.keys())}


def bulk_assign_group(db: Session, content_ids: list[str], group_name: str) -> dict:
    """Tildeler flere content-rader samme filmgruppe i ett kall - brukt
    av "velg-modus" i Mine filmer (index.php), hvor man kan
    huke av flere filmer og legge dem til samme gruppe i én operasjon i
    stedet for ett PATCH /media/content/{id}-kall pr. film.

    Gjør ÉN get-or-create mot movie_group (samme gruppe for alle valgte
    filmer), deretter én bulk UPDATE mot content. Ugyldige content_id-
    verdier hoppes stille over (samme "best effort"-tankegang som andre
    steder i UI-et der brukeren uansett ikke kan sende inn en id som
    ikke kommer fra en reell liste) - responsen forteller hvor mange
    rader som faktisk ble oppdatert.

    Rekkefølgen (group_sort_order) foreslås automatisk basert på
    first_release rett etter tildelingen (se
    _auto_assign_group_sort_order()) - brukeren kan justere videre med
    dra-og-slipp i filmgruppe-listen på detaljsiden (se
    reorder_group()).
    """

    raw_ids = []
    for content_id in content_ids:
        try:
            raw_ids.append(_parse_hex_id(content_id))
        except (ValueError, AttributeError):
            continue

    if not raw_ids:
        raise ContentExternalSourceError("Ingen gyldige content_id-er", status_code=400)

    group_id = _get_or_create_group_id(db, group_name)

    # En film kan nå tilhøre flere grupper samtidig (many-to-many via
    # content_group_membership), så dette er ADDITIVT - filmer som
    # allerede tilhører group_id fra før hoppes bare over (INSERT
    # IGNORE), de mister IKKE andre gruppetilhørigheter de måtte ha.
    result = db.execute(
        text(
            "INSERT IGNORE INTO content_group_membership (content_id, group_id) "
            "VALUES (:content_id, :group_id)"
        ),
        [{"content_id": raw_id, "group_id": group_id} for raw_id in raw_ids],
    )
    _auto_assign_group_sort_order(db, group_id, raw_ids)
    db.commit()

    return {"status": "ok", "group_id": group_id, "updated_count": result.rowcount}


def add_content_to_group(db: Session, content_id: str, group_name: str) -> dict:
    """Legger én content-rad til i en filmgruppe (via get-or-create på
    navn, se _get_or_create_group_id()) - brukt av "+ Legg til i
    gruppe"-knappen på detaljsiden. En film kan tilhøre flere grupper
    samtidig; kalles denne med en gruppe filmen allerede tilhører, er
    det en no-op (INSERT IGNORE - ingen feil, ingen duplikat-rad).

    Setter automatisk et startforslag til sort_order (se
    _auto_assign_group_sort_order()) - brukeren kan justere videre med
    dra-og-slipp (se reorder_group()).
    """

    try:
        raw_id = _parse_hex_id(content_id)
    except (ValueError, AttributeError):
        raise ContentExternalSourceError("Ugyldig content_id", status_code=404)

    content_row = db.execute(
        text("SELECT content_id FROM content WHERE content_id = :content_id"),
        {"content_id": raw_id},
    ).fetchone()
    if content_row is None:
        raise ContentExternalSourceError("Fant ikke content med denne IDen", status_code=404)

    group_id = _get_or_create_group_id(db, group_name)
    if group_id is None:
        raise ContentExternalSourceError("Ugyldig gruppenavn", status_code=400)

    db.execute(
        text(
            "INSERT IGNORE INTO content_group_membership (content_id, group_id) "
            "VALUES (:content_id, :group_id)"
        ),
        {"content_id": raw_id, "group_id": group_id},
    )
    _auto_assign_group_sort_order(db, group_id, [raw_id])
    db.commit()

    group_row = db.execute(
        text("SELECT name FROM movie_group WHERE group_id = :group_id"), {"group_id": group_id}
    ).fetchone()

    return {"status": "ok", "group_id": group_id, "group_name": group_row.name if group_row else group_name}


def remove_content_from_group(db: Session, content_id: str, group_id: int) -> dict:
    """Fjerner én content-rad fra én filmgruppe (fjerner kun raden i
    content_group_membership for akkurat denne kombinasjonen - filmen
    beholder alle sine ANDRE gruppetilhørigheter uendret). Brukt av
    "×"-knappen ved siden av hver gruppe på detaljsiden.

    Selve movie_group-raden slettes ALDRI her, selv om den skulle bli
    stående uten medlemmer - den kan gjenbrukes senere (samme
    get-or-create-mønster som ved oppretting).
    """

    try:
        raw_id = _parse_hex_id(content_id)
    except (ValueError, AttributeError):
        raise ContentExternalSourceError("Ugyldig content_id", status_code=404)

    result = db.execute(
        text(
            "DELETE FROM content_group_membership WHERE content_id = :content_id AND group_id = :group_id"
        ),
        {"content_id": raw_id, "group_id": group_id},
    )
    db.commit()

    if result.rowcount == 0:
        raise ContentExternalSourceError("Filmen tilhører ikke denne gruppen", status_code=404)

    return {"status": "ok", "group_id": group_id, "content_id": content_id}


def get_groups_for_content(db: Session, raw_id: bytes) -> list[dict]:
    """Alle filmgrupper en gitt film (raw_id = rå/binær content_id)
    tilhører, hver med sin egen liste over søsken-filmer i akkurat den
    gruppen (ekskludert filmen selv) - brukt av get_content_by_id() for
    "Andre filmer i denne filmgruppen"-seksjonen(e) på detaljsiden.

    Én film kan nå tilhøre 0, 1 eller flere grupper samtidig (many-to-
    many via content_group_membership) - i motsetning til den gamle
    content.group_id-kolonnen (fortsatt i databasen, men ikke lenger i
    bruk) som kun tillot én gruppe.

    Gruppene sorteres alfabetisk på navn (stabil, forutsigbar
    rekkefølge på detaljsiden uavhengig av når medlemskapet ble
    opprettet). Søsken-filmene innad i hver gruppe sorteres på
    sort_order (NULL sist), deretter first_release som fallback -
    samme logikk som tidligere.
    """

    membership_rows = db.execute(
        text(
            """
            SELECT cgm.group_id, mg.name AS group_name, cgm.sort_order
            FROM content_group_membership cgm
            JOIN movie_group mg ON mg.group_id = cgm.group_id
            WHERE cgm.content_id = :content_id
            ORDER BY mg.name ASC
            """
        ),
        {"content_id": raw_id},
    ).fetchall()

    groups = []
    for membership in membership_rows:
        sibling_rows = db.execute(
            text(
                """
                SELECT c.content_id, c.title, c.first_release, c.cover_image, cgm.sort_order
                FROM content_group_membership cgm
                JOIN content c ON c.content_id = cgm.content_id
                WHERE cgm.group_id = :group_id AND cgm.content_id != :content_id
                ORDER BY (cgm.sort_order IS NULL) ASC, cgm.sort_order ASC, c.first_release ASC
                """
            ),
            {"group_id": membership.group_id, "content_id": raw_id},
        ).fetchall()

        groups.append(
            {
                "group_id": membership.group_id,
                "group_name": membership.group_name,
                "sort_order": membership.sort_order,
                "group_movies": [
                    {
                        "content_id": _hex_id(r.content_id),
                        "title": r.title,
                        "first_release": (
                            str(r.first_release)[:10] if r.first_release is not None else None
                        ),
                        "cover_image": _to_proxied_cover_image(r.cover_image),
                        "group_sort_order": r.sort_order,
                    }
                    for r in sibling_rows
                ],
            }
        )

    return groups


def _auto_assign_group_sort_order(db: Session, group_id: int | None, content_ids: list) -> None:
    """Foreslår automatisk sort_order for filmer som nettopp er lagt
    til en filmgruppe (add_content_to_group() eller bulk_assign_group())
    og som IKKE allerede har en verdi der (f.eks. en film som ble
    fjernet og lagt til igjen).

    Rekkefølgen foreslås kronologisk etter first_release (eldste
    film = lavest tall), og legges til ETTER høyeste sort_order som
    allerede er i bruk i gruppen fra før - slik at en manuelt justert
    rekkefølge for eksisterende medlemmer aldri overskrives. Filmer
    uten first_release havner sist blant de som får nytt forslag.

    Dette er kun et startpunkt - brukeren kan justere videre med
    dra-og-slipp (se reorder_group()). Kalles fra add_content_to_group()
    og bulk_assign_group() rett etter at medlemskapet er satt inn;
    caller har ansvar for commit().

    `content_ids` skal være en liste med RÅ (binære) content_id-verdier
    (samme format som _parse_hex_id() returnerer), ikke hex-strenger.
    """

    if group_id is None or not content_ids:
        return

    max_row = db.execute(
        text("SELECT MAX(sort_order) AS max_order FROM content_group_membership WHERE group_id = :group_id"),
        {"group_id": group_id},
    ).fetchone()
    next_order = (max_row.max_order or 0) + 1

    rows = db.execute(
        text(
            """
            SELECT cgm.content_id, c.first_release
            FROM content_group_membership cgm
            JOIN content c ON c.content_id = cgm.content_id
            WHERE cgm.content_id IN :content_ids
              AND cgm.group_id = :group_id
              AND cgm.sort_order IS NULL
            ORDER BY (c.first_release IS NULL) ASC, c.first_release ASC
            """
        ).bindparams(bindparam("content_ids", expanding=True)),
        {"content_ids": content_ids, "group_id": group_id},
    ).fetchall()

    for offset, row in enumerate(rows):
        db.execute(
            text(
                "UPDATE content_group_membership SET sort_order = :order "
                "WHERE content_id = :content_id AND group_id = :group_id"
            ),
            {"order": next_order + offset, "content_id": row.content_id, "group_id": group_id},
        )


def reorder_group(db: Session, group_id: int, content_ids: list[str]) -> dict:
    """Setter sort_order = 1, 2, 3, ... i henhold til rekkefølgen på
    content_ids-listen - brukt av dra-og-slipp-sortering av
    filmgruppe-listen på detaljsiden (se renderGroupMovies() i
    detail.php).

    Kun content_group_membership-rader som faktisk tilhører group_id
    blir oppdatert; ukjente/ugyldige id-er eller id-er som tilhører en
    ANNEN gruppe (eller ingen gruppe i det hele tatt) hoppes stille
    over (samme "best effort"-mønster som bulk_assign_group()). Kaster
    ContentExternalSourceError (400) hvis ingen gyldige id-er ble
    oppdatert, ellers (404) hvis group_id ikke finnes i det hele tatt.
    """

    group_row = db.execute(
        text("SELECT group_id FROM movie_group WHERE group_id = :group_id"),
        {"group_id": group_id},
    ).fetchone()
    if group_row is None:
        raise ContentExternalSourceError("Fant ikke filmgruppen", status_code=404)

    raw_ids = []
    for content_id in content_ids:
        try:
            raw_ids.append(_parse_hex_id(content_id))
        except (ValueError, AttributeError):
            continue

    if not raw_ids:
        raise ContentExternalSourceError("Ingen gyldige content_id-er", status_code=400)

    updated_count = 0
    for order, raw_id in enumerate(raw_ids, start=1):
        result = db.execute(
            text(
                """
                UPDATE content_group_membership SET sort_order = :order
                WHERE content_id = :content_id AND group_id = :group_id
                """
            ),
            {"order": order, "content_id": raw_id, "group_id": group_id},
        )
        updated_count += result.rowcount
    db.commit()

    return {"status": "ok", "group_id": group_id, "updated_count": updated_count}


def set_content_field_lock(db: Session, content_id: str, field: str, locked: bool) -> dict:
    """Låser eller låser opp ett enkelt content-felt manuelt (hengelås-
    ikon på detaljsiden), UTEN å endre selve verdien i feltet.

    Dette er selve grunnen til at content.locked_fields finnes: et felt
    kan låses uten at man samtidig redigerer det (f.eks. hvis TMDB sin
    verdi tilfeldigvis allerede er riktig, men man likevel vil hindre
    at en senere "flett inn fra TMDB/TVDB" overskriver det).

    Kaster ContentExternalSourceError (400) hvis feltnavnet ikke er
    blant de redigerbare feltene (se EDITABLE_CONTENT_FIELDS), (404)
    hvis content ikke finnes.
    """

    if field not in EDITABLE_CONTENT_FIELDS:
        raise ContentExternalSourceError(
            f"Feltet '{field}' kan ikke låses/åpnes her", status_code=400
        )

    try:
        raw_id = _parse_hex_id(content_id)
    except (ValueError, AttributeError):
        raise ContentExternalSourceError("Ugyldig content_id", status_code=404)

    content_row = db.execute(
        text("SELECT content_id, locked_fields FROM content WHERE content_id = :content_id"),
        {"content_id": raw_id},
    ).fetchone()

    if content_row is None:
        raise ContentExternalSourceError(
            "Fant ikke content med denne IDen", status_code=404
        )

    locked_fields = set(
        json.loads(content_row.locked_fields) if content_row.locked_fields else []
    )
    if locked:
        locked_fields.add(field)
    else:
        locked_fields.discard(field)

    db.execute(
        text(
            """
            UPDATE content
            SET locked_fields = :locked_fields
            WHERE content_id = :content_id
            """
        ),
        {"content_id": raw_id, "locked_fields": json.dumps(sorted(locked_fields))},
    )
    db.commit()

    return {
        "status": "ok",
        "content_id": content_id,
        "field": field,
        "locked": locked,
        "locked_fields": sorted(locked_fields),
    }


def update_physical_copy_fields(
    db: Session, collection_id: str, copy_id: int, fields: dict
) -> dict:
    """Oppdaterer ett eller flere felter på ett fysisk eksemplar
    (penne-ikon-redigering på "Samlingsopplysninger"/"Kjøpsinformasjon"
    i detaljsiden).

    owner/store sendes som navn (fritekst) - finnes en rad med det
    navnet i owner-/store-tabellen brukes den, ellers opprettes en ny
    rad automatisk (get-or-create). Tom streng ("") tolkes som "fjern
    koblingen" (setter owner_id/store_id til NULL), IKKE som "ingen
    endring" - "ingen endring" oppnås ved å utelate feltet helt fra
    forespørselen.

    `fields` skal komme fra
    PhysicalCopyFieldUpdateRequest.model_dump(exclude_unset=True).

    Kaster ContentExternalSourceError (404) hvis eksemplaret ikke
    finnes.
    """

    if not fields:
        raise ContentExternalSourceError("Ingen felt å oppdatere", status_code=400)

    try:
        raw_collection_id = _parse_hex_id(collection_id)
    except (ValueError, AttributeError):
        raise ContentExternalSourceError("Ugyldig collection_id", status_code=404)

    copy_row = db.execute(
        text(
            """
            SELECT collection_id FROM physical_copy
            WHERE collection_id = :collection_id AND copy_id = :copy_id
            """
        ),
        {"collection_id": raw_collection_id, "copy_id": copy_id},
    ).fetchone()

    if copy_row is None:
        raise ContentExternalSourceError(
            "Fant ikke eksemplaret med denne collection_id/copy_id", status_code=404
        )

    set_clauses: list[str] = []
    params: dict = {"collection_id": raw_collection_id, "copy_id": copy_id}

    if "owner" in fields:
        set_clauses.append("owner_id = :owner_id")
        params["owner_id"] = _get_or_create_owner_id(db, fields["owner"])
    if "store" in fields:
        set_clauses.append("store_id = :store_id")
        params["store_id"] = _get_or_create_store_id(db, fields["store"])
    for plain_field in ("purchased_at", "price", "currency"):
        if plain_field in fields:
            set_clauses.append(f"{plain_field} = :{plain_field}")
            params[plain_field] = fields[plain_field]

    if not set_clauses:
        raise ContentExternalSourceError("Ingen felt å oppdatere", status_code=400)

    db.execute(
        text(
            f"""
            UPDATE physical_copy
            SET {", ".join(set_clauses)}
            WHERE collection_id = :collection_id AND copy_id = :copy_id
            """
        ),
        params,
    )
    db.commit()

    return {
        "status": "ok",
        "collection_id": collection_id,
        "copy_id": copy_id,
        "updated_fields": sorted(fields.keys()),
    }


def _get_or_create_owner_id(db: Session, name: str | None) -> int | None:
    name = (name or "").strip()
    if not name:
        return None

    existing = db.execute(
        text("SELECT owner_id FROM owner WHERE name = :name"), {"name": name}
    ).fetchone()
    if existing:
        return existing.owner_id

    result = db.execute(text("INSERT INTO owner (name) VALUES (:name)"), {"name": name})
    return result.lastrowid


def _get_or_create_store_id(db: Session, name: str | None) -> int | None:
    name = (name or "").strip()
    if not name:
        return None

    existing = db.execute(
        text("SELECT store_id FROM store WHERE name = :name"), {"name": name}
    ).fetchone()
    if existing:
        return existing.store_id

    result = db.execute(text("INSERT INTO store (name) VALUES (:name)"), {"name": name})
    return result.lastrowid


def _get_or_create_group_id(db: Session, name: str | None) -> int | None:
    """Samme get-or-create-mønster som owner/store, men for
    movie_group - en fri gruppering av filmer en content-rad kan
    tilhøre (f.eks. "Tilbake til fremtiden"-trilogien, eller bare
    filmer som naturlig hører sammen uten å være en formell
    "saga"/franchise), IKKE knyttet til noe fysisk eksemplar (derfor
    på content, ikke physical_copy).

    Oppslaget er case-insensitive (LOWER(name) = LOWER(:name)), i
    motsetning til owner/store - dette er for å unngå at "Olsenbanden"
    og "olsenbanden" ved en inkurie blir to forskjellige grupper bare
    fordi brukeren skrev inn navnet litt ulikt (frontend tilbyr uansett
    autofullføring, se GET /media/groups, men dette er et ekstra
    sikkerhetsnett).
    """
    name = (name or "").strip()
    if not name:
        return None

    existing = db.execute(
        text("SELECT group_id FROM movie_group WHERE LOWER(name) = LOWER(:name)"), {"name": name}
    ).fetchone()
    if existing:
        return existing.group_id

    result = db.execute(text("INSERT INTO movie_group (name) VALUES (:name)"), {"name": name})
    return result.lastrowid


def get_collection_stats(db: Session) -> dict:
    """Aggregerte statistikk-tall for hele samlingen: totalt antall
    filmer, fordeling per tiår/sjanger/format, og hvilke filmgrupper
    som har flest medlemmer. Brukes av statistikksiden (kun
    lesing - ingen skriving her).
    """
    total_movies = db.execute(text("SELECT COUNT(*) FROM content")).scalar() or 0

    format_rows = db.execute(
        text(
            """
            SELECT pc.format AS format, COUNT(*) AS count
            FROM content_in_physical_collection cipc
            JOIN physical_collection pc ON pc.collection_id = cipc.collection_id
            WHERE pc.format IS NOT NULL AND pc.format <> ''
            GROUP BY pc.format
            ORDER BY count DESC
            """
        )
    ).fetchall()
    by_format = [{"format": row.format, "count": row.count} for row in format_rows]

    group_rows = db.execute(
        text(
            """
            SELECT mg.name AS name, COUNT(*) AS count
            FROM content_group_membership cgm
            JOIN movie_group mg ON mg.group_id = cgm.group_id
            GROUP BY mg.group_id, mg.name
            ORDER BY count DESC, name ASC
            LIMIT 10
            """
        )
    ).fetchall()
    most_added_groups = [{"name": row.name, "count": row.count} for row in group_rows]

    # Sjanger finnes ikke som egen kolonne i content, og
    # content.first_release er NULL for de fleste rader i praksis (ser
    # ut til å bare bli fylt inn når man eksplisitt "fletter inn" TMDB-
    # data manuelt). Begge hentes derfor ut av TMDBs lagrede data_json
    # (genres/release_date), som allerede finnes for samtlige rader fra
    # tidligere "hent fra TMDB"-kall - med content.first_release som
    # foretrukket kilde til utgivelsesår der den faktisk er satt.
    # Gjøres i Python fremfor SQL JSON-funksjoner for enkelhets skyld -
    # noen hundre rader er uansett trivielt raskt å loope gjennom.
    first_release_by_id = {
        _hex_id(row.content_id): row.first_release
        for row in db.execute(text("SELECT content_id, first_release FROM content")).fetchall()
    }

    tmdb_rows = db.execute(
        text(
            """
            SELECT content_id, data_json
            FROM content_external_source
            WHERE source = 'tmdb' AND data_json IS NOT NULL
            """
        )
    ).fetchall()

    genre_counts: dict[str, int] = {}
    decade_counts: dict[int, int] = {}
    seen_content_ids: set[str] = set()

    for row in tmdb_rows:
        content_id = _hex_id(row.content_id)
        seen_content_ids.add(content_id)
        try:
            data = json.loads(row.data_json)
        except (TypeError, ValueError):
            data = {}

        for genre in data.get("genres") or []:
            name = genre.get("name")
            if name:
                genre_counts[name] = genre_counts.get(name, 0) + 1

        year = None
        first_release = first_release_by_id.get(content_id)
        if first_release is not None:
            year = first_release.year
        else:
            release_date = data.get("release_date")
            if release_date and release_date[:4].isdigit():
                year = int(release_date[:4])
        if year:
            decade_counts[(year // 10) * 10] = decade_counts.get((year // 10) * 10, 0) + 1

    # Content-rader uten noen TMDB-kilde i det hele tatt (f.eks. rent
    # TVDB-baserte serier) - bruk first_release direkte hvis den finnes.
    for content_id, first_release in first_release_by_id.items():
        if content_id not in seen_content_ids and first_release is not None:
            decade = (first_release.year // 10) * 10
            decade_counts[decade] = decade_counts.get(decade, 0) + 1

    by_genre = [
        {"genre": name, "count": count}
        for name, count in sorted(genre_counts.items(), key=lambda kv: (-kv[1], kv[0]))
    ]
    by_decade = [
        {"decade": decade, "count": count}
        for decade, count in sorted(decade_counts.items())
    ]

    return {
        "total_movies": total_movies,
        "by_decade": by_decade,
        "by_genre": by_genre,
        "by_format": by_format,
        "most_added_groups": most_added_groups,
    }


def list_group_names(db: Session) -> list[dict]:
    """Alle filmgrupper (kun group_id/name) - brukes til autofullføring
    i redigerings-popupen for "group"-feltet på detaljsiden (se
    GET /media/groups), slik at brukeren kan velge en eksisterende
    gruppe i stedet for å skrive inn navnet på nytt (og risikere
    stave-/kasus-varianter av samme gruppe, f.eks. "Olsenbanden" vs.
    "olsenbanden"). Skriver man inn et navn som ikke finnes i listen,
    opprettes automatisk en ny gruppe (se _get_or_create_group_id()).
    """
    rows = db.execute(text("SELECT group_id, name FROM movie_group ORDER BY name ASC")).fetchall()
    return [{"group_id": r.group_id, "name": r.name} for r in rows]


def update_content_external_source(
    db: Session,
    source: str,
    external_id: str,
) -> dict:
    """Oppdaterer data_json (+ fetched_at) på en *eksisterende*
    content_external_source-rad, funnet via source + external_id (f.eks.
    source='tmdb', external_id='137867') - ingen content_id trengs fra
    den som kaller endepunktet.

    Henter selv de fulle detaljene fra TMDB/TVDB (server-side) i stedet
    for å motta dem som payload - en full TMDB/TVDB-respons kan bli for
    stor/tungvint å sende via request body fra frontend.

    Oppretter bevisst IKKE en ny rad hvis (source, external_id) ikke
    matcher noen rad fra før - kaster en feil i stedet. Begrunnelse: en
    manglende rad her betyr enten at external_id/source er feil, eller
    at denne kilden aldri har blitt koblet på noen content-rad i
    utgangspunktet - i begge tilfeller er en stille "opprett den da" en
    dårligere løsning enn å varsle brukeren, siden det kan skjule en
    feil i innsendt data. Automatisk oppretting kan legges til senere
    hvis det viser seg å være ønskelig.
    """

    if source not in ("tmdb", "tvdb"):
        raise ContentExternalSourceError(
            f"Oppdatering fra kilde er ikke støttet for source={source!r}. "
            "Kun 'tmdb' og 'tvdb' kan hentes på nytt her.",
            status_code=400,
        )

    existing = db.execute(
        text(
            """
            SELECT ces.content_id, c.content_type
            FROM content_external_source ces
            JOIN content c ON c.content_id = ces.content_id
            WHERE ces.source = :source AND ces.external_id = :external_id
            """
        ),
        {"source": source, "external_id": external_id},
    ).fetchone()

    if existing is None:
        raise ContentExternalSourceError(
            f"Fant ingen content_external_source-rad for source={source!r} "
            f"og external_id={external_id!r}. Denne må finnes fra før - "
            "dette endepunktet oppretter ikke nye rader.",
            status_code=404,
        )

    content_type = existing.content_type or "movie"

    try:
        if source == "tmdb":
            data_json = fetch_tmdb_details(external_id, content_type)
        else:
            data_json = fetch_tvdb_details(external_id, content_type)
    except ExternalApiError as e:
        raise ContentExternalSourceError(str(e), status_code=e.status_code) from e

    db.execute(
        text(
            """
            UPDATE content_external_source
            SET data_json = :data_json, fetched_at = :fetched_at
            WHERE source = :source AND external_id = :external_id
            """
        ),
        {
            "source": source,
            "external_id": external_id,
            "data_json": json.dumps(data_json, ensure_ascii=False),
            "fetched_at": datetime.now(timezone.utc),
        },
    )
    db.commit()

    row = db.execute(
        text(
            """
            SELECT content_id, source, external_id, fetched_at
            FROM content_external_source
            WHERE source = :source AND external_id = :external_id
            """
        ),
        {"source": source, "external_id": external_id},
    ).fetchone()

    return {
        "content_id": _hex_id(row.content_id),
        "source": row.source,
        "external_id": row.external_id,
        "fetched_at": str(row.fetched_at)[:19] if row.fetched_at is not None else None,
    }


# --- Flett kilde-data inn i content ------------------------------------
#
# Feltene under er de som kan flettes inn i content-tabellen fra en
# kildes data_json. Et felt flettes kun inn hvis (a) kilden faktisk har
# en verdi for det, og (b) feltet ikke står i content.locked_fields
# (brukeren har da bevisst låst feltet mot overskriving).
MERGEABLE_CONTENT_FIELDS = {
    "title",
    "original_title",
    "first_release",
    "overview",
    "runtime",
    "cover_image",
    "age_restriction",
    "imdb_id",
}

# Felter som kan redigeres manuelt via PATCH /media/content/{id}
# (penne-ikon på detaljsiden) - samme som MERGEABLE_CONTENT_FIELDS,
# minus cover_image (eget endepunkt, se set_content_cover_image())
# pluss content_type (redigerbart manuelt, men kommer ikke fra
# TMDB/TVDB-fletting siden det sjelden endrer seg for en eksisterende
# rad). Filmgruppe-medlemskap (tidligere "group"/"group_sort_order"
# her) har egne dedikerte endepunkt siden en film nå kan tilhøre flere
# grupper samtidig - se add_content_to_group()/
# remove_content_from_group()/reorder_group().
EDITABLE_CONTENT_FIELDS = (MERGEABLE_CONTENT_FIELDS - {"cover_image"}) | {
    "content_type",
}

TMDB_IMAGE_BASE_URL = "https://image.tmdb.org/t/p/w500"

# Foretrukket rekkefølge av land for aldersgrense-sertifisering
# (norsk først, siden appen er norsk - amerikansk som fallback siden
# TMDB/TVDB nesten alltid har US-sertifisering).
_CERTIFICATION_COUNTRY_PRIORITY_TMDB = ("NO", "US")
_CERTIFICATION_COUNTRY_PRIORITY_TVDB = ("nor", "usa")


def _extract_tmdb_certification(data: dict) -> str | None:
    results = (data.get("release_dates") or {}).get("results") or []
    by_country = {r.get("iso_3166_1"): r for r in results}
    for country in _CERTIFICATION_COUNTRY_PRIORITY_TMDB:
        entry = by_country.get(country)
        if not entry:
            continue
        for release in entry.get("release_dates") or []:
            cert = (release.get("certification") or "").strip()
            if cert:
                return cert
    return None


def _map_tmdb_to_content_fields(data: dict) -> dict:
    fields: dict = {}

    if data.get("title"):
        fields["title"] = data["title"]
    if data.get("original_title"):
        fields["original_title"] = data["original_title"]
    if data.get("release_date"):
        fields["first_release"] = data["release_date"]
    if data.get("overview"):
        fields["overview"] = data["overview"]
    if data.get("runtime"):
        fields["runtime"] = data["runtime"]
    if data.get("poster_path"):
        fields["cover_image"] = f"{TMDB_IMAGE_BASE_URL}{data['poster_path']}"

    imdb_id = (data.get("external_ids") or {}).get("imdb_id")
    if imdb_id:
        fields["imdb_id"] = imdb_id

    certification = _extract_tmdb_certification(data)
    if certification:
        fields["age_restriction"] = certification

    return fields


def _extract_tvdb_overview(data: dict) -> str | None:
    """TVDB gir ikke overview-teksten direkte på toppnivå - selv med
    ?short=true er 'overviewTranslations' kun en liste med språkkoder.
    Selve teksten ligger i 'translations.overviewTranslations' (krever
    ?meta=translations - se fetch_tvdb_details() i external_apis.py).
    """
    translations = (data.get("translations") or {}).get("overviewTranslations") or []
    for entry in translations:
        if entry.get("language") == "eng" and entry.get("isPrimary"):
            return entry.get("overview")
    for entry in translations:
        if entry.get("language") == "eng":
            return entry.get("overview")
    return translations[0].get("overview") if translations else None


def _extract_tvdb_imdb_id(data: dict) -> str | None:
    for remote in data.get("remoteIds") or []:
        if remote.get("sourceName") == "IMDB":
            return remote.get("id")
    return None


def _extract_tvdb_certification(data: dict) -> str | None:
    ratings = data.get("contentRatings") or []
    by_country = {r.get("country"): r for r in ratings}
    for country in _CERTIFICATION_COUNTRY_PRIORITY_TVDB:
        entry = by_country.get(country)
        if entry and entry.get("name"):
            return entry["name"]
    return None


def _map_tvdb_to_content_fields(data: dict) -> dict:
    fields: dict = {}

    if data.get("name"):
        fields["title"] = data["name"]

    first_release = (data.get("first_release") or {}).get("date")
    if first_release:
        fields["first_release"] = first_release
    if data.get("runtime"):
        fields["runtime"] = data["runtime"]
    if data.get("image"):
        fields["cover_image"] = data["image"]

    overview = _extract_tvdb_overview(data)
    if overview:
        fields["overview"] = overview

    imdb_id = _extract_tvdb_imdb_id(data)
    if imdb_id:
        fields["imdb_id"] = imdb_id

    certification = _extract_tvdb_certification(data)
    if certification:
        fields["age_restriction"] = certification

    return fields


def merge_content_from_source(db: Session, source: str, external_id: str) -> dict:
    """Fletter den sist lagrede data_json-en for (source, external_id)
    inn i tilhørende content-rad.

    Leser IKKE på nytt fra TMDB/TVDB her - bruker det som allerede
    ligger i content_external_source.data_json (hentet av
    update_content_external_source()/"oppdater fra kilde"-knappen).
    Dette holder "hent fra kilde" og "flett inn i content" som to
    separate, eksplisitte handlinger - se tidligere diskusjon om at
    fletting skal være en bevisst, manuell handling per kilde.

    Felter i content.locked_fields (JSON-array med kolonnenavn) hoppes
    over - brukeren har da bevisst låst dem mot overskriving. Etter en
    vellykket fletting oppdateres last_merged_source/last_merged_at,
    uavhengig av om noen felt faktisk ble endret (så det alltid er
    synlig hvilken kilde/tidspunkt som sist ble forsøkt flettet inn).
    """

    if source not in ("tmdb", "tvdb"):
        raise ContentExternalSourceError(
            f"Fletting er ikke støttet for source={source!r}. "
            "Kun 'tmdb' og 'tvdb' kan flettes inn i content.",
            status_code=400,
        )

    source_row = db.execute(
        text(
            """
            SELECT content_id, data_json
            FROM content_external_source
            WHERE source = :source AND external_id = :external_id
            """
        ),
        {"source": source, "external_id": external_id},
    ).fetchone()

    if source_row is None:
        raise ContentExternalSourceError(
            f"Fant ingen content_external_source-rad for source={source!r} "
            f"og external_id={external_id!r}.",
            status_code=404,
        )

    if source_row.data_json is None:
        raise ContentExternalSourceError(
            "Denne kilden har ingen lagrede data ennå - hent (oppdater) "
            "fra kilden først.",
            status_code=409,
        )

    data = json.loads(source_row.data_json)
    mapped = (
        _map_tmdb_to_content_fields(data)
        if source == "tmdb"
        else _map_tvdb_to_content_fields(data)
    )

    content_row = db.execute(
        text("SELECT locked_fields FROM content WHERE content_id = :content_id"),
        {"content_id": source_row.content_id},
    ).fetchone()

    if content_row is None:
        raise ContentExternalSourceError(
            "Fant ikke content-raden denne kilden tilhører.", status_code=404
        )

    locked_fields = set(
        json.loads(content_row.locked_fields) if content_row.locked_fields else []
    )

    fields_to_update = {
        field: value
        for field, value in mapped.items()
        if field in MERGEABLE_CONTENT_FIELDS and field not in locked_fields
    }
    skipped_locked_fields = sorted(set(mapped.keys()) & locked_fields)

    if fields_to_update:
        set_clause = ", ".join(f"{field} = :{field}" for field in fields_to_update)
        params = dict(fields_to_update)
        params["content_id"] = source_row.content_id
        params["last_merged_source"] = source
        db.execute(
            text(
                f"""
                UPDATE content
                SET {set_clause},
                    last_merged_source = :last_merged_source,
                    last_merged_at = NOW()
                WHERE content_id = :content_id
                """
            ),
            params,
        )
    else:
        db.execute(
            text(
                """
                UPDATE content
                SET last_merged_source = :last_merged_source,
                    last_merged_at = NOW()
                WHERE content_id = :content_id
                """
            ),
            {"last_merged_source": source, "content_id": source_row.content_id},
        )

    db.commit()

    content_id = _hex_id(source_row.content_id)
    updated = get_content_by_id(db, content_id)

    return {
        **updated,
        "merged_from_source": source,
        "merged_fields": sorted(fields_to_update.keys()),
        "skipped_locked_fields": skipped_locked_fields,
    }


# --- Massiv etterutfylling av cover_image fra TMDB ---------------------

TMDB_MAX_REQUESTS_PER_SECOND = 35


def backfill_tmdb_cover_images(db: Session) -> dict:
    """Går gjennom alle content_external_source-rader med source='tmdb'
    der tilhørende content-rad mangler cover_image (NULL), henter fulle
    detaljer fra TMDB for hver og setter cover_image til posterbildet
    hvis TMDB faktisk har en poster_path.

    Rader der content allerede har cover_image hoppes over uten noe
    TMDB-kall (billig/rask filtrering i selve spørringen). Rader der
    'cover_image' står i content.locked_fields hoppes også over, av
    samme grunn som i merge_content_from_source().

    Overholder TMDB sin rate-grense (offisielt ~40 forespørsler/sekund)
    ved å strupe til maks TMDB_MAX_REQUESTS_PER_SECOND forespørsler pr.
    sekund (35, med litt margin), i enkle sekund-vise bolker.

    Commiter én rad om gangen (ikke én stor transaksjon til slutt) -
    ved en lang kjøring med mange rader vil da alt som er unnagjort
    fram til et evt. avbrudd/API-feil bli stående, i stedet for å gå
    tapt.
    """

    candidates = db.execute(
        text(
            """
            SELECT
                ces.content_id AS content_id,
                ces.external_id AS external_id,
                c.content_type AS content_type,
                c.locked_fields AS locked_fields
            FROM content_external_source ces
            JOIN content c ON c.content_id = ces.content_id
            WHERE ces.source = 'tmdb'
              AND c.cover_image IS NULL
            """
        )
    ).fetchall()

    total_candidates = len(candidates)
    updated = 0
    skipped_locked = 0
    skipped_no_poster = 0
    errors: list[dict] = []

    batch_start = time.monotonic()
    requests_in_batch = 0

    for row in candidates:
        locked_fields = set(
            json.loads(row.locked_fields) if row.locked_fields else []
        )
        if "cover_image" in locked_fields:
            skipped_locked += 1
            continue

        if requests_in_batch >= TMDB_MAX_REQUESTS_PER_SECOND:
            elapsed = time.monotonic() - batch_start
            if elapsed < 1.0:
                time.sleep(1.0 - elapsed)
            batch_start = time.monotonic()
            requests_in_batch = 0

        content_id_hex = _hex_id(row.content_id)
        try:
            data = fetch_tmdb_details(row.external_id, row.content_type or "movie")
        except ExternalApiError as e:
            errors.append(
                {
                    "content_id": content_id_hex,
                    "external_id": row.external_id,
                    "error": str(e),
                }
            )
            requests_in_batch += 1
            continue

        requests_in_batch += 1

        poster_path = data.get("poster_path")
        if not poster_path:
            skipped_no_poster += 1
            continue

        cover_image = f"{TMDB_IMAGE_BASE_URL}{poster_path}"

        db.execute(
            text(
                """
                UPDATE content
                SET cover_image = :cover_image
                WHERE content_id = :content_id AND cover_image IS NULL
                """
            ),
            {"cover_image": cover_image, "content_id": row.content_id},
        )
        db.commit()
        updated += 1

    return {
        "status": "ok",
        "total_candidates": total_candidates,
        "updated": updated,
        "skipped_locked": skipped_locked,
        "skipped_no_poster": skipped_no_poster,
        "errors": errors,
    }

