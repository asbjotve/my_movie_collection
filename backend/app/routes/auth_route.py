"""
auth_route.py – innlogging (JWT) + TOTP-basert 2FA.

Flyt uten 2FA:
    POST /auth/login {username, password}
    -> {access_token, requires_2fa: false}

Flyt med 2FA aktivert:
    POST /auth/login {username, password}
    -> {requires_2fa: true, pre_auth_token}          (ingen access_token ennå)
    POST /auth/login/2fa {pre_auth_token, code}
    -> {access_token}                                 (code = TOTP- ELLER recovery-kode)

Sette opp 2FA (krever at man allerede er innlogget - se get_current_user):
    POST /auth/2fa/setup            -> {secret, otpauth_uri, qr_code_data_uri}
    POST /auth/2fa/enable {code}    -> {recovery_codes}   (bekrefter med en TOTP-kode)
    POST /auth/2fa/disable {password}

GET /auth/me - info om innlogget bruker (også om 2FA er på).
"""

from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session

from app.auth import (
    generate_qr_code_data_uri,
    generate_recovery_codes,
    generate_totp_secret,
    build_totp_uri,
    verify_and_consume_recovery_code,
    verify_password,
    verify_totp_code,
)
from app.db import User, get_db
from app.security import (
    create_access_token,
    create_preauth_token,
    decode_token,
    get_current_user,
    get_user_by_username,
)
from app.schemas.auth import (
    CurrentUserResponse,
    LoginRequest,
    LoginResponse,
    TokenResponse,
    TwoFaDisableRequest,
    TwoFaEnableRequest,
    TwoFaEnableResponse,
    TwoFaLoginRequest,
    TwoFaSetupResponse,
)

router = APIRouter(prefix="/auth", tags=["auth"])


@router.post("/login", response_model=LoginResponse)
async def login(credentials: LoginRequest, db: Session = Depends(get_db)):
    """Validerer brukernavn/passord. Hvis brukeren har 2FA på, returneres
    IKKE et access_token her - kun et kortlevd pre_auth_token som må
    veksles inn mot en gyldig kode via POST /auth/login/2fa."""
    user = get_user_by_username(db, credentials.username)

    if not user or not verify_password(credentials.password, user.hashed_password):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Feil brukernavn eller passord",
            headers={"WWW-Authenticate": "Bearer"},
        )

    if not user.is_active:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Bruker er deaktivert",
        )

    if user.totp_enabled:
        return LoginResponse(
            requires_2fa=True,
            pre_auth_token=create_preauth_token(user.username),
        )

    return LoginResponse(
        access_token=create_access_token(user.username),
        requires_2fa=False,
    )


@router.post("/login/2fa", response_model=TokenResponse)
async def login_2fa(payload: TwoFaLoginRequest, db: Session = Depends(get_db)):
    """Andre steg i innlogging når 2FA er på: veksler et pre_auth_token
    + en gyldig TOTP- eller recovery-kode inn i et ekte access_token."""
    username = decode_token(payload.pre_auth_token, expected_type="preauth")
    user = get_user_by_username(db, username)

    if user is None or not user.is_active or not user.totp_enabled:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Ugyldig forespørsel")

    if verify_totp_code(user.totp_secret, payload.code):
        return TokenResponse(access_token=create_access_token(user.username))

    # Prøv som recovery-kode hvis TOTP-koden ikke matchet.
    matched, updated_codes_json = verify_and_consume_recovery_code(
        user.recovery_codes_json, payload.code
    )
    if matched:
        user.recovery_codes_json = updated_codes_json
        db.commit()
        return TokenResponse(access_token=create_access_token(user.username))

    raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Ugyldig kode")


@router.get("/me", response_model=CurrentUserResponse)
async def me(current_user: User = Depends(get_current_user)):
    return CurrentUserResponse(
        id=current_user.id,
        username=current_user.username,
        role=current_user.role,
        is_active=bool(current_user.is_active),
        totp_enabled=bool(current_user.totp_enabled),
    )


@router.post("/2fa/setup", response_model=TwoFaSetupResponse)
async def setup_2fa(
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    """Genererer et nytt TOTP-secret og lagrer det midlertidig
    (totp_secret_pending) - aktiveres først når brukeren bekrefter med
    en gyldig kode via POST /auth/2fa/enable. Kan kalles på nytt for å
    starte oppsettet på nytt (overskriver forrige pending-secret)."""
    if current_user.totp_enabled:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="2FA er allerede aktivert for denne brukeren",
        )

    secret = generate_totp_secret()
    current_user.totp_secret_pending = secret
    db.commit()

    otpauth_uri = build_totp_uri(secret, current_user.username)
    return TwoFaSetupResponse(
        secret=secret,
        otpauth_uri=otpauth_uri,
        qr_code_data_uri=generate_qr_code_data_uri(otpauth_uri),
    )


@router.post("/2fa/enable", response_model=TwoFaEnableResponse)
async def enable_2fa(
    payload: TwoFaEnableRequest,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    """Bekrefter 2FA-oppsettet: sjekker at brukeren kan generere en
    gyldig kode fra secret-et (dvs. at det ble skannet inn riktig),
    flytter det til det aktive feltet, og genererer recovery-koder."""
    if current_user.totp_enabled:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="2FA er allerede aktivert for denne brukeren",
        )

    if not current_user.totp_secret_pending:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Ingen påbegynt 2FA-oppsett - kall /auth/2fa/setup først",
        )

    if not verify_totp_code(current_user.totp_secret_pending, payload.code):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Ugyldig kode")

    plaintext_codes, hashed_codes_json = generate_recovery_codes()

    current_user.totp_secret = current_user.totp_secret_pending
    current_user.totp_secret_pending = None
    current_user.totp_enabled = 1
    current_user.recovery_codes_json = hashed_codes_json
    db.commit()

    return TwoFaEnableResponse(status="ok", recovery_codes=plaintext_codes)


@router.post("/2fa/disable")
async def disable_2fa(
    payload: TwoFaDisableRequest,
    current_user: User = Depends(get_current_user),
    db: Session = Depends(get_db),
):
    """Slår av 2FA for brukeren. Krever gjeldende passord (i tillegg til
    å allerede være innlogget) som en ekstra bekreftelse, siden dette
    reduserer sikkerheten på kontoen."""
    if not verify_password(payload.password, current_user.hashed_password):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Feil passord")

    current_user.totp_enabled = 0
    current_user.totp_secret = None
    current_user.totp_secret_pending = None
    current_user.recovery_codes_json = None
    db.commit()

    return {"status": "ok"}
