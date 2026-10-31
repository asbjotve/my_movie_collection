"""add share_token to custom_lists

Revision ID: f3a1c9d6b2e4
Revises: 83248afa1d28
Create Date: 2026-10-31 13:30:00.000000

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa

# revision identifiers, used by Alembic.
revision: str = 'f3a1c9d6b2e4'
down_revision: Union[str, Sequence[str], None] = '83248afa1d28'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Upgrade schema.

    Adds an opt-in, unguessable token letting a custom list be viewed
    read-only via a public share link (e.g. /share/<token>), without
    requiring a login - separate from show_on_frontend (which only
    controls visibility inside the logged-in "Andre lister" panel).
    NULL = sharing disabled for that list; see
    set_list_share_token() in custom_list_manager.py.
    """
    op.add_column(
        'custom_lists',
        sa.Column('share_token', sa.String(length=43), nullable=True),
    )
    op.create_unique_constraint(
        'uk_custom_lists_share_token', 'custom_lists', ['share_token']
    )


def downgrade() -> None:
    """Downgrade schema."""
    op.drop_constraint(
        'uk_custom_lists_share_token', 'custom_lists', type_='unique'
    )
    op.drop_column('custom_lists', 'share_token')
