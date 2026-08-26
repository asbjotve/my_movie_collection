from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from app.media_db import get_media_db
from app.services.media_catalog import get_content_by_id, list_content

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
