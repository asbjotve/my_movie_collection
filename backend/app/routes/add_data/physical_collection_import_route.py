from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from app.db import User
from app.media_db import get_media_db
from app.schemas.physical_collection_import import (
    PhysicalCollectionImportPayload,
)
from app.security import get_current_user
from app.services.add_data.physical_collection_import import (
    import_physical_collection_payload,
)

router = APIRouter(
    prefix="/import",
    tags=["physical-collection-import"],
)


@router.post("/physical-collection")
def import_physical_collection(
    payload: PhysicalCollectionImportPayload,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    try:
        return import_physical_collection_payload(
            db=db,
            payload=payload.model_dump(),
        )
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
