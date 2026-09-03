"""Pydantic-schemas for /auth-endepunktene (login + 2FA)."""

from pydantic import BaseModel


class LoginRequest(BaseModel):
    username: str
    password: str


class LoginResponse(BaseModel):
    """Svar på POST /auth/login.

    Hvis brukeren IKKE har 2FA på: access_token er satt med en gang,
    requires_2fa=False, pre_auth_token=None.

    Hvis brukeren HAR 2FA på: access_token=None, requires_2fa=True, og
    pre_auth_token må sendes videre til POST /auth/login/2fa sammen med
    en gyldig TOTP-/recovery-kode for å få et ekte access_token.
    """

    access_token: str | None = None
    token_type: str = "bearer"
    requires_2fa: bool = False
    pre_auth_token: str | None = None


class TwoFaLoginRequest(BaseModel):
    pre_auth_token: str
    code: str


class TokenResponse(BaseModel):
    access_token: str
    token_type: str = "bearer"


class TwoFaSetupResponse(BaseModel):
    """Svar på POST /auth/2fa/setup - secret + QR-kode til å skanne inn
    i en autentisator-app (Google Authenticator, Authy, osv.)."""

    secret: str
    otpauth_uri: str
    qr_code_data_uri: str


class TwoFaEnableRequest(BaseModel):
    code: str


class TwoFaEnableResponse(BaseModel):
    """Recovery-kodene vises kun i dette ene svaret - de lagres kun
    hashet i databasen og kan ikke hentes fram igjen senere."""

    status: str
    recovery_codes: list[str]


class TwoFaDisableRequest(BaseModel):
    password: str


class CurrentUserResponse(BaseModel):
    id: int
    username: str
    role: str
    is_active: bool
    totp_enabled: bool
