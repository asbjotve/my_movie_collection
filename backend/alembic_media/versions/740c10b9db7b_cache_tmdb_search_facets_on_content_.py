"""cache tmdb search facets on content_external_source

Revision ID: 740c10b9db7b
Revises: ca92db17e42f
Create Date: 2026-10-04 17:09:18.935042

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision: str = '740c10b9db7b'
down_revision: Union[str, Sequence[str], None] = 'ca92db17e42f'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


"""cache tmdb search facets on content_external_source

Revision ID: 740c10b9db7b
Revises: ca92db17e42f
Create Date: 2026-10-04 17:09:18.935042

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa


# revision identifiers, used by Alembic.
revision: str = '740c10b9db7b'
down_revision: Union[str, Sequence[str], None] = 'ca92db17e42f'
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Upgrade schema.

    Adds 3 small cache columns to content_external_source, populated
    only for source='tmdb' rows (see media_catalog.py's
    _compute_search_facets()/list_content()). Before this, every
    "Mine filmer" page load in website_template_example re-parsed the
    FULL tmdb data_json blob (avg ~108KB, up to ~420KB, ~80MB total
    across ~780 rows) just to extract genre names + top-8 cast names +
    release year for client-side search/facet filtering - measured at
    ~2.4s of backend time per request (1.6s fetching the huge blobs
    over the DB connection + 0.8s of json.loads()+extraction), the
    main cause of the slow initial "Laster data fra databasen..."
    phase reported by the user. These columns hold just the small,
    already-extracted result instead, written once whenever a 'tmdb'
    data_json is inserted/updated (not recomputed on every read).
    """
    op.add_column(
        "content_external_source",
        sa.Column("facet_genres", sa.JSON(), nullable=True),
    )
    op.add_column(
        "content_external_source",
        sa.Column("facet_cast", sa.JSON(), nullable=True),
    )
    op.add_column(
        "content_external_source",
        sa.Column("facet_release_year", sa.Integer(), nullable=True),
    )


def downgrade() -> None:
    """Downgrade schema."""
    op.drop_column("content_external_source", "facet_release_year")
    op.drop_column("content_external_source", "facet_cast")
    op.drop_column("content_external_source", "facet_genres")
