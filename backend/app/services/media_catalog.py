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
import threading
import time
import uuid
from datetime import datetime, timezone

from sqlalchemy import bindparam, text
from sqlalchemy.orm import Session

from app.media_db import MediaSessionLocal
from app.services.external_apis import (
    ExternalApiError,
    fetch_tmdb_details,
    fetch_tvdb_details,
    fetch_tmdb_season_episodes,
    fetch_tvdb_season_episodes,
)


class ContentExternalSourceError(ValueError):
    """Feil ved oppdatering av en content_external_source-rad."""

    def __init__(self, message: str, status_code: int = 400) -> None:
        super().__init__(message)
        self.status_code = status_code


class ContentExternalSourceConflict(ContentExternalSourceError):
    """En TMDB/TVDB-id som forsøkes satt via set_content_external_id()
    er allerede registrert på en ANNEN content-rad - sendes som 409 slik
    at frontend kan spørre brukeren om å slå de to radene sammen (se
    set_content_external_id())."""

    def __init__(
        self,
        message: str,
        conflict_content_id: str,
        conflict_title: str,
        physically_owned: bool,
        digitally_owned: bool,
    ) -> None:
        super().__init__(message, status_code=409)
        self.conflict_content_id = conflict_content_id
        self.conflict_title = conflict_title
        self.physically_owned = physically_owned
        self.digitally_owned = digitally_owned


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


def _format_episode_ranges(by_season: dict[int | None, list[int]]) -> str:
    """Komprimerer episodenumrene på én plate til kompakte rekkevidder,
    f.eks. [1,2,3,4,7,9,10] -> "E1-E4, E7, E9-E10" - en plate inneholder
    ofte sammenhengende episoder, men ikke alltid (bonusdisker, spesielt
    klippede samleutgivelser e.l. kan ha episoder spredt utover).
    Grupperes pr. sesong hvis platen (sjeldent) spenner over flere
    sesonger, f.eks. "S1: E11-E12 | S2: E1-E2"."""

    def ranges_for(numbers: list[int]) -> str:
        numbers = sorted(set(numbers))
        parts: list[str] = []
        start = prev = numbers[0]
        for n in numbers[1:]:
            if n == prev + 1:
                prev = n
                continue
            parts.append(f"E{start}" if start == prev else f"E{start}-E{prev}")
            start = prev = n
        parts.append(f"E{start}" if start == prev else f"E{start}-E{prev}")
        return ", ".join(parts)

    if len(by_season) == 1:
        return ranges_for(next(iter(by_season.values())))

    segments = []
    for season_number in sorted(by_season, key=lambda s: (s is None, s)):
        label = f"S{season_number}" if season_number is not None else "?"
        segments.append(f"{label}: {ranges_for(by_season[season_number])}")
    return " | ".join(segments)


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

    # En TV-boks med egne innerkasser pr. sesong (inner_case_ean) har
    # derimot bare ÉN tittel (selve serien) i ytterboks-samlingen - den
    # n_titles>1-sjekken over fanger den derfor ikke opp, og uten dette
    # ville ytterboksen (barcode=NULL, ingen egne plater - platene
    # ligger jo i sesongenes innerkasser) dukket opp som et eget, tomt
    # "eksemplar" ved siden av de reelle sesong-innerkassene. Fanges
    # opp her i stedet: en samling uten egen strekkode er en ren
    # beholder hvis samme content_id også finnes i en annen samling
    # (blant disse raw_collection_ids) som HAR en strekkode - altså at
    # det fins en mer spesifikk innerkasse et annet sted for samme
    # innhold.
    barcode_rows = db.execute(
        text(
            """
            SELECT collection_id, barcode
            FROM physical_collection
            WHERE collection_id IN :ids
            """
        ).bindparams(bindparam("ids", expanding=True)),
        {"ids": raw_collection_ids},
    ).fetchall()
    has_barcode_elsewhere = any(row.barcode for row in barcode_rows)
    if has_barcode_elsewhere:
        container_ids |= {row.collection_id for row in barcode_rows if not row.barcode}

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

    # For TV-serier (og framtidig mixed_boxset) ligger platene gruppert
    # pr. sesong via disc_contains_episode -> episode -> season. Henter
    # dette her slik at hvert eksemplar kan merkes med hvilken sesong
    # det faktisk representerer (f.eks. "Sesong 1" for innerkassen til
    # sesong 1) i stedet for å vises som et umerket, uforklart eksemplar.
    season_by_disc_hex: dict[str, set[tuple]] = {}
    if disc_id_by_hex:
        season_stmt = text(
            """
            SELECT DISTINCT
                dce.disc_id AS disc_id,
                sea.season_number AS season_number,
                sea.title AS season_title
            FROM disc_contains_episode dce
            JOIN episode ep ON ep.episode_id = dce.episode_id
            JOIN season sea ON sea.season_id = ep.season_id
            WHERE dce.disc_id IN :ids
            """
        ).bindparams(bindparam("ids", expanding=True))
        season_rows = db.execute(
            season_stmt, {"ids": list(disc_id_by_hex.values())}
        ).fetchall()
        for row in season_rows:
            season_by_disc_hex.setdefault(_hex_id(row.disc_id), set()).add(
                (row.season_number, row.season_title)
            )

    # Henter episodenumrene som faktisk ligger på hver enkelt plate
    # (samme disc_contains_episode-kobling som season_by_disc_hex over,
    # men her pr. plate i stedet for samlet pr. eksemplar) - brukes til
    # å vise f.eks. "S1: E1-E4, E7" i platetabellen på
    # "Samlingsopplysninger", se _format_episode_ranges().
    episodes_by_disc_hex: dict[str, dict[int | None, list[int]]] = {}
    if disc_id_by_hex:
        episode_stmt = text(
            """
            SELECT
                dce.disc_id AS disc_id,
                sea.season_number AS season_number,
                ep.episode_number AS episode_number
            FROM disc_contains_episode dce
            JOIN episode ep ON ep.episode_id = dce.episode_id
            JOIN season sea ON sea.season_id = ep.season_id
            WHERE dce.disc_id IN :ids
            ORDER BY dce.disc_id, sea.season_number, ep.episode_number
            """
        ).bindparams(bindparam("ids", expanding=True))
        episode_rows = db.execute(
            episode_stmt, {"ids": list(disc_id_by_hex.values())}
        ).fetchall()
        for row in episode_rows:
            if row.episode_number is None:
                continue
            dk = _hex_id(row.disc_id)
            by_season = episodes_by_disc_hex.setdefault(dk, {})
            by_season.setdefault(row.season_number, []).append(row.episode_number)

        for discs in discs_by_copy.values():
            for d in discs:
                by_season = episodes_by_disc_hex.get(d["disc_id"])
                d["episode_summary"] = (
                    _format_episode_ranges(by_season) if by_season else None
                )

    physical_copies: list[dict] = []
    for row in copy_rows:
        ck = _hex_id(row.collection_id)
        collection = collection_by_hex.get(ck, {})
        discs = discs_by_copy.get((ck, row.copy_id), [])
        box_set_barcode = collection.get("box_set_barcode")

        # Hvis ALLE platene i dette eksemplaret peker på episoder fra
        # én og samme sesong, merkes hele eksemplaret med den sesongen
        # (typisk en sesongs egen innerkasse). Spenner eksemplaret over
        # flere sesonger (eller ingen episodekobling i det hele tatt),
        # lar vi det stå umerket.
        seasons_in_copy: set[tuple] = set()
        for d in discs:
            seasons_in_copy |= season_by_disc_hex.get(d["disc_id"], set())
        season_number, season_title = (
            next(iter(seasons_in_copy)) if len(seasons_in_copy) == 1 else (None, None)
        )

        physical_copies.append(
            {
                "collection_id": ck,
                "copy_id": row.copy_id,
                "format": collection.get("format"),
                "barcode": collection.get("barcode"),
                "box_set_barcode": box_set_barcode,
                "is_box_set": bool(box_set_barcode),
                "season_number": season_number,
                "season_title": season_title,
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


def _compute_search_facets(data: dict) -> tuple[list[str], list[str], int | None]:
    """Som _extract_genres_and_cast(), men legger i tillegg til
    TMDBs release_date-år - selve verdiene skrevet til
    content_external_source.facet_genres/facet_cast/facet_release_year
    (kun for source='tmdb') hver gang en TMDB data_json lagres/
    oppdateres (se physical_collection_import.py og
    update_content_external_source() under), slik at list_content()
    kan lese disse små, ferdigberegnede feltene fremfor å måtte parse
    hele (ofte >100KB) data_json-blobben på nytt ved hvert kall.
    """
    genres, cast = _extract_genres_and_cast(data)
    # Filmer har "release_date", serier har "first_air_date" - se
    # _extract_tmdb_title()/_map_tmdb_to_content_fields() for samme
    # film/serie-distinksjon.
    release_date = data.get("release_date") or data.get("first_air_date")
    release_year = (
        int(release_date[:4]) if release_date and release_date[:4].isdigit() else None
    )
    return genres, cast, release_year


def _parse_facet_genres(raw_value) -> list[str]:
    """facet_genres/facet_cast-kolonnene er MySQL JSON, men
    db.execute(text(...)) (brukt i flere steder i denne filen) går
    forbi SQLAlchemys type-decoding, så driveren returnerer verdien
    som en rå JSON-streng (f.eks. '["Action", "Thriller"]'), ikke en
    ferdig parset liste. Brukes både av list_content() (for å gi
    frontend ferdige arrays til sjanger/skuespiller-facettene - se
    renderMineFilmerGenreChips()/getFilteredMineFilmer() i index.php)
    og get_similar_content() (som trenger faktiske sjangernavn for å
    telle eksakt overlapp). Navnet er historisk (opprinnelig kun for
    genres) - funksjonen er generisk og brukes nå for begge kolonnene.
    """
    if not raw_value:
        return []
    if isinstance(raw_value, list):
        return raw_value
    try:
        parsed = json.loads(raw_value)
    except (TypeError, ValueError):
        return []
    return parsed if isinstance(parsed, list) else []


def list_content(db: Session) -> list[dict]:
    """Henter alle content-rader, med tilhørende fysiske utgaver og
    eksterne kilder gruppert inn i hvert content-objekt.

    Inkluderer også genres/cast/release_year/decade (lest fra de
    forhåndsberegnede facet_genres/facet_cast/facet_release_year-
    kolonnene på content_external_source, se _compute_search_facets())
    slik at frontend kan tilby fritekst-/facett-søk på tittel,
    skuespiller, sjanger og år - ikke bare tittel - uten et eget
    søke-endepunkt, siden hele listen uansett lastes ned samlet av
    index.php i dag. Disse kolonnene leses her i stedet for å parse
    den fulle (ofte >100KB) TMDB data_json-blobben på nytt ved hvert
    kall - se migrasjon 740c10b9db7b for bakgrunnen/målingene.
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
            SELECT content_id, facet_genres, facet_cast, facet_release_year
            FROM content_external_source
            WHERE source = 'tmdb'
              AND (facet_genres IS NOT NULL OR facet_cast IS NOT NULL
                   OR facet_release_year IS NOT NULL)
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
        search_facets_by_content[key] = {
            # facet_genres/facet_cast kommer her som rå JSON-strenger
            # (se _parse_facet_genres()-docstringen) siden denne spørringen
            # bruker db.execute(text(...)) og dermed går forbi SQLAlchemys
            # type-decoding. Må parses FØR de sendes til frontend, ellers
            # blir t.d. hele '["Action", "Thriller"]'-strengen én eneste
            # "sjanger"-chip i stedet for to separate (Action/Thriller) -
            # se index.php sin renderMineFilmerGenreChips()/
            # getFilteredMineFilmer() som forventer ferdige arrays.
            "genres": _parse_facet_genres(row.facet_genres),
            "cast": _parse_facet_genres(row.facet_cast),
            "tmdb_year": row.facet_release_year,
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


def get_similar_content(db: Session, content_id: str, limit: int = 10) -> list[dict]:
    """"Lignende filmer"-forslag til detaljsiden: andre filmer i
    samlingen som deler minst én sjanger med denne filmen, sortert på
    flest delte sjangre (deretter nyeste utgivelsesår som
    tiebreaker).

    Leser sjangre fra de forhåndsberegnede facet_genres-kolonnene på
    content_external_source (samme cache som list_content() bruker -
    se _compute_search_facets() og migrasjon 740c10b9db7b), ikke fra
    den fulle data_json-blobben, og gjør selve overlapp-beregningen i
    Python - samlingen er uansett bare noen hundre filmer, så dette er
    billigere og enklere enn et JSON-spørring-uttrykk i SQL.

    Filmer som allerede er i samme filmgruppe som denne (se
    get_groups_for_content()) ekskluderes med vilje - de vises allerede
    i en egen "Andre filmer i denne filmgruppen"-seksjon på
    detaljsiden, så vi unngår å liste dem to ganger.

    Returnerer en tom liste hvis filmen ikke har noen lagrede sjangre
    ennå (f.eks. aldri koblet mot TMDB).
    """
    try:
        raw_id = _parse_hex_id(content_id)
    except (ValueError, AttributeError):
        return []

    current_row = db.execute(
        text(
            """
            SELECT facet_genres
            FROM content_external_source
            WHERE content_id = :content_id AND facet_genres IS NOT NULL
            ORDER BY (source = 'tmdb') DESC
            LIMIT 1
            """
        ),
        {"content_id": raw_id},
    ).fetchone()

    current_genres = set(_parse_facet_genres(current_row.facet_genres)) if current_row else set()
    if not current_genres:
        return []

    same_group_ids = {
        row.content_id
        for row in db.execute(
            text(
                """
                SELECT cgm2.content_id
                FROM content_group_membership cgm1
                JOIN content_group_membership cgm2 ON cgm2.group_id = cgm1.group_id
                WHERE cgm1.content_id = :content_id
                """
            ),
            {"content_id": raw_id},
        ).fetchall()
    }

    candidate_rows = db.execute(
        text(
            """
            SELECT content_id, facet_genres
            FROM content_external_source
            WHERE source = 'tmdb' AND facet_genres IS NOT NULL AND content_id != :content_id
            """
        ),
        {"content_id": raw_id},
    ).fetchall()

    best_overlap: dict[bytes, int] = {}
    for row in candidate_rows:
        if row.content_id in same_group_ids:
            continue
        overlap = len(current_genres & set(_parse_facet_genres(row.facet_genres)))
        if overlap > 0 and overlap > best_overlap.get(row.content_id, 0):
            best_overlap[row.content_id] = overlap

    if not best_overlap:
        return []

    # Henter litt flere kandidater enn limit (sortert på overlapp), i
    # tilfelle noen av dem mangler tittel/cover under - usannsynlig,
    # men defensivt. Selve begrensningen til `limit` skjer til slutt.
    top_ids = [cid for cid, _ in sorted(best_overlap.items(), key=lambda kv: kv[1], reverse=True)[: limit * 2]]

    content_rows = db.execute(
        text(
            """
            SELECT content_id, title, first_release, cover_image, content_type
            FROM content
            WHERE content_id IN :ids
            """
        ).bindparams(bindparam("ids", expanding=True)),
        {"ids": top_ids},
    ).fetchall()
    content_by_id = {row.content_id: row for row in content_rows}

    result = []
    for cid in top_ids:
        row = content_by_id.get(cid)
        if row is None:
            continue
        result.append(
            {
                "content_id": _hex_id(cid),
                "title": row.title,
                "first_release": (
                    str(row.first_release)[:10] if row.first_release is not None else None
                ),
                "cover_image": _to_proxied_cover_image(row.cover_image),
                "content_type": row.content_type,
                "shared_genre_count": best_overlap[cid],
            }
        )
        if len(result) >= limit:
            break

    return result


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
            release_date = data.get("release_date") or data.get("first_air_date")
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


def get_data_health_issues(db: Session) -> dict:
    """"Health check" for katalogen: flagger content-rader som mangler
    cover, overview, runtime, eller en gyldig TMDB/TVDB-kilde.

    Merk: content.overview og content.runtime er legacy-kolonner som
    så godt som alltid er tomme i praksis (samme situasjon som
    first_release, se get_collection_stats()) - selve dataene ligger i
    TMDBs lagrede data_json. Disse feltene sjekkes derfor der, ikke i
    content-tabellen direkte, ellers ville nesten alle rader (falskt)
    bli flagget som "mangler overview/runtime".

    content.imdb_id sjekkes derimot direkte, siden den kolonnen faktisk
    brukes/fylles inn i praksis.
    """
    content_rows = db.execute(
        text("SELECT content_id, title, cover_image, imdb_id FROM content")
    ).fetchall()

    tmdb_rows = db.execute(
        text(
            """
            SELECT content_id, data_json
            FROM content_external_source
            WHERE source = 'tmdb' AND data_json IS NOT NULL
            """
        )
    ).fetchall()
    tmdb_by_id: dict[str, dict] = {}
    for row in tmdb_rows:
        try:
            tmdb_by_id[_hex_id(row.content_id)] = json.loads(row.data_json)
        except (TypeError, ValueError):
            tmdb_by_id[_hex_id(row.content_id)] = {}

    external_source_ids = {
        _hex_id(row.content_id)
        for row in db.execute(
            text(
                "SELECT DISTINCT content_id FROM content_external_source "
                "WHERE source IN ('tmdb', 'tvdb')"
            )
        ).fetchall()
    }

    issue_counts: dict[str, int] = {
        "missing_cover": 0,
        "missing_overview": 0,
        "missing_runtime": 0,
        "missing_imdb_id": 0,
        "missing_external_source": 0,
    }
    flagged: list[dict] = []

    for row in content_rows:
        content_id = _hex_id(row.content_id)
        issues: list[str] = []

        if not row.cover_image:
            issues.append("missing_cover")
        if not row.imdb_id:
            issues.append("missing_imdb_id")

        if content_id not in external_source_ids:
            issues.append("missing_external_source")
        else:
            tmdb_data = tmdb_by_id.get(content_id)
            if tmdb_data is not None:
                if not _extract_tmdb_overview(tmdb_data):
                    issues.append("missing_overview")
                if not tmdb_data.get("runtime"):
                    issues.append("missing_runtime")

        if issues:
            for issue in issues:
                issue_counts[issue] += 1
            flagged.append(
                {
                    "content_id": content_id,
                    "title": row.title,
                    "issues": issues,
                }
            )

    return {
        "total_movies": len(content_rows),
        "flagged_count": len(flagged),
        "issue_counts": issue_counts,
        "items": flagged,
    }


def bulk_refresh_tmdb_for_flagged_content(db: Session) -> dict:
    """Bulk-variant av de to eksisterende enkelt-handlingene "hent fra
    kilde" (update_content_external_source()) + "flett inn i content"
    (merge_content_from_source()) - kjører begge, for alle content-rader
    som er flagget av get_data_health_issues() med et TMDB-relevant
    problem (mangler cover/overview/runtime/imdb_id) OG som faktisk har
    en TMDB-kobling. Content-rader som mangler TMDB-kobling i det hele
    tatt (missing_external_source) hoppes over her - det krever et nytt
    søk/import, ikke en refresh av en eksisterende kobling.

    Tanken er at TMDB kan ha fått data siden sist (f.eks. en overview
    som manglet da filmen først ble lagt til), så en frisk henting kan
    løse flagget uten at brukeren må gå gjennom alle filmene manuelt én
    og én.

    NB: kalles kun fra _run_bulk_refresh_tmdb_job() (i en egen
    bakgrunnstråd, med sin egen DB-sesjon) - IKKE direkte fra en
    HTTP-rute lenger. Årsak: TMDB-kallenes reelle nettverks-latens
    (ikke selve rate-limiten på TMDB_MAX_REQUESTS_PER_SECOND) viste seg
    å dominere - i praksis ca. 1 rad per 2-3 sekunder, altså langt
    tregere enn rate-limiten tillater. Med ~600-700 kandidater blir
    total kjøretid da 20-30+ minutter, godt over både nginx sin
    proxy_read_timeout og PHPs execution-tid - et vanlig
    request/response-endepunkt ville alltid timet ut underveis (bekreftet
    i praksis: jobben fortsatte å kjøre server-side lenge etter at
    nettleseren fikk en 504 fra proxyen). Se
    start_bulk_refresh_tmdb_job()/get_bulk_refresh_tmdb_status() for
    hvordan dette eksponeres til frontend nå (start + polling av status
    i stedet for å vente på ett langt svar).

    Committer (både refresh og merge) én rad om gangen, slik at et evt.
    avbrudd underveis ikke mister alt som er unnagjort så langt.
    """
    content_rows = db.execute(
        text("SELECT content_id, cover_image, imdb_id FROM content")
    ).fetchall()
    content_by_id = {_hex_id(row.content_id): row for row in content_rows}

    tmdb_rows = db.execute(
        text(
            """
            SELECT content_id, external_id, data_json
            FROM content_external_source
            WHERE source = 'tmdb'
            """
        )
    ).fetchall()

    candidates = []
    for row in tmdb_rows:
        content_id = _hex_id(row.content_id)
        content_info = content_by_id.get(content_id)
        if content_info is None:
            continue
        try:
            data = json.loads(row.data_json) if row.data_json else {}
        except (TypeError, ValueError):
            data = {}
        needs_refresh = (
            not content_info.cover_image
            or not content_info.imdb_id
            or not _extract_tmdb_overview(data)
            or not data.get("runtime")
        )
        if needs_refresh:
            candidates.append((content_id, row.external_id))

    total_candidates = len(candidates)
    _bulk_refresh_tmdb_job_state.update(
        {
            "total_candidates": total_candidates,
            "processed": 0,
            "refreshed": 0,
        }
    )

    refreshed = 0
    errors: list[dict] = []

    batch_start = time.monotonic()
    requests_in_batch = 0

    for content_id, external_id in candidates:
        if requests_in_batch >= TMDB_MAX_REQUESTS_PER_SECOND:
            elapsed = time.monotonic() - batch_start
            if elapsed < 1.0:
                time.sleep(1.0 - elapsed)
            batch_start = time.monotonic()
            requests_in_batch = 0

        try:
            update_content_external_source(db, "tmdb", external_id)
            merge_content_from_source(db, "tmdb", external_id)
            refreshed += 1
        except ContentExternalSourceError as e:
            db.rollback()
            errors.append(
                {
                    "content_id": content_id,
                    "external_id": external_id,
                    "error": str(e),
                }
            )
        except Exception as e:  # pylint: disable=broad-except
            # Uventet feil (f.eks. nettverk/DB) skal ikke stoppe resten
            # av batchen - logg raden som feilet og fortsett med neste.
            db.rollback()
            errors.append(
                {
                    "content_id": content_id,
                    "external_id": external_id,
                    "error": f"Uventet feil: {e}",
                }
            )
        requests_in_batch += 1
        _bulk_refresh_tmdb_job_state["processed"] += 1
        _bulk_refresh_tmdb_job_state["refreshed"] = refreshed

    return {
        "total_candidates": total_candidates,
        "refreshed": refreshed,
        "errors": errors,
    }


# In-memory status for den ene bulk-TMDB-refresh-jobben som kan kjøre om
# gangen (én pm2/uvicorn-prosess, ingen flere arbeidere - se
# ecosystem.config.js - så et modul-nivå objekt er nok, ingen egen
# jobbkø/tabell trengs for dette). Beskyttet av _bulk_refresh_tmdb_lock
# siden både HTTP-tråden (start/status) og bakgrunnstråden (selve
# jobben) leser/skriver den samtidig.
_bulk_refresh_tmdb_lock = threading.Lock()
_bulk_refresh_tmdb_job_state: dict = {
    "running": False,
    "total_candidates": 0,
    "processed": 0,
    "refreshed": 0,
    "errors": [],
    "started_at": None,
    "finished_at": None,
    "fatal_error": None,
}


def _run_bulk_refresh_tmdb_job() -> None:
    """Selve jobb-kjøringen i en egen bakgrunnstråd - lager sin egen
    DB-sesjon (MediaSessionLocal) siden den opprinnelige
    request-scopede sesjonen (fra get_media_db()) lukkes idet
    HTTP-requesten som startet jobben returnerer.
    """
    db = MediaSessionLocal()
    try:
        result = bulk_refresh_tmdb_for_flagged_content(db)
        with _bulk_refresh_tmdb_lock:
            _bulk_refresh_tmdb_job_state["errors"] = result["errors"]
            _bulk_refresh_tmdb_job_state["refreshed"] = result["refreshed"]
            _bulk_refresh_tmdb_job_state["total_candidates"] = result["total_candidates"]
    except Exception as e:  # pylint: disable=broad-except
        with _bulk_refresh_tmdb_lock:
            _bulk_refresh_tmdb_job_state["fatal_error"] = str(e)
    finally:
        db.close()
        with _bulk_refresh_tmdb_lock:
            _bulk_refresh_tmdb_job_state["running"] = False
            _bulk_refresh_tmdb_job_state["finished_at"] = datetime.now(timezone.utc).isoformat()


def start_bulk_refresh_tmdb_job() -> dict:
    """Starter bulk-TMDB-refreshen i en bakgrunnstråd og returnerer med
    en gang (i stedet for å vente på at hele jobben - som kan ta
    20-30+ minutter, se bulk_refresh_tmdb_for_flagged_content() -
    fullfører). Frontend poller get_bulk_refresh_tmdb_status() for
    fremdrift. Hvis en jobb allerede kjører, startes ikke en ny (kun én
    om gangen) - status returneres uansett slik at knappen kan vise
    fremdriften på den som allerede går.
    """
    with _bulk_refresh_tmdb_lock:
        if _bulk_refresh_tmdb_job_state["running"]:
            return {"started": False, "already_running": True, **_bulk_refresh_tmdb_job_state}

        _bulk_refresh_tmdb_job_state.update(
            {
                "running": True,
                "total_candidates": 0,
                "processed": 0,
                "refreshed": 0,
                "errors": [],
                "started_at": datetime.now(timezone.utc).isoformat(),
                "finished_at": None,
                "fatal_error": None,
            }
        )

    thread = threading.Thread(target=_run_bulk_refresh_tmdb_job, daemon=True)
    thread.start()

    return {"started": True, "already_running": False, **_bulk_refresh_tmdb_job_state}


def get_bulk_refresh_tmdb_status() -> dict:
    """Nåværende status for bulk-TMDB-refresh-jobben (se
    start_bulk_refresh_tmdb_job()) - brukes til polling fra
    health_check.php mens jobben kjører."""
    with _bulk_refresh_tmdb_lock:
        return dict(_bulk_refresh_tmdb_job_state)


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


# Tabeller (utenom content_external_source, som håndteres separat siden
# den har source i primærnøkkelen) som kan peke på en content-rad -
# brukt av _move_content_references() ved sammenslåing av to
# content-rader (se set_content_external_id()). Hver oppføring er
# (tabellnavn, kolonnenavn, kollisjons-nøkkelkolonner) - kollisjons-
# nøkkelkolonnene er resten av en evt. UNIQUE/PRIMARY-nøkkel utenom
# content_id selv, og brukes til å oppdage at "into" allerede har en
# rad med samme nøkkel (da beholdes "into" sin rad, og "from" sin
# droppes i stedet for å flyttes, for å unngå brudd på nøkkelen).
_CONTENT_REFERENCE_TABLES = (
    ("content_in_physical_collection", "content_id", ("collection_id",)),
    ("content_group_membership", "content_id", ("group_id",)),
    ("disc_related_content", "content_id", ("disc_id",)),
    # content_in_digital_library/season/disc_in.related_content_id har
    # ingen UNIQUE-nøkkel som involverer content_id (kun content_id selv
    # som vanlig indeks) - en ren flytting kan derfor aldri kollidere.
    ("content_in_digital_library", "content_id", ()),
    ("season", "content_id", ()),
    ("disc_in", "related_content_id", ()),
    # wishlist sin PRIMARY KEY ER content_id - "into" kan derfor allerede
    # ha en rad helt uten noen ekstra kollisjons-kolonne å sjekke mot.
    ("wishlist", "content_id", ()),
)


def _move_content_references(db: Session, from_content_id: bytes, into_content_id: bytes) -> dict:
    """Flytter alt som i dag peker på from_content_id til å peke på
    into_content_id i stedet - brukt når to content-rader slås sammen
    fordi de viste seg å gjelde samme film (se set_content_external_id()).
    Håndterer kollisjoner (into har allerede en rad med samme
    nøkkel/kolonne) ved å beholde into sin rad og droppe from sin.

    content_external_source håndteres for seg (se kallstedet) siden
    den alltid har nøyaktig én rad per (source, content_id) og selve
    poenget med sammenslåingen er at into skal beholde/få riktig id for
    kilden brukeren nettopp satte."""

    moved: dict[str, int] = {}

    for table, column, collision_columns in _CONTENT_REFERENCE_TABLES:
        if collision_columns:
            collision_select = ", ".join(collision_columns)
            into_keys = {
                tuple(row)
                for row in db.execute(
                    text(f"SELECT {collision_select} FROM {table} WHERE {column} = :id"),
                    {"id": into_content_id},
                ).fetchall()
            }
            from_rows = db.execute(
                text(f"SELECT {collision_select} FROM {table} WHERE {column} = :id"),
                {"id": from_content_id},
            ).fetchall()
            colliding = [row for row in from_rows if tuple(row) in into_keys]
            if colliding:
                where_extra = " AND ".join(f"{col} = :{col}" for col in collision_columns)
                for row in colliding:
                    params = {"id": from_content_id, **dict(zip(collision_columns, row))}
                    db.execute(
                        text(f"DELETE FROM {table} WHERE {column} = :id AND {where_extra}"),
                        params,
                    )

        result = db.execute(
            text(f"UPDATE {table} SET {column} = :into_id WHERE {column} = :from_id"),
            {"into_id": into_content_id, "from_id": from_content_id},
        )
        if result.rowcount:
            moved[table] = result.rowcount

    # Andre kilder (f.eks. from har en tvdb-kobling som into mangler) -
    # flytt over med mindre into allerede har en rad for samme source.
    into_sources = {
        row[0]
        for row in db.execute(
            text("SELECT source FROM content_external_source WHERE content_id = :id"),
            {"id": into_content_id},
        ).fetchall()
    }
    from_sources = db.execute(
        text("SELECT source FROM content_external_source WHERE content_id = :id"),
        {"id": from_content_id},
    ).fetchall()
    for (source_name,) in from_sources:
        if source_name in into_sources:
            db.execute(
                text("DELETE FROM content_external_source WHERE content_id = :id AND source = :source"),
                {"id": from_content_id, "source": source_name},
            )
        else:
            db.execute(
                text(
                    "UPDATE content_external_source SET content_id = :into_id "
                    "WHERE content_id = :from_id AND source = :source"
                ),
                {"into_id": into_content_id, "from_id": from_content_id, "source": source_name},
            )
            moved["content_external_source"] = moved.get("content_external_source", 0) + 1

    return moved


def set_content_external_id(
    db: Session,
    content_id: str,
    source: str,
    external_id: str,
    confirm_merge: bool = False,
    content_type: str | None = None,
) -> dict:
    """Setter en TMDB/TVDB-id på en content-rad som i dag mangler en (i
    motsetning til update_content_external_source(), som krever at
    koblingen finnes fra før) - eller erstatter en feil id.

    Validerer external_id-en ved å faktisk hente detaljer fra TMDB/TVDB
    med den FØR noe lagres, slik at en ugyldig/feilstavet id feiler
    tydelig i stedet for å lagre en død kobling.

    content_type ("movie"/"tv", valgfri) er typen brukeren faktisk
    søkte med i "Sett ID"-modalen - brukes FREMFOR content-radens egen,
    lagrede content_type til å velge riktig TMDB/TVDB-endepunkt. Dette
    er nødvendig fordi f.eks. Plex-import kan ha satt feil content_type
    (en serie importert som "movie") - uten denne overstyringen ville
    oppslaget skjedd mot feil endepunkt, og dermed truffet en helt
    annen, urelatert tittel på samme numeriske id (TMDB/TVDB har
    separate id-rom for filmer og serier). Hvis oppgitt og det avviker
    fra radens nåværende content_type, rettes content.content_type opp
    til det brukeren bekreftet samtidig som koblingen lagres.

    Hvis denne (source, external_id) allerede er registrert på en ANNEN
    content-rad (bekreftet med brukeren - duplikat-scenario, f.eks. en
    film som finnes både fra fysisk import og separat Plex/manuell
    opprettelse), opprettes IKKE en duplikat-kobling:
    - uten confirm_merge: kastes ContentExternalSourceConflict (409)
      med info om hvilken content-rad IDen allerede tilhører, slik at
      frontend kan spørre brukeren om å slå sammen.
    - med confirm_merge=True: alt som pekte på denne content-raden
      (fysisk eierskap, digitalt bibliotek, boxset/gruppe-medlemskap,
      disc-ekstramateriale, ønskeliste, andre kilder) flyttes over til
      den eksisterende raden (se _move_content_references()), og denne
      (nå tomme, duplikate) content-raden slettes. content_type må
      stemme overens mellom radene, ellers avvises sammenslåingen (se
      TODO.md - "Duplicate detection" for videre arbeid med dette)."""

    if source not in ("tmdb", "tvdb"):
        raise ContentExternalSourceError(
            f"Kun 'tmdb' og 'tvdb' kan settes her, ikke source={source!r}.",
            status_code=400,
        )

    external_id = external_id.strip()
    if not external_id:
        raise ContentExternalSourceError("external_id kan ikke være tom.", status_code=400)

    try:
        content_id_bytes = _parse_hex_id(content_id)
    except ValueError as e:
        raise ContentExternalSourceError(f"Ugyldig content_id={content_id!r}.", status_code=400) from e

    content_row = db.execute(
        text("SELECT content_type FROM content WHERE content_id = :content_id"),
        {"content_id": content_id_bytes},
    ).fetchone()
    if content_row is None:
        raise ContentExternalSourceError(f"Fant ingen content-rad med id={content_id!r}.", status_code=404)

    # Hva brukeren faktisk søkte med (hvis oppgitt) overstyrer radens
    # egen, lagrede content_type - se docstring.
    requested_db_content_type = (
        {"movie": "movie", "tv": "series"}.get(content_type) if content_type else None
    )
    content_type = requested_db_content_type or content_row.content_type or "movie"

    try:
        if source == "tmdb":
            data_json = fetch_tmdb_details(external_id, content_type)
        else:
            data_json = fetch_tvdb_details(external_id, content_type)
    except ExternalApiError as e:
        raise ContentExternalSourceError(str(e), status_code=e.status_code) from e

    conflict = db.execute(
        text(
            """
            SELECT ces.content_id, c.title, c.content_type
            FROM content_external_source ces
            JOIN content c ON c.content_id = ces.content_id
            WHERE ces.source = :source AND ces.external_id = :external_id
              AND ces.content_id != :content_id
            """
        ),
        {"source": source, "external_id": external_id, "content_id": content_id_bytes},
    ).fetchone()

    if conflict is not None:
        if not confirm_merge:
            is_physically_owned = db.execute(
                text("SELECT 1 FROM content_in_physical_collection WHERE content_id = :id LIMIT 1"),
                {"id": conflict.content_id},
            ).fetchone() is not None
            is_digitally_owned = db.execute(
                text("SELECT 1 FROM content_in_digital_library WHERE content_id = :id LIMIT 1"),
                {"id": conflict.content_id},
            ).fetchone() is not None
            raise ContentExternalSourceConflict(
                f"Denne {source.upper()}-IDen er allerede registrert på '{conflict.title}'.",
                conflict_content_id=_hex_id(conflict.content_id),
                conflict_title=conflict.title,
                physically_owned=is_physically_owned,
                digitally_owned=is_digitally_owned,
            )

        if conflict.content_type != content_type:
            raise ContentExternalSourceError(
                f"Kan ikke slå sammen - '{conflict.title}' er content_type={conflict.content_type!r}, "
                f"denne raden er content_type={content_type!r}. Antagelig feil id oppgitt.",
                status_code=409,
            )

        _move_content_references(db, content_id_bytes, conflict.content_id)
        db.execute(text("DELETE FROM content WHERE content_id = :id"), {"id": content_id_bytes})
        db.commit()

        return {
            "merged": True,
            "into_content_id": _hex_id(conflict.content_id),
            "into_title": conflict.title,
        }

    if source == "tmdb":
        facet_genres, facet_cast, facet_release_year = _compute_search_facets(data_json)
    else:
        facet_genres, facet_cast, facet_release_year = [], [], None

    # Brukeren bekreftet nettopp et treff av denne typen (f.eks. en serie
    # etter at raden feilaktig ble Plex-importert som "movie") - rett
    # opp content_type på raden tilsvarende, i samme transaksjon.
    if requested_db_content_type and requested_db_content_type != content_row.content_type:
        db.execute(
            text("UPDATE content SET content_type = :content_type WHERE content_id = :content_id"),
            {"content_type": requested_db_content_type, "content_id": content_id_bytes},
        )

    now = datetime.now(timezone.utc)
    # media_type ("movie"/"tv") er TMDB/TVDB-endepunktet SOM FAKTISK BLE
    # BRUKT over (utledet av content_type-verdien over, før en eventuell
    # senere, uavhengig endring av content.content_type - se
    # update_content_external_source() som leser denne kolonnen tilbake
    # for å slå opp samme endepunkt neste gang, UANSETT hva
    # content.content_type måtte bli endret til manuelt etterpå).
    media_type = "movie" if content_type == "movie" else "tv"
    db.execute(
        text(
            """
            INSERT INTO content_external_source (
                source, content_id, external_id, data_json, fetched_at,
                facet_genres, facet_cast, facet_release_year, media_type
            )
            VALUES (
                :source, :content_id, :external_id, :data_json, :fetched_at,
                :facet_genres, :facet_cast, :facet_release_year, :media_type
            )
            ON DUPLICATE KEY UPDATE
                external_id = VALUES(external_id),
                data_json = VALUES(data_json),
                fetched_at = VALUES(fetched_at),
                facet_genres = VALUES(facet_genres),
                facet_cast = VALUES(facet_cast),
                facet_release_year = VALUES(facet_release_year),
                media_type = VALUES(media_type)
            """
        ),
        {
            "source": source,
            "content_id": content_id_bytes,
            "external_id": external_id,
            "data_json": json.dumps(data_json, ensure_ascii=False),
            "fetched_at": now,
            "facet_genres": json.dumps(facet_genres),
            "facet_cast": json.dumps(facet_cast),
            "facet_release_year": facet_release_year,
            "media_type": media_type,
        },
    )
    db.commit()

    return {
        "merged": False,
        "content_id": _hex_id(content_id_bytes),
        "source": source,
        "external_id": external_id,
        "fetched_at": str(now)[:19],
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
            SELECT ces.content_id, ces.media_type, c.content_type
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

    # media_type ("movie"/"tv") er endepunktet som FAKTISK ble brukt for
    # denne (source, external_id) sist (satt av set_content_external_id())
    # - brukes her FREMFOR content.content_type, som kan ha blitt endret
    # manuelt etterpå uavhengig av hvilken type kilde-IDen faktisk er for
    # (f.eks. en TV-film brukeren bevisst har satt content_type='movie'
    # på, selv om TMDB/TVDB katalogiserer den som en serie - se
    # EDITABLE_CONTENT_FIELDS). Faller tilbake til content_type for
    # gamle rader som ble satt før media_type-kolonnen fantes og ikke er
    # hentet på nytt siden.
    content_type = existing.media_type or existing.content_type or "movie"

    try:
        if source == "tmdb":
            data_json = fetch_tmdb_details(external_id, content_type)
        else:
            data_json = fetch_tvdb_details(external_id, content_type)
    except ExternalApiError as e:
        raise ContentExternalSourceError(str(e), status_code=e.status_code) from e

    # facet_*-kolonnene brukes kun for source='tmdb' (se list_content());
    # ved en tvdb-oppdatering nulles de derfor ut i stedet for å la en
    # eventuell gammel tmdb-facet-verdi stå igjen feilaktig på raden.
    if source == "tmdb":
        facet_genres, facet_cast, facet_release_year = _compute_search_facets(data_json)
    else:
        facet_genres, facet_cast, facet_release_year = [], [], None

    db.execute(
        text(
            """
            UPDATE content_external_source
            SET data_json = :data_json, fetched_at = :fetched_at,
                facet_genres = :facet_genres, facet_cast = :facet_cast,
                facet_release_year = :facet_release_year, media_type = :media_type
            WHERE source = :source AND external_id = :external_id
            """
        ),
        {
            "source": source,
            "external_id": external_id,
            "data_json": json.dumps(data_json, ensure_ascii=False),
            "fetched_at": datetime.now(timezone.utc),
            "facet_genres": json.dumps(facet_genres),
            "facet_cast": json.dumps(facet_cast),
            "facet_release_year": facet_release_year,
            # Skriver tilbake samme media_type som faktisk ble brukt over
            # - selvhelbredende for gamle rader som manglet denne
            # kolonnen fra før migrasjonen (NULL -> fylt inn her).
            "media_type": content_type,
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

    title = _extract_tmdb_title(data)
    if title:
        fields["title"] = title
    original_title = data.get("original_title") or data.get("original_name")
    if original_title:
        fields["original_title"] = original_title
    # Filmer har "release_date", serier har "first_air_date" - se
    # _extract_tmdb_title() for samme film/serie-distinksjon.
    release_date = data.get("release_date") or data.get("first_air_date")
    if release_date:
        fields["first_release"] = release_date
    overview = _extract_tmdb_overview(data)
    if overview:
        fields["overview"] = overview
    if data.get("runtime"):
        fields["runtime"] = data["runtime"]
    elif data.get("episode_run_time"):
        # Serier har ingen samlet "runtime" - "episode_run_time" er en
        # liste med episodelengder (vanligvis bare én verdi registrert
        # for hele serien på TMDB) - bruker første som representativ
        # episodelengde.
        episode_run_times = data["episode_run_time"]
        if episode_run_times:
            fields["runtime"] = episode_run_times[0]
    if data.get("poster_path"):
        fields["cover_image"] = f"{TMDB_IMAGE_BASE_URL}{data['poster_path']}"

    imdb_id = (data.get("external_ids") or {}).get("imdb_id")
    if imdb_id:
        fields["imdb_id"] = imdb_id

    certification = _extract_tmdb_certification(data)
    if certification:
        fields["age_restriction"] = certification

    return fields


def _extract_tmdb_title(data: dict) -> str | None:
    """TMDB sin "title" (filmer) / "name" (serier) faller tilbake til
    "original_title"/"original_name" (ikke engelsk) når forespurt
    språk (nb-NO - se fetch_tmdb_details()) mangler oversettelse - det
    gir f.eks. den persiske tittelen 'جدایی نادر از سیمین' i stedet for
    "A Separation" for filmer uten norsk oversettelse registrert i
    TMDB (oppdaget via Plex-import).

    Hvis "title"/"name" og "original_title"/"original_name" er
    identiske (= ingen reell norsk oversettelse ble funnet), prøves en
    engelsk oversettelse fra append_to_response=translations (samme
    mønster som _extract_tmdb_overview()) før original-tittelen til
    slutt beholdes som siste utvei."""

    title = data.get("title") or data.get("name")
    original_title = data.get("original_title") or data.get("original_name")
    if not title:
        return original_title
    if original_title and title != original_title:
        return title

    translations = (data.get("translations") or {}).get("translations") or []
    for entry in translations:
        if entry.get("iso_639_1") == "en":
            entry_data = entry.get("data") or {}
            english_title = entry_data.get("title") or entry_data.get("name")
            if english_title:
                return english_title
    return title


def _extract_tmdb_overview(data: dict) -> str | None:
    """TMDB returnerer kun overview på det forespurte språket (nb-NO -
    se fetch_tmdb_details()), uten automatisk fallback til engelsk hvis
    den norske oversettelsen mangler. Norsk er et lite språk, så dette
    rammer mange titler (bekreftet: 647/773 av katalogen manglet
    "overview" etter en full bulk-refresh, se health-check-siden).

    append_to_response=translations (allerede med i hvert TMDB-kall)
    gir tilgang til ALLE oversettelser TMDB har lagret for tittelen -
    brukes her som fallback: engelsk foretrukket, ellers første
    tilgjengelige ikke-tomme oversettelse. Løser dermed det reelle
    problemet (visning av en beskrivelse) i stedet for å flagge titler
    som "mangler data" når data faktisk finnes, bare ikke på norsk.
    """
    if data.get("overview"):
        return data["overview"]

    translations = (data.get("translations") or {}).get("translations") or []
    for entry in translations:
        if entry.get("iso_639_1") == "en":
            overview = (entry.get("data") or {}).get("overview")
            if overview:
                return overview
    for entry in translations:
        overview = (entry.get("data") or {}).get("overview")
        if overview:
            return overview
    return None


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



# --- Sesonger/episoder (TV-serier) --------------------------------------
#
# Sesong-fanen på detaljsiden for content_type='series'. Enkel manuell
# CRUD (opprette/redigere/slette sesong+episode), pluss en "hent fra
# kilde"-funksjon som henter en hel sesongs episodeliste fra TMDB/TVDB
# i ett kall i stedet for én episode av gangen (se fetch_tmdb_season_
# episodes()/fetch_tvdb_season_episodes() i external_apis.py).
#
# NB: episode-feltene har (foreløpig) ingen locked_fields-mekanisme slik
# content har - "hent fra kilde" overskriver alltid tittel/beskrivelse/
# dato/varighet for episodene som finnes fra kilden. watched_flag
# påvirkes ALDRI av "hent fra kilde" (den er en lokal, manuell
# markering uavhengig av TMDB/TVDB).

def _season_to_dict(row) -> dict:
    return {
        "season_id": _hex_id(row.season_id),
        "season_number": row.season_number,
        "title": row.title,
        "air_date": row.air_date.isoformat() if row.air_date else None,
        "overview": row.overview,
    }


def _episode_to_dict(row, discs_by_episode_hex: dict) -> dict:
    ek = _hex_id(row.episode_id)
    return {
        "episode_id": ek,
        "episode_number": row.episode_number,
        "title": row.title,
        "overview": row.overview,
        "runtime": row.runtime,
        "original_air_date": row.original_air_date.isoformat() if row.original_air_date else None,
        "watched_flag": bool(row.watched_flag),
        # Liste siden en episode kan ligge på flere disker samtidig
        # (f.eks. en episode delt i to deler på to ulike disker i en
        # boks) - se assign_episode_disc().
        "discs": discs_by_episode_hex.get(ek, []),
    }


def list_seasons(db: Session, content_id: str) -> list[dict]:
    """Alle sesonger (med nøstede episodelister) for en serie, sortert
    på sesongnummer - brukt av sesong-fanen på detaljsiden."""

    try:
        raw_id = _parse_hex_id(content_id)
    except (ValueError, AttributeError):
        return []

    season_rows = db.execute(
        text(
            """
            SELECT season_id, season_number, title, air_date, overview
            FROM season
            WHERE content_id = :content_id
            ORDER BY season_number IS NULL, season_number
            """
        ),
        {"content_id": raw_id},
    ).fetchall()

    if not season_rows:
        return []

    season_ids = [row.season_id for row in season_rows]
    episode_rows = db.execute(
        text(
            """
            SELECT episode_id, season_id, episode_number, title, overview,
                   runtime, original_air_date, watched_flag
            FROM episode
            WHERE season_id IN :season_ids
            ORDER BY episode_number IS NULL, episode_number
            """
        ).bindparams(bindparam("season_ids", expanding=True)),
        {"season_ids": season_ids},
    ).fetchall()

    episode_ids = [row.episode_id for row in episode_rows]
    discs_by_episode_hex: dict[str, list[dict]] = {}
    if episode_ids:
        disc_rows = db.execute(
            text(
                """
                SELECT dce.episode_id, d.disc_id, d.label, di.box_set_disc_order
                FROM disc_contains_episode dce
                JOIN disc d ON d.disc_id = dce.disc_id
                LEFT JOIN disc_in di ON di.disc_id = d.disc_id
                WHERE dce.episode_id IN :episode_ids
                ORDER BY di.box_set_disc_order IS NULL, di.box_set_disc_order
                """
            ).bindparams(bindparam("episode_ids", expanding=True)),
            {"episode_ids": episode_ids},
        ).fetchall()
        for row in disc_rows:
            discs_by_episode_hex.setdefault(_hex_id(row.episode_id), []).append(
                {
                    "disc_id": _hex_id(row.disc_id),
                    "label": row.label,
                    "box_set_disc_order": row.box_set_disc_order,
                }
            )

    episodes_by_season: dict[bytes, list[dict]] = {}
    for row in episode_rows:
        episodes_by_season.setdefault(row.season_id, []).append(
            _episode_to_dict(row, discs_by_episode_hex)
        )

    return [
        {**_season_to_dict(row), "episodes": episodes_by_season.get(row.season_id, [])}
        for row in season_rows
    ]


def list_discs_for_content(db: Session, content_id: str) -> list[dict]:
    """Flat liste over diskene til en series fysiske eksemplarer - brukt
    som nedtrekksliste når en episode skal kobles til en disk (se
    assign_episode_disc()). Viser alle disker uansett om de allerede
    inneholder episode-koblinger eller ikke."""

    try:
        raw_id = _parse_hex_id(content_id)
    except (ValueError, AttributeError):
        return []

    rows = db.execute(
        text(
            """
            SELECT DISTINCT d.disc_id, d.label, di.box_set_disc_order, di.copy_id
            FROM content_in_physical_collection cipc
            JOIN physical_copy pc ON pc.collection_id = cipc.collection_id
            JOIN disc_in di ON di.collection_id = pc.collection_id AND di.copy_id = pc.copy_id
            JOIN disc d ON d.disc_id = di.disc_id
            WHERE cipc.content_id = :content_id
            ORDER BY di.box_set_disc_order IS NULL, di.box_set_disc_order
            """
        ),
        {"content_id": raw_id},
    ).fetchall()

    return [
        {
            "disc_id": _hex_id(row.disc_id),
            "label": row.label,
            "box_set_disc_order": row.box_set_disc_order,
            "copy_id": row.copy_id,
        }
        for row in rows
    ]


def create_season(db: Session, content_id: str, fields: dict) -> dict:
    try:
        raw_content_id = _parse_hex_id(content_id)
    except (ValueError, AttributeError) as e:
        raise ContentExternalSourceError(f"Ugyldig content_id={content_id!r}.", status_code=400) from e

    content_row = db.execute(
        text("SELECT content_id FROM content WHERE content_id = :id"),
        {"id": raw_content_id},
    ).fetchone()
    if content_row is None:
        raise ContentExternalSourceError(f"Fant ingen content-rad med id={content_id!r}.", status_code=404)

    season_id = uuid.uuid4().bytes
    db.execute(
        text(
            """
            INSERT INTO season (season_id, content_id, season_number, title, air_date, overview)
            VALUES (:season_id, :content_id, :season_number, :title, :air_date, :overview)
            """
        ),
        {
            "season_id": season_id,
            "content_id": raw_content_id,
            "season_number": fields.get("season_number"),
            "title": fields.get("title"),
            "air_date": fields.get("air_date"),
            "overview": fields.get("overview"),
        },
    )
    db.commit()

    return {**_season_to_dict_from_fields(season_id, fields), "episodes": []}


def _season_to_dict_from_fields(season_id: bytes, fields: dict) -> dict:
    air_date = fields.get("air_date")
    return {
        "season_id": _hex_id(season_id),
        "season_number": fields.get("season_number"),
        "title": fields.get("title"),
        "air_date": air_date.isoformat() if air_date else None,
        "overview": fields.get("overview"),
    }


def _get_season_row(db: Session, season_id: str):
    try:
        raw_id = _parse_hex_id(season_id)
    except (ValueError, AttributeError) as e:
        raise ContentExternalSourceError(f"Ugyldig season_id={season_id!r}.", status_code=400) from e

    row = db.execute(
        text("SELECT season_id, content_id, season_number FROM season WHERE season_id = :id"),
        {"id": raw_id},
    ).fetchone()
    if row is None:
        raise ContentExternalSourceError(f"Fant ingen sesong med id={season_id!r}.", status_code=404)
    return row


def update_season(db: Session, season_id: str, fields: dict) -> dict:
    row = _get_season_row(db, season_id)
    if not fields:
        return list_seasons(db, _hex_id(row.content_id))[0]

    set_clauses = ", ".join(f"{field} = :{field}" for field in fields)
    db.execute(
        text(f"UPDATE season SET {set_clauses} WHERE season_id = :season_id"),
        {**fields, "season_id": row.season_id},
    )
    db.commit()

    seasons = list_seasons(db, _hex_id(row.content_id))
    return next(s for s in seasons if s["season_id"] == _hex_id(row.season_id))


def delete_season(db: Session, season_id: str) -> dict:
    row = _get_season_row(db, season_id)
    db.execute(text("DELETE FROM season WHERE season_id = :id"), {"id": row.season_id})
    db.commit()
    return {"deleted": True, "season_id": _hex_id(row.season_id)}


def set_season_watched(db: Session, season_id: str, watched_flag: bool) -> dict:
    """Setter watched_flag for samtlige episoder i en sesong under ett."""
    row = _get_season_row(db, season_id)
    db.execute(
        text("UPDATE episode SET watched_flag = :watched_flag WHERE season_id = :season_id"),
        {"watched_flag": 1 if watched_flag else 0, "season_id": row.season_id},
    )
    db.commit()

    seasons = list_seasons(db, _hex_id(row.content_id))
    return next(s for s in seasons if s["season_id"] == _hex_id(row.season_id))


def create_episode(db: Session, season_id: str, fields: dict) -> dict:
    row = _get_season_row(db, season_id)

    episode_id = uuid.uuid4().bytes
    db.execute(
        text(
            """
            INSERT INTO episode (
                episode_id, season_id, episode_number, title, overview,
                runtime, original_air_date, watched_flag
            )
            VALUES (
                :episode_id, :season_id, :episode_number, :title, :overview,
                :runtime, :original_air_date, 0
            )
            """
        ),
        {
            "episode_id": episode_id,
            "season_id": row.season_id,
            "episode_number": fields.get("episode_number"),
            "title": fields.get("title"),
            "overview": fields.get("overview"),
            "runtime": fields.get("runtime"),
            "original_air_date": fields.get("original_air_date"),
        },
    )
    db.commit()

    seasons = list_seasons(db, _hex_id(row.content_id))
    season = next(s for s in seasons if s["season_id"] == _hex_id(row.season_id))
    return next(e for e in season["episodes"] if e["episode_id"] == _hex_id(episode_id))


def _get_episode_row(db: Session, episode_id: str):
    try:
        raw_id = _parse_hex_id(episode_id)
    except (ValueError, AttributeError) as e:
        raise ContentExternalSourceError(f"Ugyldig episode_id={episode_id!r}.", status_code=400) from e

    row = db.execute(
        text(
            """
            SELECT ep.episode_id, ep.season_id, sea.content_id
            FROM episode ep
            JOIN season sea ON sea.season_id = ep.season_id
            WHERE ep.episode_id = :id
            """
        ),
        {"id": raw_id},
    ).fetchone()
    if row is None:
        raise ContentExternalSourceError(f"Fant ingen episode med id={episode_id!r}.", status_code=404)
    return row


def update_episode(db: Session, episode_id: str, fields: dict) -> dict:
    row = _get_episode_row(db, episode_id)
    if not fields:
        pass
    else:
        if "watched_flag" in fields:
            fields = {**fields, "watched_flag": 1 if fields["watched_flag"] else 0}
        set_clauses = ", ".join(f"{field} = :{field}" for field in fields)
        db.execute(
            text(f"UPDATE episode SET {set_clauses} WHERE episode_id = :episode_id"),
            {**fields, "episode_id": row.episode_id},
        )
        db.commit()

    seasons = list_seasons(db, _hex_id(row.content_id))
    season = next(s for s in seasons if s["season_id"] == _hex_id(row.season_id))
    return next(e for e in season["episodes"] if e["episode_id"] == _hex_id(row.episode_id))


def delete_episode(db: Session, episode_id: str) -> dict:
    row = _get_episode_row(db, episode_id)
    db.execute(text("DELETE FROM episode WHERE episode_id = :id"), {"id": row.episode_id})
    db.commit()
    return {"deleted": True, "episode_id": _hex_id(row.episode_id)}


def assign_episode_disc(db: Session, episode_id: str, disc_ids: list[str]) -> dict:
    """Kobler (eller fjerner koblingen for) hvilke(n) fysisk(e) disk(er)
    en episode ligger på - disc_contains_episode er mange-til-mange i
    skjemaet, og dette støttes nå fullt ut her: en episode kan ligge på
    flere disker samtidig (f.eks. en episode som er delt i to deler på
    to ulike disker i en boks). Erstatter ALLE eksisterende koblinger
    for episoden med disc_ids-listen - tom liste fjerner alle
    koblinger uten å sette noen nye."""

    row = _get_episode_row(db, episode_id)

    valid_discs = {d["disc_id"] for d in list_discs_for_content(db, _hex_id(row.content_id))}
    raw_disc_ids = []
    for disc_id in disc_ids:
        if disc_id.upper() not in valid_discs:
            raise ContentExternalSourceError(
                "Denne disken tilhører ikke denne seriens fysiske eksemplarer.", status_code=400
            )
        try:
            raw_disc_ids.append(_parse_hex_id(disc_id))
        except (ValueError, AttributeError) as e:
            raise ContentExternalSourceError(f"Ugyldig disc_id={disc_id!r}.", status_code=400) from e

    db.execute(text("DELETE FROM disc_contains_episode WHERE episode_id = :id"), {"id": row.episode_id})
    for raw_disc_id in raw_disc_ids:
        db.execute(
            text("INSERT INTO disc_contains_episode (disc_id, episode_id) VALUES (:disc_id, :episode_id)"),
            {"disc_id": raw_disc_id, "episode_id": row.episode_id},
        )
    db.commit()

    seasons = list_seasons(db, _hex_id(row.content_id))
    season = next(s for s in seasons if s["season_id"] == _hex_id(row.season_id))
    return next(e for e in season["episodes"] if e["episode_id"] == _hex_id(row.episode_id))


def refresh_season_from_source(
    db: Session, season_id: str, source: str, season_type: str = "default"
) -> dict:
    """Henter hele sesongens episodeliste fra TMDB/TVDB i ett kall og
    oppdaterer/oppretter episoder deretter, matchet på episode_number:
    - finnes en episode med samme nummer fra før: tittel/beskrivelse/
      dato/varighet overskrives (watched_flag røres ikke).
    - finnes ingen: en ny episode-rad opprettes.
    Episoder i databasen som kilden IKKE lenger har (f.eks. feilskrevet
    episodenummer) slettes bevisst IKKE automatisk her - det er opp til
    brukeren å rydde opp manuelt, siden en episode kan ha en disk-
    kobling man ikke vil miste ved en feiltakelse.

    Henter show-ens TMDB/TVDB-id fra content_external_source (samme
    kobling som settes via "Sett TMDB/TVDB-id"-modalen på selve serien,
    IKKE en separat id pr. sesong/episode).

    season_type brukes kun for source='tvdb' - TVDB nummererer enkelte
    serier helt forskjellig avhengig av om man ser på sendedato-
    rekkefølge ('default') eller DVD/Blu-ray-utgivelsens rekkefølge
    ('dvd'), se fetch_tvdb_season_episodes(). TMDB har ikke et
    tilsvarende enkelt konsept, så parameteren ignoreres for source=
    'tmdb'."""

    if source not in ("tmdb", "tvdb"):
        raise ContentExternalSourceError(f"Kun 'tmdb' og 'tvdb' støttes, ikke source={source!r}.", status_code=400)

    row = _get_season_row(db, season_id)
    if row.season_number is None:
        raise ContentExternalSourceError(
            "Denne sesongen mangler sesongnummer - kan ikke hente episoder fra kilde.", status_code=400
        )

    show_external_id_row = db.execute(
        text("SELECT external_id FROM content_external_source WHERE content_id = :id AND source = :source"),
        {"id": row.content_id, "source": source},
    ).fetchone()
    if show_external_id_row is None:
        raise ContentExternalSourceError(
            f"Serien har ingen {source.upper()}-id ennå - sett den først (se 'Sett ID'-knappen under Oversikt).",
            status_code=400,
        )

    try:
        if source == "tmdb":
            result = fetch_tmdb_season_episodes(show_external_id_row.external_id, row.season_number)
        else:
            result = fetch_tvdb_season_episodes(
                show_external_id_row.external_id, row.season_number, season_type=season_type
            )
    except ExternalApiError as e:
        raise ContentExternalSourceError(str(e), status_code=e.status_code) from e

    if result.get("season_overview"):
        db.execute(
            text("UPDATE season SET overview = :overview WHERE season_id = :id"),
            {"overview": result["season_overview"], "id": row.season_id},
        )

    existing_rows = db.execute(
        text("SELECT episode_id, episode_number FROM episode WHERE season_id = :id"),
        {"id": row.season_id},
    ).fetchall()
    episode_id_by_number = {r.episode_number: r.episode_id for r in existing_rows if r.episode_number is not None}

    created = 0
    updated = 0
    for ep in result.get("episodes") or []:
        ep_number = ep.get("episode_number")
        if ep_number is None:
            continue

        if ep_number in episode_id_by_number:
            db.execute(
                text(
                    """
                    UPDATE episode
                    SET title = :title, overview = :overview,
                        runtime = :runtime, original_air_date = :air_date
                    WHERE episode_id = :id
                    """
                ),
                {
                    "title": ep.get("title"),
                    "overview": ep.get("overview"),
                    "runtime": ep.get("runtime"),
                    "air_date": ep.get("air_date"),
                    "id": episode_id_by_number[ep_number],
                },
            )
            updated += 1
        else:
            db.execute(
                text(
                    """
                    INSERT INTO episode (
                        episode_id, season_id, episode_number, title, overview,
                        runtime, original_air_date, watched_flag
                    )
                    VALUES (
                        :episode_id, :season_id, :episode_number, :title, :overview,
                        :runtime, :air_date, 0
                    )
                    """
                ),
                {
                    "episode_id": uuid.uuid4().bytes,
                    "season_id": row.season_id,
                    "episode_number": ep_number,
                    "title": ep.get("title"),
                    "overview": ep.get("overview"),
                    "runtime": ep.get("runtime"),
                    "air_date": ep.get("air_date"),
                },
            )
            created += 1

    db.commit()

    seasons = list_seasons(db, _hex_id(row.content_id))
    season = next(s for s in seasons if s["season_id"] == _hex_id(row.season_id))
    return {**season, "created": created, "updated": updated}
