"""
api_key.py – enkel dependency som beskytter lese-endepunkter som ikke
krever en innlogget bruker (f.eks. filmkatalog/lister vist på den
offentlige nettsiden), men som heller ikke skal være helt åpne for
hvem som helst på internett.

Klienten (frontend sin PHP-backend, server-til-server) må sende
header "X-API-Key" med samme verdi som INTERNAL_API_KEY i .env.

Dette er IKKE en erstatning for JWT/get_current_user - skrive-
/admin-endepunkter skal fortsatt kreve en innlogget bruker.
"""

import secrets

from fastapi import Header, HTTPException, status

from config.config import settings

INTERNAL_API_KEY = settings.INTERNAL_API_KEY
if not INTERNAL_API_KEY:
    raise ValueError("INTERNAL_API_KEY må være satt i config/.env filen!")


async def require_api_key(x_api_key: str | None = Header(default=None)) -> None:
    """Dependency for lese-endepunkter. Kaster HTTPException(401) hvis
    riktig X-API-Key-header mangler. Bruker secrets.compare_digest for
    å unngå timing-angrep."""
    if not x_api_key or not secrets.compare_digest(x_api_key, INTERNAL_API_KEY):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Mangler eller ugyldig API-nøkkel",
        )
