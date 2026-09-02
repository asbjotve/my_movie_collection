-- Rollback for migration_disc_related_content_up.sql
-- Restores DB to exact pre-migration state. Since the migration was
-- purely additive, this is a single safe DROP TABLE (existing data in
-- disc_in.related_content_id / disc / physical_collection is untouched
-- either way, but a full table backup also exists at:
--   backend/db_backups/pre_disc_related_content_<timestamp>.sql
-- in case a full restore is ever needed).

DROP TABLE IF EXISTS `disc_related_content`;
