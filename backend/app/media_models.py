"""media_models.py - SQLAlchemy ORM models for db_mediearkiv.

This mirrors the split already used for connections (app/db.py vs.
app/media_db.py): db_mediearkiv is a separate MySQL/MariaDB database
from mmc_userdb, so it gets its own declarative Base and, per
alembic_media/, its own Alembic environment/version table.

Historically db_mediearkiv has been read/written entirely via raw SQL
(see app/services/media_catalog.py). These ORM models exist ONLY to
give Alembic something to compare against for schema-migration
purposes - they are not (yet) used by the application's query code,
and should mirror the real table structure exactly so
`alembic revision --autogenerate` doesn't propose spurious changes.

Being modeled here table-by-table, in small batches, starting with the
tables that have no outgoing foreign keys (movie_group, owner, store,
storage, physical_collection). See TODO.md for the remaining tables.
"""

from sqlalchemy import Column, Integer, String, Text
from sqlalchemy.dialects.mysql import BINARY
from sqlalchemy.orm import declarative_base

MediaBase = declarative_base()


class MovieGroup(MediaBase):
    """A group of related movies (e.g. a film series/franchise) that a
    content row can optionally belong to - see content.group_id and,
    for the many-to-many variant, content_group_membership."""

    __tablename__ = "movie_group"

    group_id = Column(Integer, primary_key=True, autoincrement=True)
    name = Column(String(255), nullable=False)
    note = Column(Text, nullable=True)
    tmdb_collection_id = Column(String(20), nullable=True)


class Owner(MediaBase):
    """Who owns a given physical copy (physical_copy.owner_id)."""

    __tablename__ = "owner"

    owner_id = Column(Integer, primary_key=True, autoincrement=True)
    name = Column(String(255), nullable=False)
    note = Column(Text, nullable=True)


class Store(MediaBase):
    """Where a physical copy was bought (physical_copy.store_id)."""

    __tablename__ = "store"

    store_id = Column(Integer, primary_key=True, autoincrement=True)
    name = Column(String(255), nullable=False)
    note = Column(Text, nullable=True)


class Storage(MediaBase):
    """A physical storage location/container for discs
    (disc_in_storage.storage_id). Primary key is a raw 16-byte UUID
    (BINARY(16)), same convention as content_id/collection_id - see
    app/services/media_catalog.py's _uuid_to_hex()/_hex_to_uuid_bytes()
    for how the app converts these to/from hex strings."""

    __tablename__ = "storage"

    storage_id = Column(BINARY(16), primary_key=True)
    max_capacity = Column(Integer, nullable=True)


class PhysicalCollection(MediaBase):
    """One physical item/box in the collection - a single disc's
    packaging or a box-set's outer box (content_in_physical_collection,
    physical_copy.collection_id reference this)."""

    __tablename__ = "physical_collection"

    collection_id = Column(BINARY(16), primary_key=True)
    barcode = Column(String(13), nullable=True)
    format = Column(Text, nullable=True)
    box_set_barcode = Column(String(13), nullable=True)
