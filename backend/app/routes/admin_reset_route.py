"""
GET  /admin/reset-collection-data/preview
POST /admin/reset-collection-data

"Nullstill alle data" - se admin_tilganger.php (v19) og
app/services/admin_reset.py for selve slette-logikken/tabell-listen.

Krever innlogging (require_role("admin") - i dag finnes kun én rolle,
se security.py). POST krever i tillegg en eksakt bekreftelsesfrase i
request-bodyen (RESET_CONFIRMATION_PHRASE) som en ekstra sperre mot at
et feilklikk eller en automatisert test trigger slettingen ved et
uhell - frontend ber i tillegg brukeren skrive inn frasen i en egen
bekreftelsesdialog (med forhåndsvisning av antall rader som slettes)
rett før kallet sendes, se admin_tilganger.php.
"""

from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel
from sqlalchemy.orm import Session

from app.db import User
from app.media_db import get_media_db
from app.security import require_role
from app.services.admin_reset import (
    PRESERVED_TABLES,
    preview_reset_counts,
    reset_all_collection_data,
)

router = APIRouter(prefix="/admin", tags=["admin"])

RESET_CONFIRMATION_PHRASE = "NULLSTILL ALT"


class ResetConfirmRequest(BaseModel):
    confirm: str


@router.get("/reset-collection-data/preview")
async def get_reset_preview(
    current_user: User = Depends(require_role("admin")),
    db: Session = Depends(get_media_db),
) -> dict:
    counts = preview_reset_counts(db)
    return {
        "counts": counts,
        "total": sum(counts.values()),
        "preserved_tables": list(PRESERVED_TABLES),
        "confirmation_phrase": RESET_CONFIRMATION_PHRASE,
    }


@router.post("/reset-collection-data")
async def post_reset_collection_data(
    body: ResetConfirmRequest,
    current_user: User = Depends(require_role("admin")),
    db: Session = Depends(get_media_db),
) -> dict:
    if body.confirm.strip() != RESET_CONFIRMATION_PHRASE:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Bekreftelsesfrasen stemmer ikke - ingenting ble slettet.",
        )

    deleted = reset_all_collection_data(db)
    return {
        "deleted": deleted,
        "total_deleted": sum(deleted.values()),
        "preserved_tables": list(PRESERVED_TABLES),
    }
