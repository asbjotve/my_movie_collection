"""
GET/PUT /settings/section-access

Styrer om enkelt-seksjoner på forsiden (frontend v18/index.php) krever
innlogging - erstatter det som tidligere var en hardkodet
$sectionAccess-array i PHP-koden.

GET er åpent (uten API-nøkkel/JWT) fordi index.php må kunne slå opp
disse innstillingene for **alle** besøkende, innlogget eller ikke, for
å vite hva som skal vises låst. Verdiene her avslører ingenting
sensitivt - kun hvilke menypunkter som krever innlogging.

PUT krever innlogging OG rollen "admin" (via require_role) - kun en
admin skal kunne endre disse innstillingene.
"""

from fastapi import APIRouter, Depends
from sqlalchemy.orm import Session

from app.db import SectionAccess, User, get_db
from app.schemas.section_access import KNOWN_SECTION_KEYS, SectionAccessUpdateRequest
from app.security import require_role

router = APIRouter(
    prefix="/settings/section-access",
    tags=["section-access"],
)


@router.get("")
async def get_section_access(db: Session = Depends(get_db)) -> dict[str, bool]:
    rows = db.query(SectionAccess).all()
    result = {row.section_key: bool(row.requires_login) for row in rows}
    # Sørg for at alle kjente nøkler er med, selv om raden av en eller
    # annen grunn mangler i databasen (default: låst/krever innlogging,
    # det tryggeste alternativet).
    for key in KNOWN_SECTION_KEYS:
        result.setdefault(key, True)
    return result


@router.put("")
async def update_section_access(
    body: SectionAccessUpdateRequest,
    db: Session = Depends(get_db),
    current_user: User = Depends(require_role("admin")),
) -> dict[str, bool]:
    for section_key, requires_login in body.sections.items():
        row = db.query(SectionAccess).filter(SectionAccess.section_key == section_key).first()
        if row is None:
            row = SectionAccess(section_key=section_key, requires_login=requires_login)
            db.add(row)
        else:
            row.requires_login = requires_login
    db.commit()

    rows = db.query(SectionAccess).all()
    return {row.section_key: bool(row.requires_login) for row in rows}
