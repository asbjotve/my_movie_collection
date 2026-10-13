"""per-user watched status for content

Revision ID: c6ef33fd4560
Revises: dae24158461c
Create Date: 2026-10-07 00:00:00.000000

Replaces the single global `content.watched_flag` column with a new
`content_watched` join table (one row per user+content that has been
marked as watched - absence of a row means "not watched"), so that
each user can track their own "seen it" status independently instead
of sharing one flag.

`user_id` is a plain (unconstrained) integer - it intentionally has NO
foreign key to users.user_id, since the users table lives in a
completely separate database/connection (mmc_userdb, see app/db.py)
from the media catalog (db_mediearkiv, see app/media_db.py) that this
table belongs to. It's only ever written/read via already-authenticated
user.id values from app/security.py's get_current_user().

Existing content.watched_flag=1 rows are migrated to belong to user_id
4 ("asbjo"), the only real user in the system at the time of this
migration - see commit message/chat history for context if more users
exist later and this assumption needs revisiting.
"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects.mysql import BINARY


# revision identifiers, used by Alembic.
revision: str = 'c6ef33fd4560'
down_revision: Union[str, Sequence[str], None] = 'dae24158461c'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None

# The only real user at the time this migration was written - existing
# watched_flag=1 rows are attributed to this user_id (see docstring).
_LEGACY_WATCHED_USER_ID = 4


def upgrade() -> None:
    """Upgrade schema."""
    op.create_table(
        'content_watched',
        sa.Column('user_id', sa.Integer(), nullable=False),
        sa.Column('content_id', BINARY(16), nullable=False),
        sa.Column('watched_at', sa.TIMESTAMP(), server_default=sa.text('CURRENT_TIMESTAMP'), nullable=False),
        sa.ForeignKeyConstraint(['content_id'], ['content.content_id'], ondelete='CASCADE'),
        sa.PrimaryKeyConstraint('user_id', 'content_id'),
    )

    # Migrate existing watched_flag=1 rows to the new table before
    # dropping the column.
    op.execute(
        sa.text(
            """
            INSERT INTO content_watched (user_id, content_id, watched_at)
            SELECT :user_id, content_id, CURRENT_TIMESTAMP
            FROM content
            WHERE watched_flag = 1
            """
        ).bindparams(user_id=_LEGACY_WATCHED_USER_ID)
    )

    op.drop_column('content', 'watched_flag')


def downgrade() -> None:
    """Downgrade schema."""
    op.add_column('content', sa.Column('watched_flag', sa.dialects.mysql.TINYINT(1), nullable=True))
    op.execute(
        """
        UPDATE content
        SET watched_flag = 1
        WHERE content_id IN (SELECT content_id FROM content_watched)
        """
    )
    op.drop_table('content_watched')
