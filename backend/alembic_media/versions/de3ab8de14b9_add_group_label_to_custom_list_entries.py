"""add group_label to custom_list_entries

Revision ID: de3ab8de14b9
Revises: c43d9c827691
Create Date: 2026-10-12 10:03:09.804990

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa

# revision identifiers, used by Alembic.
revision: str = 'de3ab8de14b9'
down_revision: Union[str, Sequence[str], None] = 'c43d9c827691'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Upgrade schema.

    Adds a nullable, free-text group label per list entry (e.g. for a
    Marvel-style ordered list) - see
    custom_list_manager.py. Deliberately on custom_list_entries (the
    list<->item link), not on list_items itself, since the same
    list_item could in theory belong to more than one list with a
    different grouping in each.
    """
    op.add_column(
        'custom_list_entries',
        sa.Column('group_label', sa.String(length=100), nullable=True),
    )


def downgrade() -> None:
    """Downgrade schema."""
    op.drop_column('custom_list_entries', 'group_label')
