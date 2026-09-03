from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel
from sqlalchemy.orm import Session

from app.api_key import require_api_key
from app.media_db import get_media_db
from app.services.media_catalog import (
    ContentExternalSourceError,
    backfill_tmdb_cover_images,
    get_content_by_id,
    list_content,
    list_content_covers,
    merge_content_from_source,
    set_content_cover_image,
    update_content_external_source,
)


class SetCoverImagePayload(BaseModel):
    file_path: str


router = APIRouter(
    prefix="/media",
    tags=["media-catalog"],
)


@router.get("/content", dependencies=[Depends(require_api_key)])
def get_content(db: Session = Depends(get_media_db)):
    """Alle content-rader (media-katalogen), med fysiske utgaver og
    eksterne kilder gruppert inn per rad.

    Brukes foreløpig av website_template_example v18. Holdes enkel i
    starten - ingen paginering/filtrering ennå, det kommer etter hvert
    som mer av frontend flyttes over til dette API-et.
    """
    return list_content(db)


@router.get("/content/{content_id}", dependencies=[Depends(require_api_key)])
def get_content_detail(content_id: str, db: Session = Depends(get_media_db)):
    """Én content-rad (detaljvisning), med fysiske utgaver og eksterne
    kilder. content_id er 32-tegns hex (samme form som feltet i
    /media/content sin respons).
    """
    item = get_content_by_id(db, content_id)
    if item is None:
        raise HTTPException(status_code=404, detail="Fant ikke content med denne IDen")
    return item


@router.patch("/external-source/{source}/{external_id}")
def patch_content_external_source(
    source: str,
    external_id: str,
    db: Session = Depends(get_media_db),
):
    """Oppdaterer data_json for en eksisterende content_external_source-rad,
    identifisert kun via source ('tmdb' eller 'tvdb') og external_id -
    ingen content_id og ingen body trengs.

    Henter selv de fulle detaljene fra TMDB/TVDB (server-side) og
    lagrer dem som data_json - ingen payload sendes fra klienten, siden
    en full TMDB/TVDB-respons kan bli for stor til å sende via request
    body.

    Raden må finnes fra før - hvis ikke, returneres 404. Dette
    endepunktet oppretter bevisst ikke nye rader ennå (se
    update_content_external_source() i media_catalog.py).
    """
    try:
        return update_content_external_source(
            db,
            source=source,
            external_id=external_id,
        )
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.post("/external-source/{source}/{external_id}/merge")
def merge_external_source(
    source: str,
    external_id: str,
    db: Session = Depends(get_media_db),
):
    """Fletter sist lagrede data_json for (source, external_id) inn i
    tilhørende content-rad (title, overview, runtime osv.), med mindre
    feltet står i content.locked_fields.

    Leser IKKE på nytt fra TMDB/TVDB - bruk PATCH
    /external-source/{source}/{external_id} for å hente ferske data
    først. Oppdaterer alltid last_merged_source/last_merged_at ved
    vellykket kall, selv om ingen felt faktisk ble endret (f.eks. hvis
    alt er låst), slik at det er synlig hvilken kilde/tidspunkt som sist
    ble forsøkt flettet inn.
    """
    try:
        return merge_content_from_source(db, source=source, external_id=external_id)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))


@router.post("/backfill/tmdb-covers")
def backfill_tmdb_covers(db: Session = Depends(get_media_db)):
    """Henter cover_image (posterbilde) fra TMDB for alle content-rader
    som har en source='tmdb'-kobling i content_external_source, men som
    ennå mangler cover_image (NULL) i content-tabellen.

    Rader som allerede har cover_image, eller der 'cover_image' står i
    content.locked_fields, hoppes over uten TMDB-kall. Overholder TMDB
    sin rate-grense (strupet til 35 forespørsler/sekund).

    Dette er en engangs-/etterutfyllingsjobb, ikke en del av den vanlige
    "hent/flett fra kilde"-flyten per film - kjøres manuelt ved behov
    (f.eks. etter en stor bulk-import).
    """
    return backfill_tmdb_cover_images(db)


@router.get("/content/{content_id}/covers", dependencies=[Depends(require_api_key)])
def get_content_covers(content_id: str, db: Session = Depends(get_media_db)):
    """Lister alle TMDB-postere som er tilgjengelige for en content-rad
    (hentet fra sist lagrede data_json, ingen nye TMDB-kall gjøres),
    slik at man kan velge et annet cover enn det som er satt automatisk.
    """
    result = list_content_covers(db, content_id)
    if result is None:
        raise HTTPException(status_code=404, detail="Fant ikke content med denne IDen")
    return result


@router.post("/content/{content_id}/cover")
def set_content_cover(
    content_id: str,
    payload: SetCoverImagePayload,
    db: Session = Depends(get_media_db),
):
    """Setter cover_image for en content-rad til et av posterbildene fra
    GET /content/{content_id}/covers (identifisert via TMDB sin
    file_path). Låser samtidig 'cover_image' i content.locked_fields,
    slik at senere merge/backfill fra TMDB ikke overskriver valget.
    """
    try:
        return set_content_cover_image(db, content_id, payload.file_path)
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))
