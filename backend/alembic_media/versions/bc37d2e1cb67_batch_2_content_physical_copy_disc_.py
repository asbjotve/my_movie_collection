"""batch 2: content, physical_copy, disc, wishlist, custom_lists

Revision ID: bc37d2e1cb67
Revises: 995d923553ec
Create Date: 2026-09-12 13:38:24.632283

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import mysql

# revision identifiers, used by Alembic.
revision: str = 'bc37d2e1cb67'
down_revision: Union[str, Sequence[str], None] = '995d923553ec'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Upgrade schema.

    No-op, like the baseline migration. Autogenerate confirmed the 5
    newly modeled tables (content, physical_copy, disc, wishlist,
    custom_lists) match the real database exactly - zero CREATE/ALTER
    was proposed for them. The only output was DROP statements for
    the ~12 tables not modeled yet, which is the same false positive
    described in the baseline migration (incomplete model coverage,
    not a real diff) - stripped here on purpose.

    "list" was deliberately skipped when picking this batch - it
    looks like an unused/legacy table (no primary key, columns typed
    as int where text is expected, no references in the service
    code). See TODO.md.
    """
    pass


def downgrade() -> None:
    """Downgrade schema. No-op - see upgrade()."""
    pass
