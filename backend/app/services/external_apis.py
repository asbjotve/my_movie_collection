"""Klienter mot TMDB og TVDB sine offentlige API-er (server-side).

Brukes av update_content_external_source() (media_catalog.py) sin
"oppdater fra kilde"-funksjonalitet: i stedet for at frontend henter
fulle detaljer og sender dem som JSON-payload (kan bli mye data),
henter backend selv fulle detaljer fra TMDB/TVDB rett før de lagres i
content_external_source.data_json.

search_tmdb()/search_tvdb() brukes av søkemodalen for å SETTE en
manglende TMDB/TVDB-id på detail.php (se set_content_external_id() i
media_catalog.py) - samme mønster (URL-er/parametre) som PHP-versjonene
i frontend/public/tmdb_live_search og frontend/public/tvdb_live_search,
bare portet til Python/requests og normalisert til samme resultatform
for begge kildene, slik at frontend kan vise dem side om side.
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


def search_tmdb(query: str, content_type: str) -> list[dict]:
    """Søker TMDB (film eller serie) og returnerer en normalisert liste:
    [{external_id, title, year, overview, poster_url}, ...] - sortert
    som TMDB selv sorterer (popularitet/relevans)."""

    if not settings.TMDB_API_KEY:
        raise ExternalApiError(
            "TMDB_API_KEY er ikke satt i backend/config/.env", status_code=500
        )

    media_type = "movie" if content_type == "movie" else "tv"
    try:
        response = requests.get(
            f"{TMDB_BASE_URL}/search/{media_type}",
            params={
                "api_key": settings.TMDB_API_KEY,
                "language": "nb-NO",
                "query": query,
                "page": 1,
            },
            timeout=REQUEST_TIMEOUT_SECONDS,
        )
    except requests.RequestException as e:
        raise ExternalApiError(f"Kunne ikke nå TMDB: {e}") from e

    if response.status_code != 200:
        raise ExternalApiError(
            f"TMDB svarte med feilkode {response.status_code}: {response.text[:300]}"
        )

    results = response.json().get("results") or []
    normalized = []
    for r in results:
        title = r.get("title") or r.get("name") or ""
        release_date = r.get("release_date") or r.get("first_air_date") or ""
        poster_path = r.get("poster_path")
        normalized.append(
            {
                "external_id": str(r.get("id")),
                "title": title,
                "year": release_date[:4] if release_date else None,
                "overview": r.get("overview") or "",
                "poster_url": f"https://image.tmdb.org/t/p/w154{poster_path}" if poster_path else None,
            }
        )
    return normalized


def search_tvdb(query: str, content_type: str) -> list[dict]:
    """Søker TVDB (film eller serie) og returnerer samme normaliserte
    form som search_tmdb()."""

    token = _get_tvdb_token()
    tvdb_type = "movie" if content_type == "movie" else "series"

    try:
        response = requests.get(
            f"{TVDB_BASE_URL}/search",
            params={"query": query, "type": tvdb_type, "limit": 25},
            headers={"Authorization": f"Bearer {token}"},
            timeout=REQUEST_TIMEOUT_SECONDS,
        )
    except requests.RequestException as e:
        raise ExternalApiError(f"Kunne ikke nå TVDB: {e}") from e

    if response.status_code != 200:
        raise ExternalApiError(
            f"TVDB svarte med feilkode {response.status_code}: {response.text[:300]}"
        )

    results = response.json().get("data") or []
    normalized = []
    for r in results:
        external_id = r.get("tvdb_id") or r.get("id")
        if external_id is None:
            continue
        normalized.append(
            {
                "external_id": str(external_id),
                "title": r.get("name") or r.get("title") or r.get("slug") or "",
                "year": r.get("year"),
                "overview": r.get("overview") or "",
                "poster_url": r.get("image_url") or r.get("thumbnail"),
            }
        )
    return normalized


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
