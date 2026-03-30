"""
auth.py – passord-håndtering (Argon2)

Denne fila er delt ut fra main.py for å:
- unngå at CLI-script må importere hele FastAPI-appen
- samle all passord hashing/verifisering ett sted

Bruk:
- get_password_hash("passord") -> lagrer hash i DB
- verify_password("passord", "<hash>") -> True/False
"""

from argon2 import PasswordHasher
from argon2.exceptions import VerifyMismatchError

# PasswordHasher holder konfig for Argon2 og brukes til hashing/verifisering.
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
