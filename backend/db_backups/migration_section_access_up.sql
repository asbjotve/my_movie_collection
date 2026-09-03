-- Ny tabell for å styre om enkelt-seksjoner på forsiden (v18/index.php)
-- krever innlogging eller er åpne - i stedet for at dette er hardkodet
-- i $sectionAccess-arrayet i index.php. Additiv migrasjon.
--
-- section_key     : samme nøkler som i $sectionAccess i index.php
--                    (mine_filmer, onskeliste, andre_lister,
--                    administrering).
-- requires_login  : 1 = seksjonen vises låst/krever innlogging,
--                    0 = åpen for alle.

CREATE TABLE IF NOT EXISTS section_access (
    section_key VARCHAR(64) NOT NULL PRIMARY KEY,
    requires_login TINYINT(1) NOT NULL DEFAULT 0
);

-- Seed med samme verdier som dagens hardkodede $sectionAccess i
-- index.php, slik at ingenting endrer seg for besøkende ved overgangen.
INSERT INTO section_access (section_key, requires_login) VALUES
    ('mine_filmer', 0),
    ('onskeliste', 1),
    ('andre_lister', 1),
    ('administrering', 1)
ON DUPLICATE KEY UPDATE requires_login = VALUES(requires_login);
