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

# Samme to verdier som brukes ellers i backend for å velge TMDB/TVDB-
# endepunkt (movie vs. tv) - se fetch_tmdb_details()/fetch_tvdb_details()
# i external_apis.py.
ContentType = Literal["movie", "tv"]


class ContentFieldUpdateRequest(BaseModel):
    """Body for PATCH /media/content/{content_id}.

    Feltene her tilsvarer MERGEABLE_CONTENT_FIELDS i media_catalog.py
    (+ content_type, + group) - dvs. de samme feltene som kan komme fra
    TMDB/TVDB-fletting kan også redigeres manuelt. cover_image
    redigeres IKKE her - det har sitt eget endepunkt
    (POST /media/content/{id}/cover).
    """

    title: str | None = None
    original_title: str | None = None
    overview: str | None = None
    first_release: date | None = None
    runtime: int | None = None
    age_restriction: str | None = None
    content_type: ContentType | None = None
    imdb_id: str | None = None
    # Navn på filmgruppen denne filmen tilhører (f.eks. "Tilbake til
    # fremtiden"-trilogien, eller bare filmer som hører naturlig
    # sammen) - fritekst, backend gjør get-or-create mot movie_group
    # (samme mønster som owner/store på physical_copy). Tom streng ("")
    # fjerner koblingen (group_id=NULL).
    group: str | None = None
    # Manuell rekkefølge på filmen innenfor filmgruppen (f.eks. 1, 2, 3
    # for en trilogi) - brukes til å sortere "andre filmer i denne
    # gruppen"-listen på detaljsiden når utgivelsesrekkefølgen ikke
    # stemmer med den tiltenkte seer-/kronologiske rekkefølgen.
    group_sort_order: int | None = None


class ContentFieldLockRequest(BaseModel):
    """Body for PATCH /media/content/{content_id}/lock.

    Låser/åpner ett enkelt content-felt manuelt (hengelås-ikon på
    detaljsiden), uten å endre selve feltverdien - se
    set_content_field_lock() i media_catalog.py for hvorfor dette er
    et eget endepunkt fra selve redigeringen.
    """

    field: str
    locked: bool


class BulkGroupAssignRequest(BaseModel):
    """Body for POST /media/content/bulk-group.

    Brukes av "velg-modus" i Mine filmer (index.php): tildeler flere
    valgte filmer til samme filmgruppe i ett kall, i stedet for at
    frontend må gjøre ett PATCH /media/content/{id}-kall pr. film.
    Samme get-or-create-oppførsel på "group" som i
    ContentFieldUpdateRequest (se _get_or_create_group_id()) - tom/kun
    whitespace-navn er ikke tillatt her (i motsetning til den vanlige
    redigeringen, hvor tom streng brukes for å FJERNE koblingen - det
    gir ikke mening ved bulk-tillegg, se validate_group()).
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
