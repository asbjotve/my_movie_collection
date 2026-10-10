"""POST /plex/import, GET /plex/import/status

Triggers one full Plex import run (step 2, see TODO.md) - manually,
via a button in the frontend (no scheduler/job queue yet). Reuses the
stored Plex credentials and selected library keys from
plex_settings_route.py, so the settings page is the single source of
truth for both "test connection" and the actual import.

IMPORTANT: the import can take several minutes (one Plex HTTP request
per movie/episode, plus occasional TMDB/TVDB lookups for brand new
items) and the backend runs with a single uvicorn worker (see
ecosystem.config.js) - running it synchronously inside the request
handler was tried first and blocked ALL other API traffic on the site
for the whole duration (confirmed in production). It therefore runs in
a plain background thread with its own DB session (NOT the
request-scoped one from Depends(get_media_db), which gets closed as
soon as this endpoint returns) - progress/result is tracked in a small
in-memory, process-global status dict that /plex/import/status polls.

This in-memory state intentionally does NOT survive a backend restart
(acceptable for a manually-triggered, single-admin-at-a-time job) -
see _import_state below.
"""

import threading
from datetime import datetime, timezone

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from app.db import User, get_db
from app.media_db import MediaSessionLocal
from app.routes.plex_settings_route import get_selected_library_keys, get_stored_plex_credentials
from app.security import require_role
from app.services.plex_import import run_plex_import

router = APIRouter(
    prefix="/plex",
    tags=["plex-import"],
)

_state_lock = threading.Lock()
_import_state: dict = {
    "status": "idle",  # idle | running | completed | failed
    "started_at": None,
    "finished_at": None,
    "progress": None,  # {"library": str, "processed": int, "total": int}
    "summary": None,
    "error": None,
}


def _run_import_in_background(base_url: str, token: str, verify_ssl: bool, selected_library_keys: list[str]) -> None:
    def on_progress(library_title: str, processed: int, total: int) -> None:
        with _state_lock:
            _import_state["progress"] = {"library": library_title, "processed": processed, "total": total}

    # Egen DB-sesjon, uavhengig av requesten som startet tråden (den er
    # for lengst lukket igjen når selve importen fortsatt pågår).
    db = MediaSessionLocal()
    try:
        summary = run_plex_import(
            db, base_url, token, verify_ssl, selected_library_keys, progress_callback=on_progress
        )
        with _state_lock:
            _import_state["status"] = "completed"
            _import_state["summary"] = summary
            _import_state["finished_at"] = datetime.now(timezone.utc).isoformat()
    except Exception as exc:  # noqa: BLE001 - må fanges her, ellers forsvinner feilen sporløst i tråden
        db.rollback()
        with _state_lock:
            _import_state["status"] = "failed"
            _import_state["error"] = str(exc)
            _import_state["finished_at"] = datetime.now(timezone.utc).isoformat()
    finally:
        db.close()


@router.post("/import")
async def import_from_plex(
    settings_db: Session = Depends(get_db),
    current_user: User = Depends(require_role("admin")),
) -> dict:
    base_url, token, verify_ssl = get_stored_plex_credentials(settings_db)
    if not base_url or not token:
        raise HTTPException(status_code=400, detail="Plex-tilkobling er ikke konfigurert ennå")

    selected_library_keys = get_selected_library_keys(settings_db)
    if not selected_library_keys:
        raise HTTPException(status_code=400, detail="Ingen bibliotek er valgt for import ennå")

    with _state_lock:
        if _import_state["status"] == "running":
            raise HTTPException(status_code=409, detail="En import kjører allerede")
        _import_state.update(
            {
                "status": "running",
                "started_at": datetime.now(timezone.utc).isoformat(),
                "finished_at": None,
                "progress": None,
                "summary": None,
                "error": None,
            }
        )

    thread = threading.Thread(
        target=_run_import_in_background,
        args=(base_url, token, verify_ssl, selected_library_keys),
        daemon=True,
    )
    thread.start()

    return {"status": "started"}


@router.get("/import/status")
async def get_import_status(
    current_user: User = Depends(require_role("admin")),
) -> dict:
    with _state_lock:
        return dict(_import_state)
