"""store actual tmdb/tvdb media type used per source

Revision ID: 35b1ab11ddd8
Revises: e1de11d70f4b
Create Date: 2026-10-06 20:38:10.338784

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision: str = '35b1ab11ddd8'
down_revision: Union[str, Sequence[str], None] = 'e1de11d70f4b'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Upgrade schema.

    Adds content_external_source.media_type ('movie'/'tv') - the
    TMDB/TVDB endpoint actually used to fetch this row's data_json.
    Needed because content.content_type ('movie'/'series') can
    legitimately be changed independently by the user (e.g. a "TV
    movie" that TMDB/TVDB catalog as a series with no seasons, but the
    user wants listed as content_type='movie' - see discussion in
    media_catalog.py's update_content_external_source()). Without this,
    a later "Hent fra TMDB/TVDB"-refresh would re-derive the endpoint
    from content.content_type and could flip back to querying the
    wrong (movie vs. tv/series) endpoint, re-introducing the original
    cross-contamination bug (TMDB/TVDB use separate id spaces per
    endpoint, so the same numeric id can be a completely different
    title under /movie/ vs. /tv/).

    Backfilled best-effort from each row's current content.content_type
    for existing data (content_type='movie' -> media_type='movie',
    anything else -> 'tv') - this is only a guess for already-existing
    rows (correct unless the row's content_type was already wrong/
    mismatched, in which case the next manual id (re)assignment via the
    'Sett/Endre ID' modal fixes it properly, see
    set_content_external_id()).
    """
    op.add_column(
        "content_external_source",
        sa.Column("media_type", sa.String(length=10), nullable=True),
    )
    op.execute(
        """
        UPDATE content_external_source ces
        JOIN content c ON c.content_id = ces.content_id
        SET ces.media_type = IF(c.content_type = 'movie', 'movie', 'tv')
        """
    )


def downgrade() -> None:
    """Downgrade schema."""
    op.drop_column("content_external_source", "media_type")
