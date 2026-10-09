"""
"Sikkerhetskopiering" - last ned en ren SQL-dump av db_mediearkiv (uten
coverbilder - cover_image lagrer bare en URL/sti, ikke binærdata, se
content.cover_image i media_models.py, så en vanlig SQL-dump er
allerede "uten coverbilder" helt av seg selv) og gjenopprett fra en
slik fil igjen.

Se admin_backup_route.py og admin_tilganger.php (v19).

Backup bruker MEDIA_DB_USER (media_arkiv_admin) - mysqldump med
--single-transaction trenger kun SELECT-rettigheter (ingen LOCK
TABLES/RELOAD nødvendig for InnoDB-tabeller), så den bevisst
begrensede app-brukeren er fullt tilstrekkelig her (bekreftet ved
testkjøring).

Restore kjører derimot DROP TABLE/CREATE TABLE (mysqldump sin
standard --add-drop-table), noe media_arkiv_admin BEVISST ikke har
rettigheter til (least-privilege-valg, se også media_arkiv_admin sine
GRANTs). Restore bruker derfor en egen, separat konfigurert DB-bruker
(MEDIA_DB_RESTORE_USER/MEDIA_DB_RESTORE_PASSWORD, scopet til
db_mediearkiv.* med fulle rettigheter) som ALDRI brukes av noe annet i
applikasjonen enn akkurat denne ene funksjonen.
"""

import os
import subprocess
import tempfile
from datetime import datetime, timezone
from pathlib import Path

from config.config import settings


class BackupError(Exception):
    """Feil under backup/restore - status_code videreformidles som
    HTTPException-status i admin_backup_route.py."""

    def __init__(self, message: str, status_code: int = 500):
        super().__init__(message)
        self.status_code = status_code


def backup_filename() -> str:
    stamp = datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S")
    return f"db_mediearkiv_backup_{stamp}.sql"


def _mysql_client_env(password: str | None) -> dict:
    # MYSQL_PWD (fremfor --password=... på kommandolinjen) unngår at
    # passordet dukker opp i prosesslisten (ps aux) på serveren.
    env = os.environ.copy()
    env["MYSQL_PWD"] = password or ""
    return env


def create_backup_file() -> Path:
    """Kjører mysqldump mot db_mediearkiv og returnerer stien til en
    midlertidig fil med resultatet. Kalleren er ansvarlig for å slette
    filen etterpå (se BackgroundTask i admin_backup_route.py)."""

    tmp = tempfile.NamedTemporaryFile(prefix="mmc_backup_", suffix=".sql", delete=False)
    tmp_path = Path(tmp.name)
    tmp.close()

    cmd = [
        "mysqldump",
        "--single-transaction",
        "--routines",
        "--triggers",
        "--no-tablespaces",
        "-h", settings.MEDIA_DB_HOST,
        "-P", str(settings.MEDIA_DB_PORT),
        "-u", settings.MEDIA_DB_USER,
        settings.MEDIA_DB_NAME,
    ]

    try:
        with open(tmp_path, "wb") as out:
            result = subprocess.run(
                cmd,
                stdout=out,
                stderr=subprocess.PIPE,
                env=_mysql_client_env(settings.MEDIA_DB_PASSWORD),
                timeout=120,
            )
    except (subprocess.SubprocessError, OSError) as e:
        tmp_path.unlink(missing_ok=True)
        raise BackupError(f"Klarte ikke å kjøre mysqldump: {e}") from e

    if result.returncode != 0:
        tmp_path.unlink(missing_ok=True)
        stderr = result.stderr.decode(errors="replace")[:500]
        raise BackupError(f"mysqldump feilet: {stderr}")

    return tmp_path


def restore_from_sql(sql_bytes: bytes) -> None:
    """Kjører den gitte SQL-dumpen mot db_mediearkiv via mysql-
    klienten, autentisert som MEDIA_DB_RESTORE_USER (se modul-
    docstringen for hvorfor dette ikke kan bruke MEDIA_DB_USER).
    Overskriver/erstatter eksisterende tabeller fullstendig - dumpen
    forventes å inneholde DROP TABLE IF EXISTS + CREATE TABLE + data,
    slik mysqldump sitt standardformat gjør (se create_backup_file())."""

    if not settings.MEDIA_DB_RESTORE_USER or not settings.MEDIA_DB_RESTORE_PASSWORD:
        raise BackupError(
            "Gjenoppretting er ikke konfigurert ennå - MEDIA_DB_RESTORE_USER/"
            "MEDIA_DB_RESTORE_PASSWORD mangler i .env.",
            status_code=501,
        )

    cmd = [
        "mysql",
        "-h", settings.MEDIA_DB_HOST,
        "-P", str(settings.MEDIA_DB_PORT),
        "-u", settings.MEDIA_DB_RESTORE_USER,
        settings.MEDIA_DB_NAME,
    ]

    try:
        result = subprocess.run(
            cmd,
            input=sql_bytes,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            env=_mysql_client_env(settings.MEDIA_DB_RESTORE_PASSWORD),
            timeout=180,
        )
    except (subprocess.SubprocessError, OSError) as e:
        raise BackupError(f"Klarte ikke å kjøre mysql-restore: {e}") from e

    if result.returncode != 0:
        stderr = result.stderr.decode(errors="replace")[:1000]
        raise BackupError(f"Gjenoppretting feilet: {stderr}")
