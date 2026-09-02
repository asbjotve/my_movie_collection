import json
import logging
import uuid
from datetime import datetime
from typing import Optional

from sqlalchemy import text
from sqlalchemy.orm import Session

from app.services.external_apis import (
    ExternalApiError,
    fetch_tmdb_details,
    fetch_tvdb_details,
)

logger = logging.getLogger(__name__)


def _new_uuid_bytes() -> bytes:
    return uuid.uuid4().bytes


def _uuid_str_to_bytes(value: Optional[str]) -> Optional[bytes]:
    if value is None:
        return None
    try:
        return uuid.UUID(value).bytes
    except ValueError as e:
        raise ValueError(f"Invalid UUID value: {value}") from e


def _normalize_label(label: Optional[str]) -> Optional[str]:
    if label is None:
        return None
    if isinstance(label, str) and label.strip().lower() == "null":
        return None
    label = label.strip()
    return label or None


def _get_content_by_imdb_id(db: Session, imdb_id: Optional[str]) -> Optional[bytes]:
    if not imdb_id:
        return None

    row = db.execute(
        text("""
            SELECT content_id
            FROM content
            WHERE imdb_id = :imdb_id
            LIMIT 1
        """),
        {"imdb_id": imdb_id},
    ).fetchone()

    return row[0] if row else None


def _fetch_external_source_payload(source: str, external_id: str) -> tuple[dict, Optional[datetime]]:
    """Henter fulle detaljer fra TMDB/TVDB for en ekstern id oppgitt ved
    import, samme funksjoner som brukes av "oppdater fra kilde"-
    endepunktet (update_content_external_source).

    Feiler bevisst IKKE hele importen hvis kallet mot TMDB/TVDB
    mislykkes (f.eks. API nede, feil id) - faller da tilbake til en
    enkel id-ekko slik det alltid har blitt lagret, og logger en
    advarsel. En importfeil pga. et eksternt API er ikke noe brukeren
    bør miste hele den fysiske importen sin på.
    """

    try:
        if source == "tmdb":
            data = fetch_tmdb_details(external_id, "movie")
        else:
            data = fetch_tvdb_details(external_id, "movie")
        return data, datetime.utcnow()
    except ExternalApiError as e:
        logger.warning(
            "Kunne ikke hente fulle %s-detaljer for external_id=%s ved import: %s",
            source,
            external_id,
            e,
        )
        return {f"{source}_id": external_id}, None


def _create_content(
    db: Session,
    title: str,
    imdb_id: Optional[str],
    tmdb_id: Optional[str],
    tvdb_id: Optional[str] = None,
    temporary_flag: int = 0,
) -> bytes:
    content_id = _new_uuid_bytes()

    db.execute(
        text("""
            INSERT INTO content (
                content_id,
                title,
                content_type,
                imdb_id,
                overview,
                watched_flag,
                temporary_flag
            )
            VALUES (
                :content_id,
                :title,
                :content_type,
                :imdb_id,
                :overview,
                :watched_flag,
                :temporary_flag
            )
        """),
        {
            "content_id": content_id,
            "title": title,
            "content_type": "movie",
            "imdb_id": imdb_id,
            "overview": "",
            "watched_flag": 0,
            "temporary_flag": temporary_flag,
        },
    )

    if imdb_id:
        db.execute(
            text("""
                INSERT INTO content_external_source (
                    source,
                    content_id,
                    external_id,
                    data_json,
                    fetched_at
                )
                VALUES (
                    :source,
                    :content_id,
                    :external_id,
                    :data_json,
                    :fetched_at
                )
            """),
            {
                "source": "imdb",
                "content_id": content_id,
                "external_id": imdb_id,
                "data_json": json.dumps({"imdb_id": imdb_id}),
                "fetched_at": datetime.utcnow(),
            },
        )

    if tmdb_id:
        tmdb_data, tmdb_fetched_at = _fetch_external_source_payload("tmdb", tmdb_id)
        db.execute(
            text("""
                INSERT INTO content_external_source (
                    source,
                    content_id,
                    external_id,
                    data_json,
                    fetched_at
                )
                VALUES (
                    :source,
                    :content_id,
                    :external_id,
                    :data_json,
                    :fetched_at
                )
            """),
            {
                "source": "tmdb",
                "content_id": content_id,
                "external_id": tmdb_id,
                "data_json": json.dumps(tmdb_data),
                "fetched_at": tmdb_fetched_at,
            },
        )

    if tvdb_id:
        tvdb_data, tvdb_fetched_at = _fetch_external_source_payload("tvdb", tvdb_id)
        db.execute(
            text("""
                INSERT INTO content_external_source (
                    source,
                    content_id,
                    external_id,
                    data_json,
                    fetched_at
                )
                VALUES (
                    :source,
                    :content_id,
                    :external_id,
                    :data_json,
                    :fetched_at
                )
            """),
            {
                "source": "tvdb",
                "content_id": content_id,
                "external_id": tvdb_id,
                "data_json": json.dumps(tvdb_data),
                "fetched_at": tvdb_fetched_at,
            },
        )

    return content_id


def _get_or_create_content(
    db: Session,
    title: str,
    imdb_id: Optional[str],
    tmdb_id: Optional[str],
    tvdb_id: Optional[str] = None,
) -> tuple[bytes, bool]:
    if imdb_id:
        existing_content_id = _get_content_by_imdb_id(db, imdb_id)
        if existing_content_id:
            return existing_content_id, False

        return _create_content(
            db=db,
            title=title,
            imdb_id=imdb_id,
            tmdb_id=tmdb_id,
            tvdb_id=tvdb_id,
            temporary_flag=0,
        ), True

    return _create_content(
        db=db,
        title=title,
        imdb_id=None,
        tmdb_id=tmdb_id,
        tvdb_id=tvdb_id,
        temporary_flag=1,
    ), True


def _find_physical_collection_by_barcode(
    db: Session,
    barcode: Optional[str],
    box_set_barcode: Optional[str],
) -> Optional[bytes]:
    if barcode:
        row = db.execute(
            text("""
                SELECT collection_id
                FROM physical_collection
                WHERE barcode = :barcode
                LIMIT 1
            """),
            {"barcode": barcode},
        ).fetchone()
        if row:
            return row[0]

    if box_set_barcode:
        row = db.execute(
            text("""
                SELECT collection_id
                FROM physical_collection
                WHERE box_set_barcode = :box_set_barcode
                  AND barcode IS NULL
                LIMIT 1
            """),
            {"box_set_barcode": box_set_barcode},
        ).fetchone()
        if row:
            return row[0]

    return None


def _create_physical_collection(
    db: Session,
    format_value: Optional[str],
    barcode: Optional[str],
    box_set_barcode: Optional[str],
) -> bytes:
    collection_id = _new_uuid_bytes()

    db.execute(
        text("""
            INSERT INTO physical_collection (
                collection_id,
                barcode,
                format,
                box_set_barcode
            )
            VALUES (
                :collection_id,
                :barcode,
                :format,
                :box_set_barcode
            )
        """),
        {
            "collection_id": collection_id,
            "barcode": barcode,
            "format": format_value,
            "box_set_barcode": box_set_barcode,
        },
    )

    return collection_id


def _ensure_content_in_collection(
    db: Session,
    collection_id: bytes,
    content_id: bytes,
    box_set_title_sort: int,
) -> None:
    row = db.execute(
        text("""
            SELECT 1
            FROM content_in_physical_collection
            WHERE collection_id = :collection_id
              AND content_id = :content_id
            LIMIT 1
        """),
        {
            "collection_id": collection_id,
            "content_id": content_id,
        },
    ).fetchone()

    if row:
        return

    db.execute(
        text("""
            INSERT INTO content_in_physical_collection (
                collection_id,
                content_id,
                box_set_title_sort
            )
            VALUES (
                :collection_id,
                :content_id,
                :box_set_title_sort
            )
        """),
        {
            "collection_id": collection_id,
            "content_id": content_id,
            "box_set_title_sort": box_set_title_sort,
        },
    )


def _ensure_physical_copy(
    db: Session,
    collection_id: bytes,
    copy_id: int,
) -> None:
    row = db.execute(
        text("""
            SELECT 1
            FROM physical_copy
            WHERE collection_id = :collection_id
              AND copy_id = :copy_id
            LIMIT 1
        """),
        {
            "collection_id": collection_id,
            "copy_id": copy_id,
        },
    ).fetchone()

    if row:
        return

    db.execute(
        text("""
            INSERT INTO physical_copy (
                copy_id,
                collection_id
            )
            VALUES (
                :copy_id,
                :collection_id
            )
        """),
        {
            "copy_id": copy_id,
            "collection_id": collection_id,
        },
    )


def _create_disc(
    db: Session,
    type_disc: str,
    format_value: str,
    label: Optional[str],
) -> bytes:
    disc_id = _new_uuid_bytes()

    db.execute(
        text("""
            INSERT INTO disc (
                disc_id,
                type_disc,
                format,
                label
            )
            VALUES (
                :disc_id,
                :type_disc,
                :format,
                :label
            )
        """),
        {
            "disc_id": disc_id,
            "type_disc": type_disc,
            "format": format_value,
            "label": label,
        },
    )

    return disc_id


def _create_disc_in(
    db: Session,
    collection_id: bytes,
    copy_id: int,
    disc_id: bytes,
    box_set_disc_order: Optional[int],
    related_content_id: Optional[bytes],
) -> None:
    db.execute(
        text("""
            INSERT INTO disc_in (
                copy_id,
                collection_id,
                disc_id,
                box_set_disc_order,
                related_content_id
            )
            VALUES (
                :copy_id,
                :collection_id,
                :disc_id,
                :box_set_disc_order,
                :related_content_id
            )
        """),
        {
            "copy_id": copy_id,
            "collection_id": collection_id,
            "disc_id": disc_id,
            "box_set_disc_order": box_set_disc_order,
            "related_content_id": related_content_id,
        },
    )


def _set_disc_related_content(
    db: Session,
    disc_id: bytes,
    content_ids: list[bytes],
) -> None:
    for content_id in content_ids:
        db.execute(
            text("""
                INSERT INTO disc_related_content (disc_id, content_id)
                VALUES (:disc_id, :content_id)
            """),
            {"disc_id": disc_id, "content_id": content_id},
        )


def _create_bonus_items(
    db: Session,
    disc_id: bytes,
    bonus_items: list[dict],
) -> int:
    created = 0

    for idx, item in enumerate(bonus_items, start=1):
        seq_no = item.get("seq_no", idx)

        db.execute(
            text("""
                INSERT INTO disc_bonus_item (
                    disc_id,
                    seq_no,
                    title,
                    item_type,
                    runtime_seconds,
                    notes
                )
                VALUES (
                    :disc_id,
                    :seq_no,
                    :title,
                    :item_type,
                    :runtime_seconds,
                    :notes
                )
            """),
            {
                "disc_id": disc_id,
                "seq_no": seq_no,
                "title": item.get("title"),
                "item_type": item.get("item_type"),
                "runtime_seconds": item.get("runtime_seconds"),
                "notes": item.get("notes"),
            },
        )
        created += 1

    return created


def _get_storage_max_slot(db: Session, storage_id: bytes) -> int:
    row = db.execute(
        text("""
            SELECT COALESCE(MAX(number_in_storage), 0)
            FROM disc_in_storage
            WHERE storage_id = :storage_id
        """),
        {"storage_id": storage_id},
    ).fetchone()

    return int(row[0] or 0)


def _create_disc_in_storage(
    db: Session,
    storage_id: bytes,
    disc_id: bytes,
    number_in_storage: int,
) -> None:
    db.execute(
        text("""
            INSERT INTO disc_in_storage (
                storage_id,
                disc_id,
                number_in_storage
            )
            VALUES (
                :storage_id,
                :disc_id,
                :number_in_storage
            )
        """),
        {
            "storage_id": storage_id,
            "disc_id": disc_id,
            "number_in_storage": number_in_storage,
        },
    )


def _default_single_disc_label(title: str, type_disc: str, disc_no: int) -> str:
    if type_disc == "feature":
        if disc_no == 1:
            return f"{title} – Movie"
        return f"{title} – Movie Disc {disc_no}"
    if type_disc == "bonus":
        return f"{title} – Bonus"
    return f"{title} – {type_disc}"


def _default_box_disc_label(
    type_disc: str,
    disc_order: int,
    related_title: Optional[str],
) -> str:
    if related_title:
        if type_disc == "feature":
            return f"{related_title} – Movie"
        if type_disc == "bonus":
            return f"{related_title} – Bonus"
        return f"{related_title} – {type_disc}"

    if type_disc == "bonus":
        return "Box-set – Bonus"
    if type_disc == "feature":
        return f"Box-set – Feature Disc {disc_order}"
    return f"Box-set – {type_disc}"


def import_singles_payload(db: Session, payload: dict) -> dict:
    imported_rows = 0
    created_contents = 0
    created_collections = 0
    created_discs = 0
    created_bonus_items = 0

    default_copy_count = payload.get("default_copy_count", 1)
    payload_storage_id = _uuid_str_to_bytes(payload.get("storage_id"))
    storage_next_slot_cache: dict[bytes, int] = {}

    for row in payload.get("rows", []):
        title = row["title"]
        format_value = row.get("format")
        barcode = row.get("barcode")
        imdb_id = row.get("imdb_id")
        tmdb_id = row.get("tmdb_id")
        tvdb_id = row.get("tvdb_id")
        discs = row.get("discs", [])

        existing_collection_id = _find_physical_collection_by_barcode(
            db=db,
            barcode=barcode,
            box_set_barcode=None,
        )
        if existing_collection_id:
            raise ValueError(f"Single release with barcode {barcode} already exists")

        content_id, was_created = _get_or_create_content(
            db=db,
            title=title,
            imdb_id=imdb_id,
            tmdb_id=tmdb_id,
            tvdb_id=tvdb_id,
        )
        if was_created:
            created_contents += 1

        collection_id = _create_physical_collection(
            db=db,
            format_value=format_value,
            barcode=barcode,
            box_set_barcode=None,
        )
        created_collections += 1

        _ensure_content_in_collection(
            db=db,
            collection_id=collection_id,
            content_id=content_id,
            box_set_title_sort=1,
        )

        for copy_id in range(1, default_copy_count + 1):
            _ensure_physical_copy(
                db=db,
                collection_id=collection_id,
                copy_id=copy_id,
            )

            for disc_no, disc_payload in enumerate(discs, start=1):
                label = _normalize_label(disc_payload.get("label"))
                if not label:
                    label = _default_single_disc_label(
                        title=title,
                        type_disc=disc_payload["type_disc"],
                        disc_no=disc_no,
                    )

                disc_id = _create_disc(
                    db=db,
                    type_disc=disc_payload["type_disc"],
                    format_value=disc_payload["format"],
                    label=label,
                )
                created_discs += 1

                _create_disc_in(
                    db=db,
                    collection_id=collection_id,
                    copy_id=copy_id,
                    disc_id=disc_id,
                    box_set_disc_order=disc_no,
                    related_content_id=content_id,
                )

                _set_disc_related_content(
                    db=db,
                    disc_id=disc_id,
                    content_ids=[content_id],
                )

                created_bonus_items += _create_bonus_items(
                    db=db,
                    disc_id=disc_id,
                    bonus_items=disc_payload.get("bonus_items", []),
                )

                add_to_storage = bool(disc_payload.get("add_to_storage", False))
                storage_slot_no = disc_payload.get("storage_slot_no")

                if add_to_storage:
                    if payload_storage_id is None:
                        raise ValueError(
                            f"Disc for '{title}' has add_to_storage=true, "
                            "but no storage_id is defined on payload"
                        )

                    if storage_slot_no is not None:
                        assigned_slot = storage_slot_no
                    else:
                        if payload_storage_id not in storage_next_slot_cache:
                            current_max = _get_storage_max_slot(db, payload_storage_id)
                            storage_next_slot_cache[payload_storage_id] = current_max + 1

                        assigned_slot = storage_next_slot_cache[payload_storage_id]
                        storage_next_slot_cache[payload_storage_id] += 1

                    _create_disc_in_storage(
                        db=db,
                        storage_id=payload_storage_id,
                        disc_id=disc_id,
                        number_in_storage=assigned_slot,
                    )

        imported_rows += 1

    return {
        "status": "ok",
        "kind": "singles",
        "imported_rows": imported_rows,
        "created_contents": created_contents,
        "created_collections": created_collections,
        "created_discs": created_discs,
        "created_bonus_items": created_bonus_items,
    }


def import_box_sets_bulk_payload(db: Session, payload: dict) -> dict:
    imported_box_sets = 0
    created_contents = 0
    created_collections = 0
    created_discs = 0
    created_bonus_items = 0

    payload_storage_id = _uuid_str_to_bytes(payload.get("storage_id"))
    storage_next_slot_cache: dict[bytes, int] = {}

    for box_set in payload.get("box_sets", []):
        box_set_barcode = box_set.get("box_set_barcode")
        box_set_storage_id = _uuid_str_to_bytes(box_set.get("storage_id"))
        effective_storage_id = (
            box_set_storage_id if box_set_storage_id is not None else payload_storage_id
        )

        format_value = box_set.get("format")
        copy_count = box_set.get("copy_count", 1)
        movies = box_set.get("movies", [])
        discs = box_set.get("discs", [])

        existing_box_collection_id = _find_physical_collection_by_barcode(
            db=db,
            barcode=None,
            box_set_barcode=box_set_barcode,
        )
        if existing_box_collection_id and box_set_barcode is not None:
            raise ValueError(f"Box set with barcode {box_set_barcode} already exists")

        box_collection_id = _create_physical_collection(
            db=db,
            format_value=format_value,
            barcode=None,
            box_set_barcode=box_set_barcode,
        )
        created_collections += 1

        for copy_id in range(1, copy_count + 1):
            _ensure_physical_copy(
                db=db,
                collection_id=box_collection_id,
                copy_id=copy_id,
            )

        movie_content_ids: list[bytes] = []
        inner_case_collection_ids: list[Optional[bytes]] = []

        ordered_movies = sorted(movies, key=lambda m: m["order"])

        for movie in ordered_movies:
            title = movie["title"]
            imdb_id = movie.get("imdb_id")
            tmdb_id = movie.get("tmdb_id")
            tvdb_id = movie.get("tvdb_id")
            inner_case_ean = movie.get("inner_case_ean")
            treat_as_single = movie.get("treat_as_single", False)

            content_id, was_created = _get_or_create_content(
                db=db,
                title=title,
                imdb_id=imdb_id,
                tmdb_id=tmdb_id,
                tvdb_id=tvdb_id,
            )
            if was_created:
                created_contents += 1

            movie_content_ids.append(content_id)

            _ensure_content_in_collection(
                db=db,
                collection_id=box_collection_id,
                content_id=content_id,
                box_set_title_sort=movie["order"],
            )

            inner_collection_id = None
            if treat_as_single or inner_case_ean:
                if not inner_case_ean:
                    raise ValueError(
                        f"Movie '{title}' is marked as treat_as_single but has no inner_case_ean"
                    )

                existing_inner_id = _find_physical_collection_by_barcode(
                    db=db,
                    barcode=inner_case_ean,
                    box_set_barcode=None,
                )
                if existing_inner_id:
                    inner_collection_id = existing_inner_id
                else:
                    inner_collection_id = _create_physical_collection(
                        db=db,
                        format_value=format_value,
                        barcode=inner_case_ean,
                        box_set_barcode=box_set_barcode,
                    )
                    created_collections += 1

                _ensure_content_in_collection(
                    db=db,
                    collection_id=inner_collection_id,
                    content_id=content_id,
                    box_set_title_sort=1,
                )

                for copy_id in range(1, copy_count + 1):
                    _ensure_physical_copy(
                        db=db,
                        collection_id=inner_collection_id,
                        copy_id=copy_id,
                    )

            inner_case_collection_ids.append(inner_collection_id)

        ordered_discs = sorted(discs, key=lambda d: d["order"])

        for disc_payload in ordered_discs:
            # related_indexes (liste) er ny og gjeldende form; related_index
            # (enkelttall) er den gamle formen og støttes fortsatt for
            # bakoverkompatibilitet med tidligere lagrede payloads.
            related_indexes_raw = disc_payload.get("related_indexes")
            if related_indexes_raw is None:
                legacy_related_index = disc_payload.get("related_index")
                related_indexes = [legacy_related_index] if legacy_related_index is not None else []
            else:
                related_indexes = list(related_indexes_raw)

            related_title = disc_payload.get("related_title")
            label = _normalize_label(disc_payload.get("label"))
            add_to_storage = bool(disc_payload.get("add_to_storage", False))
            storage_slot_no = disc_payload.get("storage_slot_no")

            for idx in related_indexes:
                if idx < 0 or idx >= len(movie_content_ids):
                    raise ValueError(
                        f"Disc order {disc_payload['order']} has invalid related index={idx}"
                    )

            if not related_indexes:
                target_collection_id = box_collection_id
                related_content_ids: list[bytes] = []
            else:
                # Hvis (minst) én av de relaterte filmene har sin egen
                # innerkasse (treat_as_single/inner_case_ean), havner
                # discen der. Hvis ikke, ligger discen direkte i
                # boks-samlingen (helt vanlig for boks-sett uten egne
                # innerkasser, eller for plater som deles av flere
                # filmer), men beholder likevel koblingen til riktig
                # film/filmer via disc_related_content.
                target_collection_id = box_collection_id
                for idx in related_indexes:
                    inner_collection_id = inner_case_collection_ids[idx]
                    if inner_collection_id is not None:
                        target_collection_id = inner_collection_id
                        break

                related_content_ids = [movie_content_ids[idx] for idx in related_indexes]

            if not label:
                label = _default_box_disc_label(
                    type_disc=disc_payload["type_disc"],
                    disc_order=disc_payload["order"],
                    related_title=related_title,
                )

            for copy_id in range(1, copy_count + 1):
                disc_id = _create_disc(
                    db=db,
                    type_disc=disc_payload["type_disc"],
                    format_value=disc_payload["format"],
                    label=label,
                )
                created_discs += 1

                _create_disc_in(
                    db=db,
                    collection_id=target_collection_id,
                    copy_id=copy_id,
                    disc_id=disc_id,
                    box_set_disc_order=disc_payload["order"],
                    related_content_id=related_content_ids[0] if related_content_ids else None,
                )

                if related_content_ids:
                    _set_disc_related_content(
                        db=db,
                        disc_id=disc_id,
                        content_ids=related_content_ids,
                    )

                created_bonus_items += _create_bonus_items(
                    db=db,
                    disc_id=disc_id,
                    bonus_items=disc_payload.get("bonus_items", []),
                )

                if add_to_storage:
                    if effective_storage_id is None:
                        raise ValueError(
                            f"Disc order {disc_payload['order']} has add_to_storage=true, "
                            "but no storage_id is defined on payload or box set"
                        )

                    if storage_slot_no is not None:
                        assigned_slot = storage_slot_no
                    else:
                        if effective_storage_id not in storage_next_slot_cache:
                            current_max = _get_storage_max_slot(db, effective_storage_id)
                            storage_next_slot_cache[effective_storage_id] = current_max + 1

                        assigned_slot = storage_next_slot_cache[effective_storage_id]
                        storage_next_slot_cache[effective_storage_id] += 1

                    _create_disc_in_storage(
                        db=db,
                        storage_id=effective_storage_id,
                        disc_id=disc_id,
                        number_in_storage=assigned_slot,
                    )

        imported_box_sets += 1

    return {
        "status": "ok",
        "kind": "box_sets_bulk",
        "imported_box_sets": imported_box_sets,
        "created_contents": created_contents,
        "created_collections": created_collections,
        "created_discs": created_discs,
        "created_bonus_items": created_bonus_items,
    }


def import_physical_collection_payload(db: Session, payload: dict) -> dict:
    kind = payload.get("kind")

    try:
        if kind == "singles":
            result = import_singles_payload(db, payload)
        elif kind == "box_sets_bulk":
            result = import_box_sets_bulk_payload(db, payload)
        else:
            raise ValueError(f"Unsupported payload kind: {kind}")

        db.commit()
        return result
    except Exception:
        db.rollback()
        raise
