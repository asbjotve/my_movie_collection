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


def fetch_tmdb_details(external_id: str, content_type: str, language: str = "nb-NO") -> dict:
    """Henter fulle detaljer for én TMDB-tittel (film eller serie).

    'language' styrer hvilket språk TMDB returnerer title/overview på
    (append_to_response=translations gir uansett tilgang til ALLE
    oversettelser som fallback, se _extract_tmdb_title()/
    _extract_tmdb_overview() i media_catalog.py) - bestemmes av admins
    "foretrukket språk ved henting av data"-innstilling
    (tmdb_data_language, se get_tmdb_data_language() i
    media_catalog.py), default "nb-NO" for uendret oppførsel der
    kalleren ikke bryr seg (f.eks. cover-only-henting)."""

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
                "language": language,
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


def fetch_tmdb_season_episodes(show_external_id: str, season_number: int) -> dict:
    """Henter én sesongs episodeliste fra TMDB (kun relevant for
    content_type='series') - brukes av "Hent episoder fra kilde" på
    sesong-fanen på detaljsiden, for å slippe å taste inn hver
    episodetittel/-beskrivelse manuelt.

    Returnerer {season_overview, episodes: [{episode_number, title,
    overview, air_date, runtime}, ...]} - samme normaliserte form
    uansett kilde, se fetch_tvdb_season_episodes()."""

    if not settings.TMDB_API_KEY:
        raise ExternalApiError(
            "TMDB_API_KEY er ikke satt i backend/config/.env", status_code=500
        )

    url = f"{TMDB_BASE_URL}/tv/{show_external_id}/season/{season_number}"

    def _request(language: str) -> dict:
        try:
            resp = requests.get(
                url,
                params={"api_key": settings.TMDB_API_KEY, "language": language},
                timeout=REQUEST_TIMEOUT_SECONDS,
            )
        except requests.RequestException as e:
            raise ExternalApiError(f"Kunne ikke nå TMDB: {e}") from e

        if resp.status_code != 200:
            raise ExternalApiError(
                f"TMDB svarte med feilkode {resp.status_code}: {resp.text[:300]}"
            )
        return resp.json()

    data = _request("nb-NO")

    # TMDB mangler ofte norske oversettelser for episodetittel/-beskrivelse
    # og faller da tilbake til en generisk plassholder ("Episode 1") uten
    # overview - hent engelsk versjon også og bruk den der norsk mangler,
    # samme prinsipp som _extract_tmdb_overview() for filmer/serier.
    english_by_number: dict[int | None, dict] = {}
    needs_fallback = any(
        not ep.get("overview")
        or ep.get("name") == f"Episode {ep.get('episode_number')}"
        for ep in (data.get("episodes") or [])
    )
    if needs_fallback:
        english_data = _request("en-US")
        english_by_number = {
            ep.get("episode_number"): ep for ep in (english_data.get("episodes") or [])
        }

    def _pick(ep: dict, field: str) -> str | None:
        value = ep.get(field)
        if field == "name" and value == f"Episode {ep.get('episode_number')}":
            value = None
        if value:
            return value
        fallback_ep = english_by_number.get(ep.get("episode_number"))
        return fallback_ep.get(field) if fallback_ep else None

    return {
        "season_overview": data.get("overview") or None,
        "episodes": [
            {
                "episode_number": ep.get("episode_number"),
                "title": _pick(ep, "name"),
                "overview": _pick(ep, "overview"),
                "air_date": ep.get("air_date") or None,
                "runtime": ep.get("runtime"),
            }
            for ep in (data.get("episodes") or [])
        ],
    }


TVDB_SEASON_TYPES = ("default", "dvd", "official", "absolute", "alternate", "regional")


def fetch_tvdb_season_episodes(
    show_external_id: str, season_number: int, season_type: str = "default"
) -> dict:
    """Som fetch_tmdb_season_episodes(), men mot TVDB - se
    getSeriesEpisodes i TVDBs v4-API (filtrert på ?season=N).
    TVDB har som regel mer komplette sesong/episode-data enn
    TMDB for serier (jf. Season/Episode-docstringene i media_models.py),
    så dette er ment som hovedkilden for serier.

    season_type velger hvilken av TVDBs flere sesong-ordninger
    episode-/sesongnumrene skal hentes etter - 'default' er som regel
    sendedato-rekkefølge ("aired order"), mens 'dvd' er rekkefølgen
    DVD/Blu-ray-utgivelser faktisk bruker. Enkelte serier (f.eks. Young
    Indiana Jones Chronicles) er nummerert helt forskjellig i de to
    ordningene, så en bruker som har registrert fysiske sesonger etter
    DVD-rekkefølgen får "Sesong 1" til å bety noe helt annet enn TVDBs
    default-rekkefølge - da må 'dvd' brukes i stedet, ellers finnes
    ingen (eller feil) episoder for det sesongnummeret.

    NB: TVDB sin /episodes/{season-type}-rute returnerer kun engelsk
    tekst uten en eksplisitt språk-sti (/default/{lang}) - det holder
    fint her siden fetch_tvdb_details() allerede faller tilbake til
    engelsk for TVDB-tekst (se _extract_tvdb_overview() i
    media_catalog.py)."""

    if season_type not in TVDB_SEASON_TYPES:
        raise ExternalApiError(f"Ugyldig season_type={season_type!r} for TVDB.")

    token = _get_tvdb_token()

    try:
        response = requests.get(
            f"{TVDB_BASE_URL}/series/{show_external_id}/episodes/{season_type}",
            params={"page": 0, "season": season_number},
            headers={"Authorization": f"Bearer {token}"},
            timeout=REQUEST_TIMEOUT_SECONDS,
        )
    except requests.RequestException as e:
        raise ExternalApiError(f"Kunne ikke nå TVDB: {e}") from e

    if response.status_code != 200:
        raise ExternalApiError(
            f"TVDB svarte med feilkode {response.status_code}: {response.text[:300]}"
        )

    data = response.json().get("data") or {}
    episodes = [
        ep for ep in (data.get("episodes") or []) if ep.get("seasonNumber") == season_number
    ]
    return {
        "season_overview": None,
        "episodes": [
            {
                "episode_number": ep.get("number"),
                "title": ep.get("name") or None,
                "overview": ep.get("overview") or None,
                "air_date": ep.get("aired") or None,
                "runtime": ep.get("runtime"),
            }
            for ep in episodes
        ],
    }


