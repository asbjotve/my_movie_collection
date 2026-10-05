"""Selve Plex-import-jobben (steg 2, se TODO.md) - leser valgte
bibliotek fra Plex og fyller digital_library/content_in_digital_library/
digital_media_version/digital_media_part (db_mediearkiv).

Kjøres synkront via en manuell "Kjør import nå"-knapp (ingen
jobbkø/scheduler ennå) - se plex_import_route.py.

Matching mot eksisterende content (bekreftet med brukeren):
1. Plex sin TMDB-guid (hvis filmen har en) slås opp mot
   content_external_source (source='tmdb') - finnes en match, kobles
   Plex-elementet til den content-raden UTEN å endre noe på den.
2. Hvis ingen TMDB-match: prøv tittel+utgivelsesår mot content - men
   KUN hvis dette gir akkurat ett treff (flere treff/ingen treff =
   ikke trygt å gjette, opprett ny rad i stedet).
3. Ellers opprettes en helt ny content-rad. Er TMDB-id kjent, hentes
   fulle detaljer fra TMDB med en gang (samme mønster som
   add_data/physical_collection_import.py) og flettes inn via den
   allerede eksisterende merge_content_from_source() - det gir en
   fullverdig content-rad (tittel/cover/overview/etc.) med en gang,
   ikke bare det Plex selv oppgir.

Import er bevisst én-veis (kun LESING fra Plex, se TODO.md) - denne
modulen skriver ALDRI noe tilbake til Plex over API-et.

Pruning: etter at et biblioteks elementer er synkronisert, fjernes
content_in_digital_library-rader for ratingKey-er som IKKE dukket opp
i denne runden (= fjernet fra Plex siden forrige import) - se
_prune_removed_items(). Selve content-raden (filmen i katalogen)
slettes ALDRI av dette - kun koblingen til det digitale biblioteket.
"""

import json
import logging
import uuid
from datetime import datetime, timezone

from sqlalchemy import text
from sqlalchemy.orm import Session

from app.services.external_apis import ExternalApiError, fetch_tmdb_details
from app.services.media_catalog import (
    ContentExternalSourceError,
    _compute_search_facets,
    merge_content_from_source,
)
from app.services.plex_client import (
    PlexConnectionError,
    extract_tmdb_id_from_guids,
    fetch_item_detail,
    fetch_library_items,
    fetch_library_sections,
)

logger = logging.getLogger(__name__)


def _new_uuid_bytes() -> bytes:
    return uuid.uuid4().bytes


def _hex_id(raw: bytes) -> str:
    return uuid.UUID(bytes=raw).hex.upper()


# MySQL sin TIMESTAMP-kolonnetype (added_at bruker denne) støtter kun
# 1970-01-01 00:00:01 til 2038-01-19 03:14:07 UTC - Plex har vist seg å
# rapportere plassholder-/korrupte addedAt-verdier langt utenfor dette
# (f.eks. år 1961/1969) for noen filmer, noe som ellers feiler hele
# INSERT-en med "Incorrect datetime value" (oppdaget i produksjon på en
# reell import - 9 av 1355 filmer).
_TIMESTAMP_MIN = datetime(1970, 1, 1, 0, 0, 1, tzinfo=timezone.utc)
_TIMESTAMP_MAX = datetime(2038, 1, 19, 3, 14, 7, tzinfo=timezone.utc)


def _plex_epoch_to_datetime(value) -> datetime | None:
    """Plex oppgir tidsstempler (addedAt osv.) som unix-epoketid i
    sekunder - None hvis feltet mangler/er ugyldig/utenfor MySQL sin
    TIMESTAMP-rekkevidde."""

    if not value:
        return None
    try:
        parsed = datetime.fromtimestamp(int(value), tz=timezone.utc)
    except (TypeError, ValueError, OSError):
        return None
    if parsed < _TIMESTAMP_MIN or parsed > _TIMESTAMP_MAX:
        return None
    return parsed


def _match_or_create_content(db: Session, tmdb_id: str | None, title: str, year: int | None) -> tuple[bytes, bool]:
    """Returnerer (content_id, ble_opprettet_nå). Se modulens docstring
    for selve matching-strategien."""

    if tmdb_id:
        row = db.execute(
            text(
                "SELECT content_id FROM content_external_source "
                "WHERE source = 'tmdb' AND external_id = :external_id"
            ),
            {"external_id": tmdb_id},
        ).fetchone()
        if row:
            return row[0], False

    if year:
        rows = db.execute(
            text(
                """
                SELECT content_id FROM content
                WHERE LOWER(title) = LOWER(:title) AND YEAR(first_release) = :year
                LIMIT 2
                """
            ),
            {"title": title, "year": year},
        ).fetchall()
        if len(rows) == 1:
            return rows[0][0], False

    content_id = _new_uuid_bytes()
    first_release = f"{year}-01-01" if year else None
    db.execute(
        text(
            """
            INSERT INTO content (
                content_id, title, content_type, overview,
                first_release, watched_flag, temporary_flag
            )
            VALUES (
                :content_id, :title, 'movie', '',
                :first_release, 0, 0
            )
            """
        ),
        {"content_id": content_id, "title": title, "first_release": first_release},
    )

    if tmdb_id:
        try:
            data = fetch_tmdb_details(tmdb_id, "movie")
        except ExternalApiError as exc:
            logger.warning("Klarte ikke å hente TMDB-detaljer (id=%s) ved Plex-import: %s", tmdb_id, exc)
        else:
            facet_genres, facet_cast, facet_release_year = _compute_search_facets(data)
            db.execute(
                text(
                    """
                    INSERT INTO content_external_source (
                        source, content_id, external_id, data_json, fetched_at,
                        facet_genres, facet_cast, facet_release_year
                    )
                    VALUES (
                        'tmdb', :content_id, :external_id, :data_json, :fetched_at,
                        :facet_genres, :facet_cast, :facet_release_year
                    )
                    """
                ),
                {
                    "content_id": content_id,
                    "external_id": tmdb_id,
                    "data_json": json.dumps(data, ensure_ascii=False),
                    "fetched_at": datetime.now(timezone.utc),
                    "facet_genres": json.dumps(facet_genres),
                    "facet_cast": json.dumps(facet_cast),
                    "facet_release_year": facet_release_year,
                },
            )
            db.flush()
            try:
                merge_content_from_source(db, "tmdb", tmdb_id)
            except ContentExternalSourceError as exc:
                logger.warning("Klarte ikke å flette inn TMDB-data (id=%s) ved Plex-import: %s", tmdb_id, exc)

    return content_id, True


def _upsert_digital_library(db: Session, section: dict) -> bytes:
    row = db.execute(
        text("SELECT digital_library_id FROM digital_library WHERE plex_section_key = :key"),
        {"key": section["key"]},
    ).fetchone()
    now = datetime.now(timezone.utc)

    if row:
        db.execute(
            text(
                "UPDATE digital_library SET title = :title, library_type = :type, "
                "last_synced_at = :now WHERE digital_library_id = :id"
            ),
            {"title": section.get("title"), "type": section.get("type"), "now": now, "id": row[0]},
        )
        return row[0]

    digital_library_id = _new_uuid_bytes()
    db.execute(
        text(
            """
            INSERT INTO digital_library (digital_library_id, plex_section_key, title, library_type, last_synced_at)
            VALUES (:id, :key, :title, :type, :now)
            """
        ),
        {
            "id": digital_library_id,
            "key": section["key"],
            "title": section.get("title"),
            "type": section.get("type"),
            "now": now,
        },
    )
    return digital_library_id


def _upsert_content_in_digital_library(
    db: Session, content_id: bytes, digital_library_id: bytes, rating_key: str, detail: dict
) -> bytes:
    row = db.execute(
        text(
            "SELECT id FROM content_in_digital_library "
            "WHERE digital_library_id = :lib AND plex_rating_key = :rk"
        ),
        {"lib": digital_library_id, "rk": rating_key},
    ).fetchone()

    guid = detail.get("guid")
    added_at = _plex_epoch_to_datetime(detail.get("addedAt"))

    if row:
        db.execute(
            text(
                "UPDATE content_in_digital_library SET content_id = :content_id, "
                "plex_guid = :guid, added_at = :added_at WHERE id = :id"
            ),
            {"content_id": content_id, "guid": guid, "added_at": added_at, "id": row[0]},
        )
        return row[0]

    cidl_id = _new_uuid_bytes()
    db.execute(
        text(
            """
            INSERT INTO content_in_digital_library (id, content_id, digital_library_id, plex_rating_key, plex_guid, added_at)
            VALUES (:id, :content_id, :digital_library_id, :rating_key, :guid, :added_at)
            """
        ),
        {
            "id": cidl_id,
            "content_id": content_id,
            "digital_library_id": digital_library_id,
            "rating_key": rating_key,
            "guid": guid,
            "added_at": added_at,
        },
    )
    return cidl_id


def _replace_media_versions(db: Session, content_in_digital_library_id: bytes, media_list: list[dict]) -> None:
    """Sletter alle tidligere lagrede versjoner/deler for dette
    elementet og legger inn ferske rader fra Plex sitt svar - enklere
    og mer robust enn å prøve å diffe mot forrige sync (en fil som
    endres på disk, f.eks. ved re-rip i bedre kvalitet, oppdager vi
    dermed alltid korrekt). digital_media_part rydder seg selv via
    ON DELETE CASCADE når versjonen slettes."""

    db.execute(
        text("DELETE FROM digital_media_version WHERE content_in_digital_library_id = :id"),
        {"id": content_in_digital_library_id},
    )

    for media in media_list:
        media_version_id = _new_uuid_bytes()
        db.execute(
            text(
                """
                INSERT INTO digital_media_version (
                    media_version_id, content_in_digital_library_id, plex_media_id,
                    resolution, video_codec, audio_codec, container, duration_ms, bitrate
                )
                VALUES (
                    :id, :cidl_id, :plex_media_id,
                    :resolution, :video_codec, :audio_codec, :container, :duration_ms, :bitrate
                )
                """
            ),
            {
                "id": media_version_id,
                "cidl_id": content_in_digital_library_id,
                "plex_media_id": media.get("id"),
                "resolution": media.get("videoResolution"),
                "video_codec": media.get("videoCodec"),
                "audio_codec": media.get("audioCodec"),
                "container": media.get("container"),
                "duration_ms": media.get("duration"),
                "bitrate": media.get("bitrate"),
            },
        )

        for part_index, part in enumerate(media.get("Part", [])):
            db.execute(
                text(
                    """
                    INSERT INTO digital_media_part (part_id, media_version_id, plex_part_id, file_path, size_bytes, part_index)
                    VALUES (:id, :media_version_id, :plex_part_id, :file_path, :size_bytes, :part_index)
                    """
                ),
                {
                    "id": _new_uuid_bytes(),
                    "media_version_id": media_version_id,
                    "plex_part_id": part.get("id"),
                    "file_path": part.get("file"),
                    "size_bytes": part.get("size"),
                    "part_index": part_index,
                },
            )


def _store_plex_raw_source(db: Session, content_id: bytes, rating_key: str, detail: dict) -> None:
    """Lagrer rå Plex-metadata for elementet i content_external_source
    (source='plex') - samme mønster som source='tmdb'/'tvdb', slik at
    det senere er mulig å se nøyaktig hva Plex rapporterte sist."""

    data_json = json.dumps(detail, ensure_ascii=False)
    now = datetime.now(timezone.utc)

    existing = db.execute(
        text("SELECT 1 FROM content_external_source WHERE source = 'plex' AND content_id = :content_id"),
        {"content_id": content_id},
    ).fetchone()

    if existing:
        db.execute(
            text(
                "UPDATE content_external_source SET external_id = :rating_key, "
                "data_json = :data_json, fetched_at = :now "
                "WHERE source = 'plex' AND content_id = :content_id"
            ),
            {"rating_key": rating_key, "data_json": data_json, "now": now, "content_id": content_id},
        )
    else:
        db.execute(
            text(
                """
                INSERT INTO content_external_source (source, content_id, external_id, data_json, fetched_at)
                VALUES ('plex', :content_id, :rating_key, :data_json, :now)
                """
            ),
            {"content_id": content_id, "rating_key": rating_key, "data_json": data_json, "now": now},
        )


def _prune_removed_items(db: Session, digital_library_id: bytes, seen_rating_keys: set[str]) -> int:
    """Sletter content_in_digital_library-rader for dette biblioteket
    som IKKE var med i denne synk-runden - altså filmer som er fjernet
    fra Plex siden forrige import. digital_media_version/part rydder
    seg selv via ON DELETE CASCADE.

    Sletter bevisst KUN kobling til det digitale biblioteket, ikke selv
    content-raden - filmen kan fortsatt være eid fysisk eller koblet
    til andre bibliotek, og content_in_digital_library har ingen
    ON DELETE CASCADE tilbake til content (se migrasjonen), så dette
    påvirker aldri andre deler av samlingen."""

    rows = db.execute(
        text("SELECT id, plex_rating_key FROM content_in_digital_library WHERE digital_library_id = :lib"),
        {"lib": digital_library_id},
    ).fetchall()

    stale_ids = [row[0] for row in rows if row[1] not in seen_rating_keys]
    if not stale_ids:
        return 0

    for stale_id in stale_ids:
        db.execute(
            text("DELETE FROM content_in_digital_library WHERE id = :id"),
            {"id": stale_id},
        )
    db.commit()
    return len(stale_ids)


def run_plex_import(
    db: Session,
    base_url: str,
    token: str,
    verify_ssl: bool,
    selected_library_keys: list[str],
    progress_callback=None,
) -> dict:
    """Kjører en full import-runde for alle valgte bibliotek. Returnerer
    et sammendrag til UI-et - feiler IKKE hele kjøringen pga. ett enkelt
    Plex-element (f.eks. korrupt/uventet respons) - den filmen logges i
    "errors" og resten av biblioteket fortsetter.

    progress_callback(library_title, processed, total), hvis gitt, kalles
    etter hvert element - brukes av plex_import_route.py til å oppdatere
    en in-memory statusstruktur som frontend kan polle (denne funksjonen
    kjøres synkront, ofte i en egen bakgrunnstråd, nettopp for at en lang
    import ikke skal blokkere resten av nettstedet - se route-filen)."""

    summary = {
        "libraries_synced": [],
        "movies_created": 0,
        "movies_matched": 0,
        "skipped_non_movie_libraries": [],
        "movies_pruned": 0,
        "errors": [],
    }

    sections = fetch_library_sections(base_url, token, verify_ssl)
    sections_by_key = {s["key"]: s for s in sections}

    for key in selected_library_keys:
        section = sections_by_key.get(key)
        if section is None:
            summary["errors"].append(f"Bibliotek med key={key} finnes ikke lenger på Plex-serveren")
            continue

        if section.get("type") != "movie":
            # scope=movies_only (bekreftet med brukeren) - TV-serier
            # kommer i en senere omgang.
            summary["skipped_non_movie_libraries"].append(section.get("title"))
            continue

        digital_library_id = _upsert_digital_library(db, section)
        # Commit immediately so this row survives even if a later item in the
        # loop below fails and triggers a rollback.
        db.commit()

        try:
            items = fetch_library_items(base_url, token, key, verify_ssl)
        except PlexConnectionError as exc:
            summary["errors"].append(f"{section.get('title')}: {exc}")
            continue

        created_count = 0
        matched_count = 0
        total_items = len(items)
        seen_rating_keys: set[str] = set()

        for processed, item in enumerate(items, start=1):
            rating_key = item.get("ratingKey")
            if not rating_key:
                continue
            # Regnes som "sett" selv om elementet feiler lenger ned - det
            # finnes fortsatt på Plex-serveren, og skal dermed ikke prunes
            # som fjernet (se _prune_removed_items()).
            seen_rating_keys.add(rating_key)

            title = item.get("title") or "Ukjent tittel"

            try:
                detail = fetch_item_detail(base_url, token, rating_key, verify_ssl) or item
                tmdb_id = extract_tmdb_id_from_guids(detail)
                title = detail.get("title") or title
                year = detail.get("year") or item.get("year")

                content_id, was_created = _match_or_create_content(db, tmdb_id, title, year)
                cidl_id = _upsert_content_in_digital_library(db, content_id, digital_library_id, rating_key, detail)
                _replace_media_versions(db, cidl_id, detail.get("Media", []))
                _store_plex_raw_source(db, content_id, rating_key, detail)
                # Commit per item (not per library) so a failure on one item
                # cannot roll back items that were already processed
                # successfully earlier in the same library's loop.
                db.commit()

                if was_created:
                    created_count += 1
                else:
                    matched_count += 1
            except Exception as exc:  # noqa: BLE001 - must not abort the whole import on a single bad item
                db.rollback()
                summary["errors"].append(f"{title}: {exc}")

            if progress_callback is not None:
                progress_callback(section.get("title"), processed, total_items)

        try:
            pruned_count = _prune_removed_items(db, digital_library_id, seen_rating_keys)
        except Exception as exc:  # noqa: BLE001 - pruning må aldri felle hele importen
            db.rollback()
            summary["errors"].append(f"{section.get('title')}: klarte ikke å fjerne slettede filmer: {exc}")
            pruned_count = 0
        summary["movies_pruned"] += pruned_count

        summary["movies_created"] += created_count
        summary["movies_matched"] += matched_count
        summary["libraries_synced"].append({"title": section.get("title"), "item_count": len(items)})

    return summary
