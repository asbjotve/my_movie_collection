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
                d.format AS format,
                d.label AS label
            FROM disc_in di
            JOIN disc d ON d.disc_id = di.disc_id
            WHERE di.collection_id IN :ids
            """
        ).bindparams(bindparam("ids", expanding=True)),
        {"ids": item_ids},
    ).fetchall()
    disc_by_collection = {row.collection_id: row for row in disc_rows}

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

    items = []
    for row in title_rows:
        item_collection_id = item_collection_by_content.get(row.content_id)
        disc = disc_by_collection.get(item_collection_id) if item_collection_id else None
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
                imdb_id,
                overview
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
        "overview": row.overview,
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

