-- Legger til felter i mmc_userdb.users for TOTP-basert 2FA (Google
-- Authenticator/Authy-type app). Additiv migrasjon - fjerner/endrer
-- ingenting eksisterende.
--
-- totp_secret            : base32-secret brukt til å generere/verifisere
--                           TOTP-koder. NULL inntil bruker har fullført
--                           oppsett (se totp_secret_pending under).
-- totp_secret_pending     : midlertidig secret generert av
--                           POST /auth/2fa/setup, flyttes til
--                           totp_secret først når brukeren har bekreftet
--                           med en gyldig kode via POST /auth/2fa/enable.
--                           Forhindrer at en påbegynt, ubekreftet
--                           2FA-oppsett aktiveres ved et uhell.
-- totp_enabled            : 1 hvis 2FA er slått på for brukeren (krever
--                           kode ved innlogging), ellers 0.
-- recovery_codes_json     : JSON-array med hashede (Argon2) engangskoder
--                           som kan brukes i stedet for TOTP-kode hvis
--                           brukeren mister tilgang til autentisator-
--                           appen. Hver kode fjernes fra listen når den
--                           er brukt.

ALTER TABLE users
    ADD COLUMN totp_secret VARCHAR(64) NULL AFTER hashed_password,
    ADD COLUMN totp_secret_pending VARCHAR(64) NULL AFTER totp_secret,
    ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret_pending,
    ADD COLUMN recovery_codes_json TEXT NULL AFTER totp_enabled;
