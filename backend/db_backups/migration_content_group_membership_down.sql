-- Rollback for migration_content_group_membership_up.sql
-- Restores DB to exact pre-migration state. Since the migration was
-- purely additive (content.group_id / content.group_sort_order were
-- never touched), this is a single safe DROP TABLE.

DROP TABLE IF EXISTS `content_group_membership`;
