"""add show_on_frontend to custom_lists

Revision ID: dae24158461c
Revises: de3ab8de14b9
Create Date: 2026-10-12 21:30:00.000000

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa

# revision identifiers, used by Alembic.
revision: str = 'dae24158461c'
down_revision: Union[str, Sequence[str], None] = 'de3ab8de14b9'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Upgrade schema.

    Adds a boolean flag letting an admin opt a given custom list in to
    being shown on the public frontend's "Andre lister" panel - defaults
    to off (0) so existing lists stay admin-only until explicitly
    switched on, see custom_list_manager.py.
    """
    op.add_column(
        'custom_lists',
        sa.Column(
            'show_on_frontend',
            sa.SmallInteger(),
            nullable=False,
            server_default='0',
        ),
    )


def downgrade() -> None:
    """Downgrade schema."""
    op.drop_column('custom_lists', 'show_on_frontend')
