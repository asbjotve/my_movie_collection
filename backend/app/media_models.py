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

Being modeled here table-by-table, in small batches:
- Batch 1 (done): movie_group, owner, store, storage,
  physical_collection - tables with no outgoing foreign keys.
- Batch 2 (done): content, physical_copy, disc, wishlist,
  custom_lists - content/physical_copy only depend on batch 1 tables;
  disc/wishlist/custom_lists have no outgoing foreign keys either.
  ("list" was deliberately skipped - see TODO.md, it looks like an
  unused/legacy table with no primary key and wrong-looking column
  types, and has no references anywhere in the service code.)
- Batch 3 (done): content_external_source, content_group_membership,
  content_in_physical_collection, disc_related_content, list_items -
  all either have no outgoing foreign keys (list_items) or only
  depend on batch 1/2 tables.
- Batch 4 (done): disc_bonus_item, disc_in_storage, disc_in,
  custom_list_entries - the last of the "real" (non-legacy) tables;
  only "list" remains unmodeled (see TODO.md).

See TODO.md for the remaining tables.
"""

from sqlalchemy import (
    CheckConstraint,
    Column,
    DECIMAL,
    Date,
    ForeignKey,
    ForeignKeyConstraint,
    Index,
    Integer,
    String,
    Text,
    TIMESTAMP,
    UniqueConstraint,
)
from sqlalchemy.dialects.mysql import BINARY, LONGTEXT, TINYINT
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


class Content(MediaBase):
    """The core movie/show catalog table - one row per title. Not
    modeled with relationship()s to other tables yet (e.g. discs,
    physical copies, group membership) since those tables aren't all
    modeled - see media_catalog.py for the real query logic."""

    __tablename__ = "content"

    content_id = Column(BINARY(16), primary_key=True)
    title = Column(Text, nullable=True)
    original_title = Column(Text, nullable=True)
    # first_release was originally TIMESTAMP (which cannot represent
    # dates before 1970-01-01, breaking e.g. "Snow White" from 1938)
    # and was changed to DATE for this reason - see TODO.md/commit
    # history for context.
    first_release = Column(Date, nullable=True)
    cover_image = Column(Text, nullable=True)
    age_restriction = Column(Text, nullable=True)
    runtime = Column(Integer, nullable=True)
    runtime_txt = Column(Text, nullable=True)
    watched_flag = Column(TINYINT(1), nullable=True)
    temporary_flag = Column(TINYINT(1), nullable=True)
    content_type = Column(String(59), nullable=True)
    group_id = Column(
        Integer,
        ForeignKey("movie_group.group_id", ondelete="SET NULL"),
        nullable=True,
    )
    group_sort_order = Column(Integer, nullable=True)
    imdb_id = Column(Text, nullable=True)
    overview = Column(Text, nullable=False)
    locked_fields = Column(LONGTEXT(collation="utf8mb4_bin"), nullable=True)
    last_merged_source = Column(String(20), nullable=True)
    last_merged_at = Column(TIMESTAMP, nullable=True)

    __table_args__ = (
        CheckConstraint(
            "locked_fields is null or json_valid(locked_fields)",
            name="content_chk_1",
        ),
    )


class PhysicalCopy(MediaBase):
    """One physical, ownable copy of a physical_collection item (e.g.
    "my copy of this Blu-ray, bought at store X, owned by Y")."""

    __tablename__ = "physical_copy"

    # Composite primary key - copy_id is NOT auto-increment; it is
    # assigned by the application per collection_id (see
    # app/services/add_data/physical_collection_import.py).
    collection_id = Column(
        BINARY(16),
        ForeignKey("physical_collection.collection_id", ondelete="CASCADE"),
        primary_key=True,
    )
    copy_id = Column(Integer, primary_key=True)
    owner_id = Column(
        Integer, ForeignKey("owner.owner_id", ondelete="SET NULL"), nullable=True
    )
    store_id = Column(
        Integer, ForeignKey("store.store_id", ondelete="SET NULL"), nullable=True
    )
    purchased_at = Column(Date, nullable=True)
    price = Column(DECIMAL(10, 2), nullable=True)
    currency = Column(String(3), nullable=True)


class Disc(MediaBase):
    """A single physical disc (within a physical_collection item) -
    may be a movie disc or a bonus disc (type_disc)."""

    __tablename__ = "disc"

    disc_id = Column(BINARY(16), primary_key=True)
    type_disc = Column(Text, nullable=True)
    format = Column(Text, nullable=True)
    label = Column(String(255), nullable=True)


class Wishlist(MediaBase):
    """A wanted-but-not-yet-owned title, keyed by the same content_id
    convention as `content` (though a wishlist entry may not have a
    matching `content` row yet)."""

    __tablename__ = "wishlist"

    content_id = Column(BINARY(16), primary_key=True)
    title = Column(Text, nullable=True)
    cover_image = Column(Text, nullable=True)


class CustomList(MediaBase):
    """A user-defined list (see custom_list_manager), distinct from
    the legacy/likely-unused `list` table (deliberately not modeled -
    see TODO.md)."""

    __tablename__ = "custom_lists"

    list_id = Column(BINARY(16), primary_key=True)
    list_name = Column(String(255), nullable=False)

    __table_args__ = (UniqueConstraint("list_name", name="uk_list_name"),)


class ContentExternalSource(MediaBase):
    """Raw data fetched from an external source (TMDB/TVDB) for a
    given content row - one row per (source, content_id) pair. Used
    by the "refresh from TMDB/TVDB" + "merge" flow in
    media_catalog_route.py."""

    __tablename__ = "content_external_source"

    source = Column(String(20), primary_key=True)
    content_id = Column(
        BINARY(16),
        ForeignKey("content.content_id", ondelete="CASCADE", onupdate="CASCADE"),
        primary_key=True,
    )
    external_id = Column(String(20), nullable=True)
    data_json = Column(LONGTEXT(collation="utf8mb4_bin"), nullable=True)
    fetched_at = Column(TIMESTAMP, nullable=True)

    __table_args__ = (
        CheckConstraint("json_valid(data_json)", name="content_external_source_chk_1"),
    )


class ContentGroupMembership(MediaBase):
    """Many-to-many join table letting a movie belong to more than
    one movie_group (added on feature/movie-groups-many-to-many) -
    complements content.group_id, which still holds a single
    "primary" group for backward compatibility."""

    __tablename__ = "content_group_membership"

    content_id = Column(
        BINARY(16),
        ForeignKey("content.content_id", ondelete="CASCADE", onupdate="CASCADE"),
        primary_key=True,
    )
    group_id = Column(
        Integer,
        ForeignKey("movie_group.group_id", ondelete="CASCADE", onupdate="CASCADE"),
        primary_key=True,
    )
    sort_order = Column(Integer, nullable=True)

    __table_args__ = (
        Index(
            "idx_content_group_membership__group", "group_id", "sort_order"
        ),
    )


class ContentInPhysicalCollection(MediaBase):
    """Which content (movies) are contained in a given physical_collection
    item (e.g. which of the box set's movies this particular box
    contains) - also tracks sort order for box-set title listing."""

    __tablename__ = "content_in_physical_collection"

    collection_id = Column(
        BINARY(16),
        ForeignKey("physical_collection.collection_id", ondelete="CASCADE"),
        primary_key=True,
    )
    content_id = Column(
        BINARY(16),
        ForeignKey("content.content_id", ondelete="CASCADE"),
        primary_key=True,
    )
    box_set_title_sort = Column(Integer, nullable=True)

    __table_args__ = (
        UniqueConstraint(
            "collection_id",
            "box_set_title_sort",
            name="uk_collection_box_set_title_sort",
        ),
        Index("idx_cipc__content_id", "content_id"),
    )


class DiscRelatedContent(MediaBase):
    """Which content (movie) a given disc relates to - e.g. a bonus
    disc referencing the movie it belongs to (see disc_in.related_content_id
    for the per-copy equivalent)."""

    __tablename__ = "disc_related_content"

    disc_id = Column(
        BINARY(16),
        ForeignKey("disc.disc_id", ondelete="CASCADE", onupdate="CASCADE"),
        primary_key=True,
    )
    content_id = Column(
        BINARY(16),
        ForeignKey("content.content_id", ondelete="CASCADE", onupdate="CASCADE"),
        primary_key=True,
    )

    __table_args__ = (
        Index("idx_disc_related_content__content_id", "content_id"),
    )


class ListItem(MediaBase):
    """An entry on a "watch list" style list (distinct from the
    legacy `list` table, and from custom_lists/custom_list_entries -
    see custom_list_entries.list_item_id, which references this
    table)."""

    __tablename__ = "list_items"

    list_item_id = Column(BINARY(16), primary_key=True)
    title = Column(String(255), nullable=False)
    original_title = Column(String(255), nullable=True)
    first_release_year = Column(Integer, nullable=True)
    imdb_id = Column(String(20), nullable=True)
    tmdb_id = Column(String(20), nullable=True)
    tvdb_id = Column(String(20), nullable=True)
    season = Column(String(50), nullable=True)
    cover_image = Column(String(255), nullable=True)


class DiscBonusItem(MediaBase):
    """One bonus-feature entry (e.g. a making-of, deleted scene) on a
    given disc - seq_no is its position on that disc."""

    __tablename__ = "disc_bonus_item"

    disc_id = Column(
        BINARY(16),
        ForeignKey("disc.disc_id", ondelete="CASCADE", onupdate="CASCADE"),
        primary_key=True,
    )
    seq_no = Column(Integer, primary_key=True)
    title = Column(Text, nullable=False)
    item_type = Column(String(30), nullable=False)
    runtime_seconds = Column(Integer, nullable=True)
    notes = Column(Text, nullable=True)

    __table_args__ = (
        Index("idx_disc_bonus_item__item_type", "item_type"),
    )


class DiscInStorage(MediaBase):
    """Where a given disc physically lives (which storage container,
    and its slot number in it)."""

    __tablename__ = "disc_in_storage"

    storage_id = Column(
        BINARY(16),
        ForeignKey("storage.storage_id", ondelete="CASCADE", onupdate="CASCADE"),
        primary_key=True,
    )
    disc_id = Column(
        BINARY(16),
        ForeignKey("disc.disc_id", ondelete="CASCADE", onupdate="CASCADE"),
        primary_key=True,
    )
    number_in_storage = Column(Integer, nullable=False)

    __table_args__ = (
        UniqueConstraint(
            "storage_id", "number_in_storage", name="uk_disc_in_storage__storage__number"
        ),
        UniqueConstraint("disc_id", name="uk_disc_in_storage__disc_id"),
    )


class DiscIn(MediaBase):
    """Which disc belongs to which physical_copy (a specific owned
    copy of a box set may contain several discs) - also records the
    disc's sort order within that box set, and, for bonus discs, which
    content (movie) it relates to."""

    __tablename__ = "disc_in"

    copy_id = Column(Integer, primary_key=True)
    collection_id = Column(BINARY(16), primary_key=True)
    disc_id = Column(
        BINARY(16), ForeignKey("disc.disc_id", ondelete="CASCADE"), primary_key=True
    )
    box_set_disc_order = Column(Integer, nullable=True)
    related_content_id = Column(
        BINARY(16),
        ForeignKey("content.content_id", ondelete="SET NULL", onupdate="CASCADE"),
        nullable=True,
    )

    __table_args__ = (
        # Composite FK to physical_copy's composite primary key
        # (collection_id, copy_id) - can't be expressed as a
        # column-level ForeignKey() since it spans two columns.
        ForeignKeyConstraint(
            ["collection_id", "copy_id"],
            ["physical_copy.collection_id", "physical_copy.copy_id"],
            name="fk_disc_in__physical_copy",
            ondelete="CASCADE",
        ),
        UniqueConstraint("disc_id", name="uk_disc_in__disc_id"),
        UniqueConstraint(
            "collection_id", "copy_id", "box_set_disc_order", name="uk_disc_in__copy_order"
        ),
        Index("idx_disc_in__related_content_id", "related_content_id"),
    )


class CustomListEntry(MediaBase):
    """One movie/entry within a custom_lists list, in a given sort
    order (see custom_list_manager)."""

    __tablename__ = "custom_list_entries"

    list_id = Column(
        BINARY(16), ForeignKey("custom_lists.list_id"), primary_key=True
    )
    list_item_id = Column(
        BINARY(16), ForeignKey("list_items.list_item_id"), primary_key=True
    )
    sort_order = Column(Integer, nullable=True)

    __table_args__ = (Index("idx_list_sort", "list_id", "sort_order"),)
