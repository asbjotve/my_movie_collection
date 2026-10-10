from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from app.db import User
from app.media_db import get_media_db
from app.schemas.physical_collection_import import (
    PhysicalCollectionImportPayload,
    ResolvePendingDuplicatePayload,
)
from app.security import get_current_user
from app.services.add_data.physical_collection_import import (
    discard_pending_duplicate,
    import_physical_collection_payload,
    list_pending_duplicates,
    resolve_pending_duplicate_as_copy,
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


@router.get("/physical-collection/pending")
def get_pending_duplicate_imports(
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    """Rows queued by import_singles_payload() because their barcode
    matched an already-registered release - see duplicate_review.php."""
    return {"items": list_pending_duplicates(db)}


@router.post("/physical-collection/pending/{pending_id}/resolve")
def resolve_pending_duplicate_import(
    pending_id: int,
    payload: ResolvePendingDuplicatePayload,
    db: Session = Depends(get_media_db),
    current_user: User = Depends(get_current_user),
):
    try:
        if payload.action == "add_copy":
            return resolve_pending_duplicate_as_copy(db, pending_id)
        return discard_pending_duplicate(db, pending_id)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))
