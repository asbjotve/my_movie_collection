"""
auth.py – passord-håndtering (Argon2) + TOTP-basert 2FA (pyotp)

Denne fila er delt ut fra main.py for å:
- unngå at CLI-script må importere hele FastAPI-appen
- samle all passord hashing/verifisering ett sted
- samle TOTP (tidsbasert engangskode, f.eks. Google Authenticator/Authy)
  og engangs-recovery-koder ett sted

Bruk (passord):
- get_password_hash("passord") -> lagrer hash i DB
- verify_password("passord", "<hash>") -> True/False

Bruk (2FA/TOTP):
- generate_totp_secret() -> nytt base32-secret til en bruker
- build_totp_uri(secret, username) -> otpauth://-URI for QR-kode
- generate_qr_code_data_uri(otpauth_uri) -> "data:image/png;base64,..."
- verify_totp_code(secret, code) -> True/False

Bruk (recovery codes):
- generate_recovery_codes(n=8) -> (plaintext_codes, hashed_codes_json)
  plaintext_codes vises ÉN gang til brukeren, hashed_codes_json lagres.
- verify_and_consume_recovery_code(hashed_codes_json, code) ->
  (matched: bool, updated_hashed_codes_json: str) - fjerner koden fra
  listen hvis den matchet, slik at den ikke kan brukes på nytt.
"""

import base64
import json
import secrets
import string
from io import BytesIO

import pyotp
import qrcode
from argon2 import PasswordHasher
from argon2.exceptions import VerifyMismatchError

# PasswordHasher holder konfig for Argon2 og brukes til hashing/verifisering
# (brukes til både passord og recovery-koder).
ph = PasswordHasher()


def verify_password(plain_password: str, hashed_password: str) -> bool:
    """Returnerer True hvis passordet matcher hash-en, ellers False."""
    try:
        ph.verify(hashed_password, plain_password)
        return True
    except VerifyMismatchError:
        return False


def get_password_hash(password: str) -> str:
    """Returnerer en Argon2-hash for passordet (streng som kan lagres i DB)."""
    return ph.hash(password)


# =========================================================
# TOTP (2FA)
# =========================================================

TOTP_ISSUER = "Mitt Mediearkiv"


def generate_totp_secret() -> str:
    """Genererer et nytt tilfeldig base32-secret (brukt av pyotp/TOTP-apper)."""
    return pyotp.random_base32()


def build_totp_uri(secret: str, username: str) -> str:
    """Bygger en otpauth://-URI som kan vises som QR-kode i en
    autentisator-app (Google Authenticator, Authy, osv.)."""
    return pyotp.totp.TOTP(secret).provisioning_uri(
        name=username, issuer_name=TOTP_ISSUER
    )


def generate_qr_code_data_uri(otpauth_uri: str) -> str:
    """Lager en QR-kode (PNG) av en otpauth-URI og returnerer den som en
    data-URI (base64), slik at frontend kan vise den direkte i en
    <img src="..."> uten noe eget bilde-endepunkt."""
    img = qrcode.make(otpauth_uri)
    buffer = BytesIO()
    img.save(buffer, format="PNG")
    encoded = base64.b64encode(buffer.getvalue()).decode("ascii")
    return f"data:image/png;base64,{encoded}"


def verify_totp_code(secret: str, code: str) -> bool:
    """Verifiserer en 6-sifret TOTP-kode mot secret-et. valid_window=1
    tillater at koden fra forrige/neste 30-sekunders-vindu også
    godtas, som et lite slingringsmonn for klokkeavvik."""
    if not secret or not code:
        return False
    return pyotp.totp.TOTP(secret).verify(code, valid_window=1)


# =========================================================
# Recovery codes (backup-koder ved tap av autentisator-app)
# =========================================================

RECOVERY_CODE_ALPHABET = string.ascii_uppercase + string.digits
RECOVERY_CODE_LENGTH = 10
RECOVERY_CODE_COUNT = 8


def _format_recovery_code(raw: str) -> str:
    """Formaterer en rå kode som f.eks. 'ABCD1-EFGH2' for lesbarhet."""
    half = len(raw) // 2
    return f"{raw[:half]}-{raw[half:]}"


def generate_recovery_codes(count: int = RECOVERY_CODE_COUNT) -> tuple[list[str], str]:
    """Genererer et sett med engangs-recovery-koder.

    Returnerer (plaintext_codes, hashed_codes_json):
    - plaintext_codes vises til brukeren ÉN gang (må lagres et trygt sted).
    - hashed_codes_json er en JSON-liste med Argon2-hasher av kodene,
      klar til å lagres i users.recovery_codes_json.
    """
    plaintext_codes = [
        _format_recovery_code(
            "".join(secrets.choice(RECOVERY_CODE_ALPHABET) for _ in range(RECOVERY_CODE_LENGTH))
        )
        for _ in range(count)
    ]
    hashed_codes = [ph.hash(code) for code in plaintext_codes]
    return plaintext_codes, json.dumps(hashed_codes)


def verify_and_consume_recovery_code(
    hashed_codes_json: str | None, code: str
) -> tuple[bool, str | None]:
    """Sjekker om 'code' matcher en av de lagrede (hashede) recovery-
    kodene. Hvis den matcher, fjernes den fra listen (kan ikke brukes
    på nytt) og den oppdaterte JSON-strengen returneres.

    Returnerer (matched, updated_hashed_codes_json). Hvis ingen match,
    returneres (False, hashed_codes_json) uendret.
    """
    if not hashed_codes_json or not code:
        return False, hashed_codes_json

    hashed_codes: list[str] = json.loads(hashed_codes_json)
    normalized = code.strip().upper()

    for hashed in hashed_codes:
        try:
            ph.verify(hashed, normalized)
        except VerifyMismatchError:
            continue
        remaining = [h for h in hashed_codes if h != hashed]
        return True, json.dumps(remaining)

    return False, hashed_codes_json

