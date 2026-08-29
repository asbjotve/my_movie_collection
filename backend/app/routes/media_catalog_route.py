from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from app.media_db import get_media_db
from app.schemas.media_catalog import ContentExternalSourceUpdatePayload
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


@router.patch("/content/{content_id}/external-source/{source}")
def patch_content_external_source(
    content_id: str,
    source: str,
    payload: ContentExternalSourceUpdatePayload,
    db: Session = Depends(get_media_db),
):
    """Oppdaterer external_id og/eller data_json for en eksisterende
    content_external_source-rad (content_id + source, f.eks. 'tmdb',
    'tvdb' eller 'imdb').

    Raden må finnes fra før - hvis ikke, returneres 404. Dette
    endepunktet oppretter bevisst ikke nye rader ennå (se
    update_content_external_source() i media_catalog.py).
    """
    try:
        return update_content_external_source(
            db,
            content_id=content_id,
            source=source,
            external_id=payload.external_id,
            data_json=payload.data_json,
        )
    except ContentExternalSourceError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))
