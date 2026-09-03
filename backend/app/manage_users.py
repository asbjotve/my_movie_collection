#!/usr/bin/env python3
"""
manage_users.py – CLI-script for å administrere brukere i databasen

Kommandoer:
  add <brukernavn> <passord> [--role=<rolle>]   Legg til ny bruker (rolle: admin, default admin)
  list                                          List alle brukere (med rolle)
  delete <brukernavn>                           Slett bruker
  deactivate <brukernavn>                       Deaktiver bruker
  set-role <brukernavn> <rolle>                 Endre rollen til en eksisterende bruker

Merk:
- Scriptet bruker db.py for tilgang til User-modell og SessionLocal
- Scriptet bruker auth.py for hashing av passord
- Tabeller opprettes automatisk via init_db()
- Roller: per i dag finnes kun "admin" (default). Infrastrukturen
  (kolonnen users.role + require_role() i security.py) er lagt til i
  forkant, slik at flere roller kan tas i bruk senere uten videre
  skjemaendringer.
"""

import sys
from pathlib import Path

BACKEND_DIR = Path(__file__).resolve().parents[1]
if str(BACKEND_DIR) not in sys.path:
    sys.path.insert(0, str(BACKEND_DIR))

from app.db import User, SessionLocal, init_db
from app.auth import get_password_hash

# Sørg for at tabeller finnes før vi begynner å jobbe
init_db()

KNOWN_ROLES = ("admin",)


def add_user(username: str, password: str, role: str = "admin") -> bool:
    """Legg til en ny bruker."""
    db = SessionLocal()
    try:
        existing_user = db.query(User).filter(User.username == username).first()
        if existing_user:
            print(f"❌ Bruker '{username}' eksisterer allerede!")
            return False

        hashed_password = get_password_hash(password)
        new_user = User(username=username, hashed_password=hashed_password, role=role)

        db.add(new_user)
        db.commit()

        print(f"✅ Bruker '{username}' ble opprettet (rolle: {role})!")
        return True
    except Exception as e:
        print(f"❌ Feil ved oppretting av bruker: {e}")
        db.rollback()
        return False
    finally:
        db.close()


def list_users() -> None:
    """List alle brukere."""
    db = SessionLocal()
    try:
        users = db.query(User).all()
        if not users:
            print("Ingen brukere funnet.")
            return

        print("\n📋 Brukere i databasen:")
        print("-" * 50)
        for user in users:
            status = "✓ Aktiv" if user.is_active else "✗ Inaktiv"
            print(f"ID: {user.id} | Brukernavn: {user.username} | Rolle: {user.role} | {status}")
        print("-" * 50)
    finally:
        db.close()


def delete_user(username: str) -> bool:
    """Slett en bruker."""
    db = SessionLocal()
    try:
        user = db.query(User).filter(User.username == username).first()
        if not user:
            print(f"❌ Bruker '{username}' finnes ikke!")
            return False

        db.delete(user)
        db.commit()

        print(f"✅ Bruker '{username}' ble slettet!")
        return True
    except Exception as e:
        print(f"❌ Feil ved sletting av bruker: {e}")
        db.rollback()
        return False
    finally:
        db.close()


def deactivate_user(username: str) -> bool:
    """Deaktiver en bruker (setter is_active = 0)."""
    db = SessionLocal()
    try:
        user = db.query(User).filter(User.username == username).first()
        if not user:
            print(f"❌ Bruker '{username}' finnes ikke!")
            return False

        user.is_active = 0
        db.commit()

        print(f"✅ Bruker '{username}' ble deaktivert!")
        return True
    except Exception as e:
        print(f"❌ Feil ved deaktivering av bruker: {e}")
        db.rollback()
        return False
    finally:
        db.close()


def set_role(username: str, role: str) -> bool:
    """Endre rollen til en eksisterende bruker."""
    db = SessionLocal()
    try:
        user = db.query(User).filter(User.username == username).first()
        if not user:
            print(f"❌ Bruker '{username}' finnes ikke!")
            return False

        user.role = role
        db.commit()

        print(f"✅ Rollen til '{username}' er nå '{role}'!")
        return True
    except Exception as e:
        print(f"❌ Feil ved endring av rolle: {e}")
        db.rollback()
        return False
    finally:
        db.close()


def print_usage_and_exit() -> None:
    print(
        """
Bruk: python manage_users.py <kommando> [argumenter]

Kommandoer:
  add <brukernavn> <passord> [--role=<rolle>]   Legg til ny bruker (default rolle: admin)
  list                                          List alle brukere
  delete <brukernavn>                           Slett bruker
  deactivate <brukernavn>                       Deaktiver bruker
  set-role <brukernavn> <rolle>                 Endre rollen til en eksisterende bruker
"""
    )
    sys.exit(1)


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print_usage_and_exit()

    command = sys.argv[1]

    if command == "add":
        if len(sys.argv) < 4:
            print("❌ Bruk: python manage_users.py add <brukernavn> <passord> [--role=<rolle>]")
            sys.exit(1)
        role = "admin"
        for extra_arg in sys.argv[4:]:
            if extra_arg.startswith("--role="):
                role = extra_arg.split("=", 1)[1]
        if role not in KNOWN_ROLES:
            print(f"❌ Ukjent rolle '{role}'. Gyldige roller: {', '.join(KNOWN_ROLES)}")
            sys.exit(1)
        add_user(sys.argv[2], sys.argv[3], role)

    elif command == "list":
        list_users()

    elif command == "delete":
        if len(sys.argv) != 3:
            print("❌ Bruk: python manage_users.py delete <brukernavn>")
            sys.exit(1)
        delete_user(sys.argv[2])

    elif command == "deactivate":
        if len(sys.argv) != 3:
            print("❌ Bruk: python manage_users.py deactivate <brukernavn>")
            sys.exit(1)
        deactivate_user(sys.argv[2])

    elif command == "set-role":
        if len(sys.argv) != 4:
            print("❌ Bruk: python manage_users.py set-role <brukernavn> <rolle>")
            sys.exit(1)
        new_role = sys.argv[3]
        if new_role not in KNOWN_ROLES:
            print(f"❌ Ukjent rolle '{new_role}'. Gyldige roller: {', '.join(KNOWN_ROLES)}")
            sys.exit(1)
        set_role(sys.argv[2], new_role)

    else:
        print(f"❌ Ukjent kommando: {command}")
        print_usage_and_exit()
