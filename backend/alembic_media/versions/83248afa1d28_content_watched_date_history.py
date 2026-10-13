"""content watched date history (multiple watch dates per user)

Revision ID: 83248afa1d28
Revises: c6ef33fd4560
Create Date: 2026-10-13 00:00:00.000000

Replaces the single-row-per-user content_watched table (migration
c6ef33fd4560, one PK(user_id, content_id) row = "watched", no date
tracking) with a proper one-to-many history table: a user can now have
zero, one, or MANY watched_date entries for the same title (rewatch
history), each independently addable/editable/removable - see
add_content_watched_date()/update_content_watched_date()/
delete_content_watched_date() in media_catalog.py.

The old table never accumulated any real rows in production (verified
before writing this migration), so there is no data to carry over -
this is a straight drop-and-recreate.
"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects.mysql import BINARY


# revision identifiers, used by Alembic.
revision: str = '83248afa1d28'
down_revision: Union[str, Sequence[str], None] = 'c6ef33fd4560'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Upgrade schema."""
    op.drop_table('content_watched')
    op.create_table(
        'content_watched',
        sa.Column('watched_id', sa.Integer(), autoincrement=True, nullable=False),
        sa.Column('user_id', sa.Integer(), nullable=False),
        sa.Column('content_id', BINARY(16), nullable=False),
        sa.Column('watched_date', sa.Date(), nullable=False),
        sa.Column('created_at', sa.TIMESTAMP(), server_default=sa.text('CURRENT_TIMESTAMP'), nullable=False),
        sa.ForeignKeyConstraint(['content_id'], ['content.content_id'], ondelete='CASCADE'),
        sa.PrimaryKeyConstraint('watched_id'),
    )
    op.create_index(
        'ix_content_watched_user_content', 'content_watched', ['user_id', 'content_id']
    )


def downgrade() -> None:
    """Downgrade schema."""
    op.drop_index('ix_content_watched_user_content', table_name='content_watched')
    op.drop_table('content_watched')
    op.create_table(
        'content_watched',
        sa.Column('user_id', sa.Integer(), nullable=False),
        sa.Column('content_id', BINARY(16), nullable=False),
        sa.Column('watched_at', sa.TIMESTAMP(), server_default=sa.text('CURRENT_TIMESTAMP'), nullable=False),
        sa.ForeignKeyConstraint(['content_id'], ['content.content_id'], ondelete='CASCADE'),
        sa.PrimaryKeyConstraint('user_id', 'content_id'),
    )
