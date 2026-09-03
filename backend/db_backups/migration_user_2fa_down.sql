-- Rollback for migration_user_2fa_up.sql

ALTER TABLE users
    DROP COLUMN recovery_codes_json,
    DROP COLUMN totp_enabled,
    DROP COLUMN totp_secret_pending,
    DROP COLUMN totp_secret;
