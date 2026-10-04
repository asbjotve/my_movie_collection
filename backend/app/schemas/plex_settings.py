"""Pydantic-schema for PUT /settings/plex.

Lagrer kun tilkoblingsinfo (server-URL + X-Plex-Token) - selve
import-logikken (digital_library-tabellene osv., se TODO.md) bygges i
en senere omgang. Dette er bevisst det første steget: få credentials
konfigurerbare fra UI-et i stedet for en ny .env-variabel, slik
brukeren ba om.
"""

import re

from pydantic import BaseModel, field_validator

# Enkel sjekk på at det faktisk ligner en URL (http/https + host) - vi
# validerer ikke at serveren faktisk finnes her, det gjøres av
# "Test tilkobling"-knappen (POST /settings/plex/test).
BASE_URL_PATTERN = re.compile(r"^https?://[^\s]+$")


class PlexSettingsUpdateRequest(BaseModel):
    """Body for PUT /settings/plex.

    `token` er valgfri med vilje: sendes den tom/utelatt, beholdes
    eksisterende lagret token uendret - slik slipper man å skrive inn
    tokenet på nytt bare for å endre URL-en (og GET /settings/plex
    returnerer aldri selve tokenet i klartekst igjen, se routen).
    """

    base_url: str
    token: str | None = None
    # Plex sin "machineIdentifier" for serveren - valgfri, brukes kun til
    # å bekrefte (ved "Test tilkobling") at man faktisk snakker med
    # RIKTIG server og ikke en annen Plex-server som tilfeldigvis
    # godtar samme token (f.eks. hvis man har flere servere på kontoen).
    server_identifier: str | None = None
    # Default True (streng sertifikatverifisering). Settes til False av
    # brukere som kobler til via et eget DDNS/hostnavn Plex sitt
    # TLS-sertifikat ikke er utstedt for (Plex sitt sertifikat er kun
    # gyldig for dets egne "*.plex.direct"-adresser) - se plex_client.py.
    verify_ssl: bool = True

    @field_validator("base_url")
    @classmethod
    def validate_base_url(cls, value: str) -> str:
        value = value.strip().rstrip("/")
        if not BASE_URL_PATTERN.match(value):
            raise ValueError("base_url må være en gyldig http(s)-URL, f.eks. http://192.168.1.10:32400")
        return value

    @field_validator("token")
    @classmethod
    def validate_token(cls, value: str | None) -> str | None:
        if value is None:
            return None
        value = value.strip()
        return value or None

    @field_validator("server_identifier")
    @classmethod
    def validate_server_identifier(cls, value: str | None) -> str | None:
        if value is None:
            return None
        value = value.strip()
        return value or None
