from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from app.media_db import get_media_db
from app.schemas.physical_collection_import import (
    SinglesImportPayload,
    BoxSetsBulkImportPayload,
)
from app.services.add_data.physical_collection_import import (
    import_physical_collection_payload,
)

router = APIRouter(
    prefix="/import",
    tags=["physical-collection-import"],
)


@router.post("/physical-collection")
def import_physical_collection(
    payload: SinglesImportPayload | BoxSetsBulkImportPayload,
    db: Session = Depends(get_media_db),
):
    try:
        return import_physical_collection_payload(
            db=db,
            payload=payload.model_dump(),
        )
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
