"""Klienter mot TMDB og TVDB sine offentlige API-er (server-side).

Brukes av update_content_external_source() (media_catalog.py) sin
"oppdater fra kilde"-funksjonalitet: i stedet for at frontend henter
fulle detaljer og sender dem som JSON-payload (kan bli mye data),
henter backend selv fulle detaljer fra TMDB/TVDB rett før de lagres i
content_external_source.data_json.

Samme mønster (URL-er/parametre) som PHP-versjonene i
frontend/public/tmdb_live_search og frontend/public/tvdb_live_search
bruker for søk - denne filen dekker kun "hent fulle detaljer for én
kjent ekstern ID", ikke søk.
"""

import requests

from config.config import settings

TMDB_BASE_URL = "https://api.themoviedb.org/3"
TVDB_BASE_URL = "https://api4.thetvdb.com/v4"

REQUEST_TIMEOUT_SECONDS = 10


class ExternalApiError(ValueError):
    def __init__(self, message: str, status_code: int = 502) -> None:
        super().__init__(message)
        self.status_code = status_code


def fetch_tmdb_details(external_id: str, content_type: str) -> dict:
    """Henter fulle detaljer for én TMDB-tittel (film eller serie)."""

    if not settings.TMDB_API_KEY:
        raise ExternalApiError(
            "TMDB_API_KEY er ikke satt i backend/config/.env", status_code=500
        )

    media_type = "movie" if content_type == "movie" else "tv"
    url = f"{TMDB_BASE_URL}/{media_type}/{external_id}"

    try:
        response = requests.get(
            url,
            params={
                "api_key": settings.TMDB_API_KEY,
                "language": "nb-NO",
                "append_to_response": (
                    "alternative_titles,credits,external_ids,images,"
                    "keywords,release_dates,translations"
                ),
                # Uten denne filtrerer TMDB "images" (postere) etter samme
                # "language"-parameter som resten av kallet (nb-NO) - de
                # fleste filmer/serier har ingen norskspråklige postere i
                # det hele tatt, så images.posters endte som regel opp tom.
                # nb/en/null (= uten språk) gir et bredt utvalg postere å
                # velge mellom i "Bytt cover"-funksjonen.
                "include_image_language": "nb,en,null",
            },
            timeout=REQUEST_TIMEOUT_SECONDS,
        )
    except requests.RequestException as e:
        raise ExternalApiError(f"Kunne ikke nå TMDB: {e}") from e

    if response.status_code != 200:
        raise ExternalApiError(
            f"TMDB svarte med feilkode {response.status_code}: {response.text[:300]}"
        )

    return response.json()


def _get_tvdb_token() -> str:
    if not settings.TVDB_API_KEY or not settings.TVDB_PIN:
        raise ExternalApiError(
            "TVDB_API_KEY/TVDB_PIN er ikke satt i backend/config/.env",
            status_code=500,
        )

    try:
        response = requests.post(
            f"{TVDB_BASE_URL}/login",
            json={"apikey": settings.TVDB_API_KEY, "pin": settings.TVDB_PIN},
            timeout=REQUEST_TIMEOUT_SECONDS,
        )
    except requests.RequestException as e:
        raise ExternalApiError(f"Kunne ikke logge inn mot TVDB: {e}") from e

    if response.status_code != 200:
        raise ExternalApiError(
            f"TVDB-innlogging feilet ({response.status_code}): {response.text[:300]}"
        )

    token = response.json().get("data", {}).get("token")
    if not token:
        raise ExternalApiError("TVDB-innlogging: token mangler i responsen")

    return token


def fetch_tvdb_details(external_id: str, content_type: str) -> dict:
    """Henter fulle detaljer for én TVDB-tittel (film eller serie).

    NB: gjør en fresh /login for hvert kall (ingen token-cache her, i
    motsetning til config_tvdb.php sin fil-cache) - denne funksjonen
    brukes kun av den manuelle "oppdater fra kilde"-knappen, ikke i en
    hyppig kalt søke-hot-path, så den ekstra innloggings-runden er
    ubetydelig her.
    """

    token = _get_tvdb_token()
    media_path = "movies" if content_type == "movie" else "series"
    url = f"{TVDB_BASE_URL}/{media_path}/{external_id}/extended"

    try:
        response = requests.get(
            url,
            params={"short": "true", "meta": "translations"},
            headers={"Authorization": f"Bearer {token}"},
            timeout=REQUEST_TIMEOUT_SECONDS,
        )
    except requests.RequestException as e:
        raise ExternalApiError(f"Kunne ikke nå TVDB: {e}") from e

    if response.status_code != 200:
        raise ExternalApiError(
            f"TVDB svarte med feilkode {response.status_code}: {response.text[:300]}"
        )

    data = response.json().get("data")
    if data is None:
        raise ExternalApiError("TVDB: 'data' mangler i responsen")

    return data
