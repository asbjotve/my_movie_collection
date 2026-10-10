"""Klient mot Plex Media Server sitt eget (ikke-offentlig, men stabilt
og mye brukt) HTTP-API. Brukes av "Test tilkobling" (plex_settings_route.py)
og selve import-jobben (plex_import.py) - henter bibliotekliste, filmliste
per bibliotek, og fulle detaljer (inkl. eksterne id-er for matching) per
element.

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


def _plex_request(base_url: str, token: str, path: str, verify_ssl: bool) -> dict:
    """Felles GET-helper mot Plex sitt API - samme feilhåndtering som
    test_plex_connection(), men brukes av de faktiske import-kallene
    under (bibliotekliste, filmliste, enkelt-item-detaljer)."""

    try:
        response = requests.get(
            f"{base_url}{path}",
            headers={
                "X-Plex-Token": token,
                "Accept": "application/json",
            },
            timeout=REQUEST_TIMEOUT_SECONDS,
            verify=verify_ssl,
        )
    except requests.exceptions.RequestException as exc:
        raise PlexConnectionError(f"Fikk ikke kontakt med Plex-serveren ({path}): {exc}") from exc

    if response.status_code == 401:
        raise PlexConnectionError("Plex avviste token (401 Unauthorized)")
    if response.status_code != 200:
        raise PlexConnectionError(f"Plex svarte med uventet status {response.status_code} for {path}")

    try:
        return response.json()
    except ValueError as exc:
        raise PlexConnectionError(f"Klarte ikke å tolke svaret fra Plex som JSON ({path})") from exc


def fetch_library_sections(base_url: str, token: str, verify_ssl: bool = True) -> list[dict]:
    """Lister alle bibliotekseksjoner på serveren (GET /library/sections),
    f.eks. "Filmer"/"Serier" - brukes i innstillingene til å velge
    hvilke(t) bibliotek som skal importeres."""

    data = _plex_request(base_url, token, "/library/sections", verify_ssl)
    directories = data.get("MediaContainer", {}).get("Directory", [])
    return [
        {"key": d.get("key"), "title": d.get("title"), "type": d.get("type")}
        for d in directories
    ]


def fetch_library_items(base_url: str, token: str, section_key: str, verify_ssl: bool = True) -> list[dict]:
    """Henter alle elementer (f.eks. filmer) i én bibliotekseksjon (GET
    /library/sections/{key}/all). Gir en forenklet liste per item -
    fulle detaljer (eksterne id-er for matching, media/part-info for
    filene) hentes separat per item via fetch_item_detail(), siden
    denne listen ikke inkluderer Plex sin <Guid>-liste for nyere
    metadata-agenter."""

    data = _plex_request(base_url, token, f"/library/sections/{section_key}/all", verify_ssl)
    return data.get("MediaContainer", {}).get("Metadata", [])


def fetch_item_detail(base_url: str, token: str, rating_key: str, verify_ssl: bool = True) -> dict | None:
    """Henter full metadata for ett enkelt element (GET
    /library/metadata/{ratingKey}), inkludert <Guid>-listen med
    eksterne id-er (tmdb://, imdb://, tvdb://) som fetch_library_items()
    ikke gir, samt Media/Part-info om filene på disk."""

    data = _plex_request(base_url, token, f"/library/metadata/{rating_key}", verify_ssl)
    items = data.get("MediaContainer", {}).get("Metadata", [])
    return items[0] if items else None


def fetch_show_episodes(base_url: str, token: str, show_rating_key: str, verify_ssl: bool = True) -> list[dict]:
    """Henter ALLE episoder for én serie, på tvers av sesonger, i én
    flat liste (GET /library/metadata/{ratingKey}/allLeaves) - enklere
    enn å gå om sesong-for-sesong. Hvert element har bl.a. parentIndex
    (sesongnummer), index (episodenummer), title, originallyAvailableAt,
    duration, Media/Part (filinfo) og sin egen Guid-liste."""

    data = _plex_request(base_url, token, f"/library/metadata/{show_rating_key}/allLeaves", verify_ssl)
    return data.get("MediaContainer", {}).get("Metadata", [])


def extract_tmdb_id_from_guids(item: dict) -> str | None:
    """Plex sin nyere metadata-agent oppgir eksterne id-er som en liste
    under "Guid": [{"id": "tmdb://603"}, {"id": "imdb://tt0133093"}, ...].
    Den eldre agenten oppgir i stedet toppnivå-feltet "guid" direkte som
    f.eks. "com.plexapp.agents.themoviedb://603?lang=en" - begge formater
    sjekkes her."""

    for guid_entry in item.get("Guid", []):
        guid_id = guid_entry.get("id", "")
        if guid_id.startswith("tmdb://"):
            return guid_id.removeprefix("tmdb://")

    top_level_guid = item.get("guid", "")
    if "themoviedb://" in top_level_guid:
        # f.eks. "com.plexapp.agents.themoviedb://603?lang=en" -> "603"
        after = top_level_guid.split("themoviedb://", 1)[1]
        return after.split("?", 1)[0]

    return None


def extract_tvdb_id_from_guids(item: dict) -> str | None:
    """Samme som extract_tmdb_id_from_guids(), men for TVDB - brukes
    til å matche en SERIE (show-nivå item) mot en eksisterende
    content-rad. For den eldre agenten er toppnivå-"guid" på formatet
    "com.plexapp.agents.thetvdb://<show_id>?lang=en" for show-elementer
    - kun første "/"-segment tas med, siden samme felt på et
    EPISODE-item i stedet er "<show_id>/<season>/<episode>" (se
    extract_tvdb_episode_id_from_guids() for presis episode-matching -
    IKKE bruk denne funksjonen på episode-items, den vil returnere
    show-id-en, ikke en episode-id)."""

    for guid_entry in item.get("Guid", []):
        guid_id = guid_entry.get("id", "")
        if guid_id.startswith("tvdb://"):
            return guid_id.removeprefix("tvdb://").split("/", 1)[0]

    top_level_guid = item.get("guid", "")
    if "thetvdb://" in top_level_guid:
        after = top_level_guid.split("thetvdb://", 1)[1]
        after = after.split("?", 1)[0]
        return after.split("/", 1)[0]

    return None


def extract_tvdb_episode_id_from_guids(item: dict) -> str | None:
    """Presis TVDB EPISODE-id (ikke serie-id) for ett episode-item -
    kun tilgjengelig via den nyere agentens "Guid"-liste
    ([{"id": "tvdb://7658965"}, ...], en egen id-serie fra
    sesong/episode-numre). Den eldre agenten oppgir ikke en egen
    episode-id (kun show-id + sesong/episode-numre i toppnivå-"guid"),
    så denne returnerer None for slike items - sesong+episodenummer
    brukes da som fallback for matching i stedet (se plex_import.py)."""

    for guid_entry in item.get("Guid", []):
        guid_id = guid_entry.get("id", "")
        if guid_id.startswith("tvdb://"):
            return guid_id.removeprefix("tvdb://")

    return None
