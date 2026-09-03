-- Legger til en enkel rolle-kolonne på mmc_userdb.users. Additiv
-- migrasjon - fjerner/endrer ingenting eksisterende.
--
-- role : tekstfelt (foreløpig kun brukt med verdien 'admin', siden det
--        per nå kun finnes én rolle). Lagt til nå, i forkant av faktisk
--        behov, slik at infrastrukturen (kolonne + JWT-claim + dependency
--        i security.py) er på plass den dagen det trengs flere roller
--        (f.eks. en fremtidig 'viewer'-rolle med kun lesetilgang).
--
-- Alle eksisterende brukere settes til 'admin' som standardverdi, siden
-- det er den eneste rollen som finnes i dag og reflekterer hvordan
-- kontoene faktisk brukes.

ALTER TABLE users
    ADD COLUMN role VARCHAR(32) NOT NULL DEFAULT 'admin' AFTER hashed_password;
