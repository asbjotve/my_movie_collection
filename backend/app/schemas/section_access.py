"""Pydantic-schemas for /settings/section-access-endepunktene."""

from pydantic import BaseModel

# Samme nøkler som $sectionAccess i frontend/.../v18/index.php.
KNOWN_SECTION_KEYS = ("mine_filmer", "onskeliste", "andre_lister", "administrering")


class SectionAccessItem(BaseModel):
    section_key: str
    requires_login: bool


class SectionAccessUpdateRequest(BaseModel):
    """Body for PUT /settings/section-access - kun de seksjonene som
    faktisk skal endres trenger å være med."""

    sections: dict[str, bool]
