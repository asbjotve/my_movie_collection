"""batch 4: disc_bonus_item, disc_in_storage, disc_in, custom_list_entries

Revision ID: c2001d10efbe
Revises: 4117fcf21534
Create Date: 2026-09-12 13:41:19.939304

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import mysql

# revision identifiers, used by Alembic.
revision: str = 'c2001d10efbe'
down_revision: Union[str, Sequence[str], None] = '4117fcf21534'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Upgrade schema.

    No-op, like the previous batch migrations. Autogenerate confirmed
    the 4 newly modeled tables (disc_bonus_item, disc_in_storage,
    disc_in, custom_list_entries) match the real database exactly -
    zero CREATE/ALTER was proposed for them. This was the last batch
    of "real" tables: the only remaining diff was the expected
    "removed table 'list'" - the deliberately-skipped legacy table
    (see TODO.md) - stripped here on purpose.

    All db_mediearkiv tables except "list" are now modeled in
    app/media_models.py.
    """
    pass


def downgrade() -> None:
    """Downgrade schema. No-op - see upgrade()."""
    pass
