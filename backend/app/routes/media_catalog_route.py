from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from app.media_db import get_media_db
from app.services.media_catalog import list_content

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
