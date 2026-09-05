"""
GET/PUT /settings/default-language

Lar en admin overstyre website_template_example/v19 sitt default
UI-språk direkte fra admin_tilganger.php, uten å måtte redigere .env på
serveren. Nyttig hvis prosjektet en dag deles/videreformidles til andre
- da trenger ikke serveradministrasjon å pusle med .env for å endre
default-språk, det holder å logge inn og endre i UI-et.

Rekkefølge for hvilket språk som faktisk blir default (se
wte_default_lang() i frontend/.../v19/lang.php):
    1. Verdien herfra (hvis satt og gyldig)
    2. WTE_DEFAULT_LANG i .env (hvis satt og gyldig)
    3. "no" (hardkodet siste fallback)

GET er åpent (uten API-nøkkel/JWT) fordi lang.php må slå opp denne
verdien på HVER sidevisning, for alle besøkende, innlogget eller ikke -
verdien i seg selv er ikke sensitiv (kun en 2-bokstavs språkkode).

PUT krever innlogging OG rollen "admin" (via require_role) - samme
mønster som PUT /settings/section-access.
"""

from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from app.db import AppSetting, User, get_db
from app.schemas.app_settings import DefaultLanguageUpdateRequest
from app.security import require_role

router = APIRouter(
    prefix="/settings/default-language",
    tags=["settings"],
)

SETTING_KEY = "default_language"


@router.get("")
async def get_default_language(db: Session = Depends(get_db)) -> dict[str, str | None]:
    row = db.query(AppSetting).filter(AppSetting.setting_key == SETTING_KEY).first()
    return {"default_language": row.setting_value if row else None}


@router.put("")
async def update_default_language(
    body: DefaultLanguageUpdateRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("admin")),
) -> dict[str, str]:
    row = db.query(AppSetting).filter(AppSetting.setting_key == SETTING_KEY).first()
    if row is None:
        row = AppSetting(setting_key=SETTING_KEY, setting_value=body.default_language)
        db.add(row)
    else:
        row.setting_value = body.default_language
    db.commit()

    return {"default_language": body.default_language}
