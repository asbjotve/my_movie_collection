-- Rollback for migration_user_role_up.sql

ALTER TABLE users
    DROP COLUMN role;
