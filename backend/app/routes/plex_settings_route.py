"""GET/PUT /settings/plex og POST /settings/plex/test

Lar en admin sette Plex Media Server sin URL og X-Plex-Token direkte
fra en egen side i frontend (plex_settings.php), i stedet for en
.env-variabel på serveren - første steg mot den planlagte
Plex-importen (se TODO.md, "digital library"-tabellene kommer i en
senere omgang).

Credentials lagres i samme generiske app_settings-tabell (mmc_userdb)
som default-language/default-currency bruker - se AppSetting i db.py.
Lagres i klartekst der, samme risikoprofil som de andre verdiene i den
tabellen - OBS: tokenet gir full lesetilgang til hele Plex-biblioteket,
så GET returnerer aldri selve tokenet i klartekst (kun om det er satt
+ de 4 siste tegnene, til hjelp for å bekrefte man endrer riktig verdi).

Både GET og PUT krever innlogging OG rollen "admin" (require_role) -
ulikt default-language/default-currency sin GET (som bevisst er åpen
for alle besøkende) - her er selve eksistensen av en lagret URL/token
mer sensitiv, så vi holder begge lukket.
"""

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from app.db import AppSetting, User, get_db
from app.schemas.plex_settings import PlexSettingsUpdateRequest
from app.security import require_role
from app.services.plex_client import PlexConnectionError, test_plex_connection

router = APIRouter(
    prefix="/settings/plex",
    tags=["settings"],
)

BASE_URL_KEY = "plex_base_url"
TOKEN_KEY = "plex_token"
SERVER_IDENTIFIER_KEY = "plex_server_identifier"
VERIFY_SSL_KEY = "plex_verify_ssl"


def _get_setting(db: Session, key: str) -> str | None:
    row = db.query(AppSetting).filter(AppSetting.setting_key == key).first()
    return row.setting_value if row else None


def _set_setting(db: Session, key: str, value: str | None) -> None:
    row = db.query(AppSetting).filter(AppSetting.setting_key == key).first()
    if row is None:
        row = AppSetting(setting_key=key, setting_value=value)
        db.add(row)
    else:
        row.setting_value = value


@router.get("")
async def get_plex_settings(
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("admin")),
) -> dict:
    base_url = _get_setting(db, BASE_URL_KEY)
    token = _get_setting(db, TOKEN_KEY)
    verify_ssl_raw = _get_setting(db, VERIFY_SSL_KEY)
    return {
        "base_url": base_url,
        "token_set": bool(token),
        "token_last4": token[-4:] if token and len(token) >= 4 else None,
        "server_identifier": _get_setting(db, SERVER_IDENTIFIER_KEY),
        # Mangler raden -> default True (streng verifisering), se schema.
        "verify_ssl": verify_ssl_raw != "0",
    }


@router.put("")
async def update_plex_settings(
    body: PlexSettingsUpdateRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("admin")),
) -> dict:
    _set_setting(db, BASE_URL_KEY, body.base_url)
    if body.token is not None:
        _set_setting(db, TOKEN_KEY, body.token)
    _set_setting(db, SERVER_IDENTIFIER_KEY, body.server_identifier)
    _set_setting(db, VERIFY_SSL_KEY, "1" if body.verify_ssl else "0")
    db.commit()

    token = _get_setting(db, TOKEN_KEY)
    return {
        "base_url": body.base_url,
        "token_set": bool(token),
        "token_last4": token[-4:] if token and len(token) >= 4 else None,
        "server_identifier": body.server_identifier,
        "verify_ssl": body.verify_ssl,
    }


@router.post("/test")
async def test_plex_settings(
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("admin")),
) -> dict:
    """Tester de FAKTISK LAGREDE credentials (ikke en payload fra
    requesten) - unngår at et ulagret token ved et uhell blir sendt
    til en tredjepart via et skrivefeil-url, og sikrer at "fungerer"
    faktisk betyr "det som er lagret fungerer", ikke bare det som
    står i skjemaet akkurat nå (som jo kan avvike hvis brukeren ikke
    har trykket "Lagre" ennå).
    """

    base_url = _get_setting(db, BASE_URL_KEY)
    token = _get_setting(db, TOKEN_KEY)
    server_identifier = _get_setting(db, SERVER_IDENTIFIER_KEY)
    verify_ssl = _get_setting(db, VERIFY_SSL_KEY) != "0"

    try:
        result = test_plex_connection(base_url or "", token or "", server_identifier, verify_ssl)
    except PlexConnectionError as exc:
        raise HTTPException(status_code=502, detail=str(exc)) from exc

    return {"ok": True, **result}
