# Alembic i dette prosjektet

Alembic styrer kun **strukturen** (skjemaet) i `mmc_userdb`
(brukere/tilganger/innstillinger) - de tabellene som har ordentlige
SQLAlchemy ORM-modeller i `app/db.py` (`User`, `SectionAccess`,
`AppSetting`).

`db_mediearkiv` (filmkatalogen: `content`, filmgrupper osv.) har ingen
ORM-modeller ennå og styres fortsatt manuelt via SQL-filer i
`backend/db_backups/`, akkurat som før. Det kan legges under Alembic
også senere, men krever da at tabellene først modelleres i Python.

## Vanlige kommandoer (kjøres fra `backend/`)

```
.venv/bin/alembic revision --autogenerate -m "beskrivelse av endringen"
.venv/bin/alembic upgrade head      # kjør nye migrasjoner
.venv/bin/alembic downgrade -1      # rull tilbake én migrasjon (skjema, IKKE data)
.venv/bin/alembic current           # vis hvilken revisjon databasen står på
```

Alembic leser tilkoblings-URL fra prosjektets egen `config/.env` (samme
som resten av backend) via `alembic/env.py` - ingenting å endre i
`alembic.ini` per miljø.

## Baseline

Den aller første migrasjonen (`baseline: existing mmc_userdb schema`)
er bevisst tom (no-op) - den markerer bare at den allerede eksisterende
databasen er "à jour", uten å kjøre noen SQL mot den (satt med
`alembic stamp head`, ikke `upgrade head`, ved førstegangsoppsettet).
