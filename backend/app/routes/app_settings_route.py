"""
GET/PUT /settings/default-language og /settings/default-currency

Lar en admin overstyre website_template_example/v19 sitt default
UI-språk, samt standardvaluta for kjøpspris på fysiske eksemplarer
(physical_copy.currency), direkte fra admin_tilganger.php - uten å
måtte redigere .env eller kjøre SQL på serveren. Nyttig hvis prosjektet
en dag deles/videreformidles til andre - da trenger ikke
serveradministrasjon å pusle med .env/DB for å endre disse, det holder
å logge inn og endre i UI-et.

Rekkefølge for hvilket språk som faktisk blir default (se
wte_default_lang() i frontend/.../v19/lang.php):
    1. Verdien herfra (hvis satt og gyldig)
    2. WTE_DEFAULT_LANG i .env (hvis satt og gyldig)
    3. "no" (hardkodet siste fallback)

Tilsvarende for valuta (se get_content_by_id() i media_catalog.py):
    1. physical_copy.currency (hvis satt for det spesifikke eksemplaret)
    2. Verdien herfra (default_currency-settingen, hvis satt)
    3. "NOK" (hardkodet siste fallback)

GET er åpent (uten API-nøkkel/JWT) fordi lang.php må slå opp
default-language-verdien på HVER sidevisning, for alle besøkende,
innlogget eller ikke - verdien i seg selv er ikke sensitiv. Samme
åpne GET brukes for default-currency, av konsistens (heller ikke
sensitivt, kun en 3-bokstavs valutakode).

PUT krever innlogging OG rollen "admin" (via require_role) - samme
mønster som PUT /settings/section-access.
"""

from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from app.db import AppSetting, User, get_db
from app.schemas.app_settings import (
    DefaultCurrencyUpdateRequest,
    DefaultLanguageUpdateRequest,
)
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


currency_router = APIRouter(
    prefix="/settings/default-currency",
    tags=["settings"],
)

CURRENCY_SETTING_KEY = "default_currency"


@currency_router.get("")
async def get_default_currency(db: Session = Depends(get_db)) -> dict[str, str | None]:
    row = db.query(AppSetting).filter(AppSetting.setting_key == CURRENCY_SETTING_KEY).first()
    return {"default_currency": row.setting_value if row else None}


@currency_router.put("")
async def update_default_currency(
    body: DefaultCurrencyUpdateRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("admin")),
) -> dict[str, str]:
    row = db.query(AppSetting).filter(AppSetting.setting_key == CURRENCY_SETTING_KEY).first()
    if row is None:
        row = AppSetting(setting_key=CURRENCY_SETTING_KEY, setting_value=body.default_currency)
        db.add(row)
    else:
        row.setting_value = body.default_currency
    db.commit()

    return {"default_currency": body.default_currency}
