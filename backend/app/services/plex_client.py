"""Klient mot Plex Media Server sitt eget (ikke-offentlig, men stabilt
og mye brukt) HTTP-API - kun for "Test tilkobling"-funksjonen i
innstillingene foreløpig (se plex_settings_route.py). Selve
import-jobben (lese biblioteker/filmer via /library/sections) bygges i
en senere omgang, se TODO.md.

Plex svarer med XML by default - "Accept: application/json" gjør at
den svarer med JSON i stedet, som er enklere å jobbe med herfra.
"""

import requests

REQUEST_TIMEOUT_SECONDS = 10


class PlexConnectionError(ValueError):
    """Brukes til å gi en forklarende feilmelding tilbake til UI-et
    (f.eks. "Feil token" vs. "Fikk ikke kontakt med serveren") uten å
    lekke stack traces/interne detaljer til frontend."""


def test_plex_connection(
    base_url: str,
    token: str,
    server_identifier: str | None = None,
    verify_ssl: bool = True,
) -> dict:
    """Pinger Plex sitt /identity-endepunkt (krever ikke tilgang til et
    spesifikt bibliotek, kun en gyldig token) og returnerer et lite
    sammendrag av serveren hvis det svarer. Kaster PlexConnectionError
    med en norsk feilmelding hvis noe går galt.

    `server_identifier` er valgfri: hvis satt, sjekkes den mot
    `machineIdentifier` i svaret, slik at man er sikker på at man
    faktisk snakker med RIKTIG Plex-server (og ikke bare en vilkårlig
    server som godtar tokenet) - nyttig hvis man noen gang bytter
    URL/DDNS-adresse og vil unngå å importere fra feil server ved et
    uhell.

    `verify_ssl=False` lar brukeren koble til servere der Plex sitt
    TLS-sertifikat ikke matcher hostnavnet som brukes (typisk ved eget
    DDNS-navn, siden Plex sitt sertifikat kun er gyldig for dets egne
    "*.plex.direct"-adresser) - dette er brukerens eget valg/ansvar,
    default er fortsatt streng verifisering.
    """

    if not base_url:
        raise PlexConnectionError("Mangler Plex-server-URL")
    if not token:
        raise PlexConnectionError("Mangler Plex-token")

    url = f"{base_url}/identity"

    try:
        response = requests.get(
            url,
            headers={
                "X-Plex-Token": token,
                "Accept": "application/json",
            },
            timeout=REQUEST_TIMEOUT_SECONDS,
            verify=verify_ssl,
        )
    except requests.exceptions.SSLError as exc:
        raise PlexConnectionError(
            "SSL-sertifikatfeil - Plex sitt sertifikat er vanligvis kun gyldig for "
            "*.plex.direct-adresser, ikke egendefinerte hostnavn/DDNS. Kryss av for "
            "\"Ignorer SSL-sertifikatfeil\" hvis du stoler på adressen, eller bruk "
            f"server-URL-en som slutter på .plex.direct i stedet. ({exc})"
        ) from exc
    except requests.exceptions.RequestException as exc:
        raise PlexConnectionError(f"Fikk ikke kontakt med Plex-serveren: {exc}") from exc

    if response.status_code == 401:
        raise PlexConnectionError("Plex avviste token (401 Unauthorized) - sjekk at tokenet er riktig")
    if response.status_code != 200:
        raise PlexConnectionError(f"Plex svarte med uventet status {response.status_code}")

    try:
        data = response.json()
    except ValueError as exc:
        raise PlexConnectionError("Klarte ikke å tolke svaret fra Plex som JSON") from exc

    container = data.get("MediaContainer", {})
    machine_identifier = container.get("machineIdentifier")

    if server_identifier and machine_identifier and server_identifier != machine_identifier:
        raise PlexConnectionError(
            f"Serveren svarte, men identifikatoren stemmer ikke "
            f"(forventet \"{server_identifier}\", fikk \"{machine_identifier}\") - "
            "sjekk at URL-en peker til riktig Plex-server"
        )

    return {
        "machine_identifier": machine_identifier,
        "version": container.get("version"),
    }
