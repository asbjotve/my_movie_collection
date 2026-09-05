"""Pydantic-schemas for /settings/default-language og
/settings/default-currency-endepunktene.
"""

import re

from pydantic import BaseModel, field_validator

# Holdes i sync med WTE_AVAILABLE_LANGS i
# frontend/.../v19/lang.php - validert her også (defense in depth),
# ikke bare i frontend.
ALLOWED_LANGUAGES = ("no", "en")

# ISO 4217-valutakoder er 3 store bokstaver (NOK, EUR, USD, ...) - vi
# holder ikke en fullstendig liste her (åpent for alle gyldige koder),
# men validerer formatet slik at feiltastede verdier ikke havner i
# app_settings og lekker ut i alle physical_copy-rader uten currency.
CURRENCY_CODE_PATTERN = re.compile(r"^[A-Z]{3}$")


class DefaultLanguageUpdateRequest(BaseModel):
    """Body for PUT /settings/default-language."""

    default_language: str

    @field_validator("default_language")
    @classmethod
    def validate_language(cls, value: str) -> str:
        if value not in ALLOWED_LANGUAGES:
            raise ValueError(f"default_language must be one of {ALLOWED_LANGUAGES}")
        return value


class DefaultCurrencyUpdateRequest(BaseModel):
    """Body for PUT /settings/default-currency."""

    default_currency: str

    @field_validator("default_currency")
    @classmethod
    def validate_currency(cls, value: str) -> str:
        value = value.strip().upper()
        if not CURRENCY_CODE_PATTERN.match(value):
            raise ValueError("default_currency must be a 3-letter ISO 4217 code (e.g. NOK, EUR, USD)")
        return value
