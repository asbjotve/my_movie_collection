"""baseline: movie_group, owner, store, storage, physical_collection

Revision ID: 995d923553ec
Revises:
Create Date: 2026-09-12 13:26:13.055661

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import mysql

# revision identifiers, used by Alembic.
revision: str = '995d923553ec'
down_revision: Union[str, Sequence[str], None] = None
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Upgrade schema.

    This is the baseline migration for the db_mediearkiv Alembic
    environment (alembic_media/): it intentionally does nothing.

    Only 5 of the 20 real tables in db_mediearkiv are modeled as
    SQLAlchemy ORM classes so far (app/media_models.py: movie_group,
    owner, store, storage, physical_collection - the tables with no
    outgoing foreign keys, chosen as the safest first batch). The
    remaining 15 tables will be added in later batches/migrations.

    Because of that, `alembic revision --autogenerate` initially
    proposed DROPping every one of those 15 not-yet-modeled tables
    (content, disc, wishlist, custom_lists, etc.) - that is a false
    positive caused by the incomplete model coverage, NOT a real
    schema difference, and has been stripped from this migration on
    purpose. The existing database was marked up to date with
    `alembic stamp head` (not `upgrade head`), so no DDL was run and
    no data or tables were touched.
    """
    pass


def downgrade() -> None:
    """Downgrade schema. No-op - see upgrade()."""
    pass
