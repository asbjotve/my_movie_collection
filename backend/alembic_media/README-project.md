# Alembic for db_mediearkiv

Separate Alembic environment (own `alembic_media.ini` + `alembic_media/`
folder, own `alembic_version` table inside `db_mediearkiv`) from the
`mmc_userdb` one in `alembic/` - mirrors the existing dual-connection
split between `app/db.py` and `app/media_db.py`.

## Models

ORM models live in `app/media_models.py` (`MediaBase`), separate from
`app/db.py`'s `Base`. They exist purely so Alembic's autogenerate has
something to diff against - the application itself still reads/writes
db_mediearkiv via raw SQL (see `app/services/media_catalog.py`).

Tables are being modeled in small batches, starting with the ones that
have no outgoing foreign keys:

- [x] Batch 1: `movie_group`, `owner`, `store`, `storage`,
      `physical_collection`
- [ ] Remaining ~15 tables (`content`, `disc`, `physical_copy`,
      `custom_lists`, `wishlist`, etc.) - to be added in later batches.

Until every table is modeled, `alembic revision --autogenerate` WILL
propose dropping every not-yet-modeled table - that's a false positive
from incomplete model coverage, not a real diff. Strip those from the
generated migration (or write it by hand) until the batch it belongs
to is modeled.

## Permissions

The app's runtime DB user (`media_arkiv_admin`) intentionally has no
CREATE/ALTER/DROP privileges (least-privilege). Running Alembic against
db_mediearkiv therefore requires a separate, migration-only user (e.g.
`media_arkiv_migrator`) with:

```sql
GRANT SELECT, CREATE, ALTER, DROP, INDEX, REFERENCES ON db_mediearkiv.* TO 'media_arkiv_migrator'@'<host>';
GRANT INSERT, UPDATE, DELETE ON db_mediearkiv.alembic_version TO 'media_arkiv_migrator'@'<host>';
```

Only the second grant (on `alembic_version` specifically) includes
INSERT/UPDATE/DELETE - Alembic needs to write its own bookkeeping row,
but has no reason to write to the real data tables.

Never put these credentials in `config/.env` (that file is read by the
running FastAPI app too) - pass them as one-off environment variables
when invoking Alembic instead, e.g.:

```
MEDIA_DB_USER=media_arkiv_migrator MEDIA_DB_PASSWORD='...' \
  .venv/bin/alembic -c alembic_media.ini revision --autogenerate -m "..."
```

## Common commands (run from `backend/`)

```
.venv/bin/alembic -c alembic_media.ini revision --autogenerate -m "..."
.venv/bin/alembic -c alembic_media.ini upgrade head
.venv/bin/alembic -c alembic_media.ini current
```
