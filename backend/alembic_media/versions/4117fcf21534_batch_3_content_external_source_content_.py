"""batch 3: content_external_source, content_group_membership, content_in_physical_collection, disc_related_content, list_items

Revision ID: 4117fcf21534
Revises: bc37d2e1cb67
Create Date: 2026-09-12 13:40:28.107305

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import mysql

# revision identifiers, used by Alembic.
revision: str = '4117fcf21534'
down_revision: Union[str, Sequence[str], None] = 'bc37d2e1cb67'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Upgrade schema.

    No-op, like the previous batch migrations. Autogenerate confirmed
    the 5 newly modeled tables (content_external_source,
    content_group_membership, content_in_physical_collection,
    disc_related_content, list_items) match the real database exactly
    - zero CREATE/ALTER was proposed for them. The only output was
    DROP statements for the remaining not-yet-modeled tables
    (disc_in, disc_in_storage, disc_bonus_item, custom_list_entries,
    list), the same false positive described in earlier migrations -
    stripped here on purpose.
    """
    pass


def downgrade() -> None:
    """Downgrade schema. No-op - see upgrade()."""
    pass
