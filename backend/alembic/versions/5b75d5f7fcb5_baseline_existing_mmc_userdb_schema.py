"""baseline: existing mmc_userdb schema

Revision ID: 5b75d5f7fcb5
Revises: 
Create Date: 2026-09-12 12:36:31.136063

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import mysql

# revision identifiers, used by Alembic.
revision: str = '5b75d5f7fcb5'
down_revision: Union[str, Sequence[str], None] = None
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    """Upgrade schema.

    This is the baseline migration: it intentionally does nothing. Its
    only purpose is to give the already-existing mmc_userdb schema a
    starting revision id, so `alembic stamp head` can mark the current
    (pre-Alembic) database as up to date without running any DDL
    against it.

    Autogenerate detected one pre-existing, purely cosmetic mismatch
    between the DB and the ORM model (users.totp_enabled is
    TINYINT(1) in the DB vs. Integer() in app/db.py) - this predates
    Alembic and is left untouched here on purpose. Reconcile it in a
    dedicated future migration if it's ever worth changing.
    """
    pass


def downgrade() -> None:
    """Downgrade schema. No-op - see upgrade()."""
    pass
