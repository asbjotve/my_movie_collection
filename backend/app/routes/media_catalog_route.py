from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from app.media_db import get_media_db
from app.services.media_catalog import (
    ContentExternalSourceError,
    get_content_by_id,
    list_content,
    update_content_external_source,
)

router = APIRouter(
    prefix="/media",
    tags=["media-catalog"],
)


@router.get("/content")
def get_content(db: Session = Depends(get_media_db)):
    """Alle content-rader (media-katalogen), med fysiske utgaver og
    eksterne kilder gruppert inn per rad.

    Brukes foreløpig av website_template_example v18. Holdes enkel i
    starten - ingen paginering/filtrering ennå, det kommer etter hvert
    som mer av frontend flyttes over til dette API-et.
    """
    return list_content(db)


@router.get("/content/{content_id}")
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
