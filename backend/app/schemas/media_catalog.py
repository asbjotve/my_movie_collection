"""Pydantic-schemas for de manuelle redigeringsendepunktene i
media_catalog_route.py (penne-ikon-redigering på detaljsiden -
PATCH /media/content/{id} og PATCH /media/physical-copy/{collection_id}/{copy_id}).

Alle felter er valgfrie (kun feltet som faktisk redigeres sendes med
fra frontend) - hvilke felter som faktisk ble sendt inn avgjøres med
`model_dump(exclude_unset=True)` i route/service-laget, IKKE ved å
sjekke `is not None`, siden None er en gyldig verdi å sette et felt til
(f.eks. å tømme age_restriction).
"""

import re
from datetime import date
from typing import Literal

from pydantic import BaseModel, field_validator

CURRENCY_CODE_PATTERN = re.compile(r"^[A-Z]{3}$")

# Samme to verdier som faktisk lagres i content.content_type i databasen
# ("movie"/"series" - se f.eks. _get_or_create_content() i
# physical_collection_import.py og detail.php sin content_type-select,
# som viser disse oversatt som "Film"/"Serie" (no) eller "Movie"/"TV
# series" (en) - selve de lagrede verdiene er alltid engelske interne
# nøkkelord, uavhengig av visningsspråk). IKKE det samme som "tv" som
# brukes andre steder i backend kun som TMDB/TVDB-endepunktvalg (se
# fetch_tmdb_details()/fetch_tvdb_details() i external_apis.py) - de to
# bruksområdene må ikke blandes sammen.
ContentType = Literal["movie", "series"]


class ContentFieldUpdateRequest(BaseModel):
    """Body for PATCH /media/content/{content_id}.

    Feltene her tilsvarer MERGEABLE_CONTENT_FIELDS i media_catalog.py
    (+ content_type) - dvs. de samme feltene som kan komme fra
    TMDB/TVDB-fletting kan også redigeres manuelt. cover_image
    redigeres IKKE her - det har sitt eget endepunkt
    (POST /media/content/{id}/cover). Filmgruppe-medlemskap redigeres
    heller ikke her - se GroupMembershipAddRequest/
    DELETE /media/content/{id}/groups/{group_id}, siden en film nå kan
    tilhøre flere grupper samtidig.
    """

    title: str | None = None
    original_title: str | None = None
    overview: str | None = None
    first_release: date | None = None
    runtime: int | None = None
    age_restriction: str | None = None
    content_type: ContentType | None = None
    imdb_id: str | None = None


class ContentFieldLockRequest(BaseModel):
    """Body for PATCH /media/content/{content_id}/lock.

    Låser/åpner ett enkelt content-felt manuelt (hengelås-ikon på
    detaljsiden), uten å endre selve feltverdien - se
    set_content_field_lock() i media_catalog.py for hvorfor dette er
    et eget endepunkt fra selve redigeringen.
    """

    field: str
    locked: bool


class SetContentExternalIdRequest(BaseModel):
    """Body for POST /media/content/{content_id}/external-source/{source}.

    Setter en TMDB/TVDB-id på en content-rad som mangler en (eller
    erstatter en feil id) - se set_content_external_id() i
    media_catalog.py. confirm_merge brukes kun i et andre kall, etter
    at frontend har vist brukeren at IDen allerede tilhører en annen
    film og fått et eksplisitt "ja, slå sammen".

    content_type ("movie"/"tv") er typen brukeren faktisk søkte med i
    "Sett ID"-modalen - brukes til å velge riktig TMDB/TVDB-endepunkt
    (/movie/ vs. /tv/ eller /series/) FREMFOR content-radens egen,
    lagrede content_type. Uten dette ville en feilimportert rad (f.eks.
    en serie som feilaktig endte opp som content_type='movie') alltid
    bli slått opp som film, og dermed matche en helt annen, urelatert
    tittel på samme numeriske TMDB/TVDB-id (filmer og serier har
    separate id-rom). Hvis oppgitt og den avviker fra radens nåværende
    content_type, oppdateres content.content_type til det brukeren
    faktisk bekreftet. Valgfri (None) for bakoverkompatibilitet - da
    brukes radens eksisterende content_type som før."""

    external_id: str
    confirm_merge: bool = False
    content_type: Literal["movie", "tv"] | None = None


class BulkGroupAssignRequest(BaseModel):
    """Body for POST /media/content/bulk-group.

    Brukes av "velg-modus" i Mine filmer (index.php): legger flere
    valgte filmer til samme filmgruppe i ett kall, i stedet for at
    frontend må gjøre ett kall pr. film. En film kan tilhøre flere
    grupper samtidig, så dette er ADDITIVT - filmer som allerede
    tilhører gruppen fra før beholder både den og alle andre
    gruppetilhørigheter de måtte ha (se bulk_assign_group()). Samme
    get-or-create-oppførsel på "group" som ellers (se
    _get_or_create_group_id()) - tomt/kun whitespace-navn er ikke
    tillatt (se validate_group()).
    """

    content_ids: list[str]
    group: str

    @field_validator("group")
    @classmethod
    def validate_group(cls, value: str) -> str:
        value = value.strip()
        if not value:
            raise ValueError("group kan ikke være tom")
        return value

    @field_validator("content_ids")
    @classmethod
    def validate_content_ids(cls, value: list[str]) -> list[str]:
        if not value:
            raise ValueError("content_ids kan ikke være tom")
        return value


class GroupMembershipAddRequest(BaseModel):
    """Body for POST /media/content/{content_id}/groups.

    Legger én film til i én filmgruppe til (fritekst navn - backend
    gjør get-or-create mot movie_group, samme mønster som owner/store).
    En film kan tilhøre flere grupper samtidig - dette LEGGER TIL en
    gruppetilhørighet, det erstatter ikke noen eksisterende (se
    add_content_to_group()). For å fjerne en gruppetilhørighet, bruk
    DELETE /media/content/{content_id}/groups/{group_id} i stedet.
    """

    group: str

    @field_validator("group")
    @classmethod
    def validate_group(cls, value: str) -> str:
        value = value.strip()
        if not value:
            raise ValueError("group kan ikke være tom")
        return value


class GroupReorderRequest(BaseModel):
    """Body for PATCH /media/groups/{group_id}/reorder.

    Brukes av dra-og-slipp-sortering av filmgruppe-listen på
    detaljsiden: content_ids skal være ALLE filmene i gruppen, i den
    nye ønskede rekkefølgen (indeks 0 = group_sort_order 1, osv.) - se
    reorder_group().
    """

    content_ids: list[str]

    @field_validator("content_ids")
    @classmethod
    def validate_content_ids(cls, value: list[str]) -> list[str]:
        if not value:
            raise ValueError("content_ids kan ikke være tom")
        return value


class PhysicalCopyFieldUpdateRequest(BaseModel):
    """Body for PATCH /media/physical-copy/{collection_id}/{copy_id}.

    owner/store sendes som navn (fritekst) - backend gjør selv
    get-or-create mot owner-/store-tabellene (samme mønster som
    brukeren selv gjorde manuelt for Platekompaniet-testdataen), slik at
    frontend slipper egne "administrer eiere/butikker"-skjermer.
    """

    owner: str | None = None
    store: str | None = None
    purchased_at: date | None = None
    price: float | None = None
    currency: str | None = None

    @field_validator("currency")
    @classmethod
    def validate_currency(cls, value: str | None) -> str | None:
        if value is None:
            return None
        value = value.strip().upper()
        if not CURRENCY_CODE_PATTERN.match(value):
            raise ValueError("currency must be a 3-letter ISO 4217 code (e.g. NOK, EUR, USD)")
        return value


class SeasonCreateRequest(BaseModel):
    """Body for POST /media/content/{content_id}/seasons - oppretter en
    ny sesong manuelt (sesong-fanen på detaljsiden, kun relevant for
    content_type='series'). Episodene legges til separat etterpå (enten
    manuelt, en og en, eller hentet samlet fra TMDB/TVDB - se
    POST /media/seasons/{season_id}/refresh/{source})."""

    season_number: int | None = None
    title: str | None = None
    air_date: date | None = None
    overview: str | None = None


class SeasonUpdateRequest(BaseModel):
    """Body for PATCH /media/seasons/{season_id}. Samme felter som
    SeasonCreateRequest - kun feltene som faktisk er med i payload
    (exclude_unset) blir oppdatert."""

    season_number: int | None = None
    title: str | None = None
    air_date: date | None = None
    overview: str | None = None


class SeasonWatchedRequest(BaseModel):
    """Body for PATCH /media/seasons/{season_id}/watched - merker samtlige
    episoder i sesongen som sett/usett under ett, se set_season_watched()."""

    watched_flag: bool


class EpisodeCreateRequest(BaseModel):
    """Body for POST /media/seasons/{season_id}/episodes - legger til én
    episode manuelt i en eksisterende sesong."""

    episode_number: int | None = None
    title: str | None = None
    overview: str | None = None
    runtime: int | None = None
    original_air_date: date | None = None


class EpisodeUpdateRequest(BaseModel):
    """Body for PATCH /media/episodes/{episode_id}. Samme felter som
    EpisodeCreateRequest, pluss watched_flag (avkrysningsboksen for
    "sett") - kun feltene som faktisk er med i payload (exclude_unset)
    blir oppdatert."""

    episode_number: int | None = None
    title: str | None = None
    overview: str | None = None
    runtime: int | None = None
    original_air_date: date | None = None
    watched_flag: bool | None = None


class EpisodeDiscAssignRequest(BaseModel):
    """Body for PUT /media/episodes/{episode_id}/disc. disc_id=None
    fjerner en eventuell eksisterende disk-kobling for episoden (se
    assign_episode_disc()) - disc_id må være en disk som fysisk
    tilhører samme serie som episoden (validert server-side)."""

    disc_id: str | None = None
