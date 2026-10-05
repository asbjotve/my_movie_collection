"""add overview and watched_flag to season/episode

Revision ID: e1de11d70f4b
Revises: 7617ebce2f9b
Create Date: 2026-10-05 22:04:04.714990

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision: str = 'e1de11d70f4b'
down_revision: Union[str, Sequence[str], None] = '7617ebce2f9b'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Upgrade schema."""
    op.add_column('season', sa.Column('overview', sa.Text(), nullable=True))
    op.add_column('episode', sa.Column('overview', sa.Text(), nullable=True))
    op.add_column(
        'episode',
        sa.Column('watched_flag', sa.SmallInteger(), nullable=False, server_default='0'),
    )


def downgrade() -> None:
    """Downgrade schema."""
    op.drop_column('episode', 'watched_flag')
    op.drop_column('episode', 'overview')
    op.drop_column('season', 'overview')
