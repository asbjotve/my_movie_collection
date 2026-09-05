"""Pydantic-schemas for /settings/default-language-endepunktene."""

from pydantic import BaseModel, field_validator

# Holdes i sync med WTE_AVAILABLE_LANGS i
# frontend/.../v19/lang.php - validert her også (defense in depth),
# ikke bare i frontend.
ALLOWED_LANGUAGES = ("no", "en")


class DefaultLanguageUpdateRequest(BaseModel):
    """Body for PUT /settings/default-language."""

    default_language: str

    @field_validator("default_language")
    @classmethod
    def validate_language(cls, value: str) -> str:
        if value not in ALLOWED_LANGUAGES:
            raise ValueError(f"default_language must be one of {ALLOWED_LANGUAGES}")
        return value
