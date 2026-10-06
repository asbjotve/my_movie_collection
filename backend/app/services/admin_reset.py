"""
"Nullstill alle data" - se admin_tilganger.php i frontend (v19) og
POST /admin/reset-collection-data i admin_reset_route.py.

Sletter ALL samlings-/filmdata (content og alt som direkte eller
indirekte henger på en content-rad: fysiske/digitale eksemplarer,
plater, sesonger/episoder, filmgrupper, egendefinerte lister og
ønskeliste) slik at databasen fremstår som "tom" og klar for en frisk
import (f.eks. en ny Plex-import), UTEN å røre tabellene brukeren
eksplisitt ba om å bevare:

    - storage            (lagringssteder brukeren har satt opp manuelt)
    - store               (butikker/kjøpssteder)
    - owner               (eiere av fysiske eksemplarer)
    - alembic_version     (migrasjonshistorikk - skal ALDRI røres herfra)

RESET_TABLES_IN_ORDER ble utledet fra et spørring mot
information_schema.KEY_COLUMN_USAGE (alle FOREIGN KEY-relasjoner i
db_mediearkiv) og lister barn FØR foreldre, slik at rekkefølgen er
trygg selv om FOREIGN_KEY_CHECKS av en eller annen grunn ikke lar seg
slå av. I selve kjøringen slås FOREIGN_KEY_CHECKS likevel midlertidig
av (kun en sesjonsvariabel - krever ingen ALTER-privilegier, som
media_arkiv_admin-brukeren bevisst mangler) som en ekstra sikkerhet mot
at rekkefølgen skulle vise seg å være ufullstendig.
"""

from sqlalchemy import text
from sqlalchemy.orm import Session

RESET_TABLES_IN_ORDER: list[str] = [
    "content_external_source",
    "disc_related_content",
    "disc_in",
    "content_in_physical_collection",
    "digital_media_part",
    "custom_list_entries",
    "disc_bonus_item",
    "disc_contains_episode",
    "disc_in_storage",
    "wishlist",
    "content_group_membership",
    "custom_lists",
    "list_items",
    "digital_media_version",
    "disc",
    "physical_copy",
    "episode",
    "content_in_digital_library",
    "physical_collection",
    "season",
    "content",
    "digital_library",
    "movie_group",
]

# Eksplisitt IKKE med over, og skal ALDRI slettes av denne funksjonen.
PRESERVED_TABLES: tuple[str, ...] = ("storage", "store", "owner", "alembic_version")


def preview_reset_counts(db: Session) -> dict[str, int]:
    """Antall rader som VILLE blitt slettet per tabell, uten å slette
    noe - brukes av frontend til å vise brukeren nøyaktig hva
    "Nullstill alle data" kommer til å fjerne FØR de bekrefter."""
    counts: dict[str, int] = {}
    for table in RESET_TABLES_IN_ORDER:
        counts[table] = db.execute(text(f"SELECT COUNT(*) FROM `{table}`")).scalar() or 0
    return counts


def reset_all_collection_data(db: Session) -> dict[str, int]:
    """Sletter alt i RESET_TABLES_IN_ORDER i én transaksjon. storage/
    store/owner/alembic_version røres aldri. Kaster videre og ruller
    tilbake hvis noe feiler underveis, slik at databasen aldri blir
    stående halvveis tømt."""
    deleted: dict[str, int] = {}
    db.execute(text("SET FOREIGN_KEY_CHECKS=0"))
    try:
        for table in RESET_TABLES_IN_ORDER:
            result = db.execute(text(f"DELETE FROM `{table}`"))
            deleted[table] = result.rowcount
        db.commit()
    except Exception:
        db.rollback()
        raise
    finally:
        db.execute(text("SET FOREIGN_KEY_CHECKS=1"))
        db.commit()
    return deleted
