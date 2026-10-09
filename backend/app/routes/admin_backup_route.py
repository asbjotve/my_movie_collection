"""
GET  /admin/backup/download
POST /admin/backup/restore

"Sikkerhetskopiering" - se admin_tilganger.php (v19) og
app/services/admin_backup.py for selve dump/restore-logikken.

Krever innlogging (require_role("admin")). POST krever i tillegg en
eksakt bekreftelsesfrase i skjemafeltet "confirm"
(RESTORE_CONFIRMATION_PHRASE), samme mønster som "Nullstill alle data"
(se admin_reset_route.py) - restore overskriver ALL eksisterende data
i db_mediearkiv permanent og kan ikke angres.
"""

import os

from fastapi import APIRouter, Depends, File, Form, HTTPException, UploadFile
from fastapi.responses import FileResponse
from starlette.background import BackgroundTask

from app.db import User
from app.security import require_role
from app.services.admin_backup import (
    BackupError,
    backup_filename,
    create_backup_file,
    restore_from_sql,
)

router = APIRouter(prefix="/admin", tags=["admin"])

RESTORE_CONFIRMATION_PHRASE = "GJENOPPRETT DATABASE"


@router.get("/backup/download")
async def get_backup_download(
    current_user: User = Depends(require_role("admin")),
):
    try:
        tmp_path = create_backup_file()
    except BackupError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))

    return FileResponse(
        path=tmp_path,
        media_type="application/sql",
        filename=backup_filename(),
        background=BackgroundTask(os.unlink, tmp_path),
    )


@router.post("/backup/restore")
async def post_backup_restore(
    confirm: str = Form(...),
    file: UploadFile = File(...),
    current_user: User = Depends(require_role("admin")),
):
    if confirm.strip() != RESTORE_CONFIRMATION_PHRASE:
        raise HTTPException(
            status_code=400,
            detail=f"Bekreftelsesfrasen stemmer ikke - skriv nøyaktig «{RESTORE_CONFIRMATION_PHRASE}».",
        )

    sql_bytes = await file.read()
    if not sql_bytes:
        raise HTTPException(status_code=400, detail="Filen er tom.")

    try:
        restore_from_sql(sql_bytes)
    except BackupError as e:
        raise HTTPException(status_code=e.status_code, detail=str(e))

    return {"status": "ok"}
