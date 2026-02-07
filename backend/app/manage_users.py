#!/usr/bin/env python3
"""
CLI-script for å administrere brukere via SSH
"""
import sys
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from dotenv import load_dotenv
import os

# Import fra main.py
from main import User, Base, DATABASE_URL, get_password_hash

load_dotenv()

engine = create_engine(DATABASE_URL)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

def add_user(username: str, password: str):
    """Legg til en ny bruker"""
    db = SessionLocal()
    try:
        # Sjekk om bruker allerede eksisterer
        existing_user = db.query(User).filter(User.username == username).first()
        if existing_user:
            print(f"❌ Bruker '{username}' eksisterer allerede!")
            return False
        
        # Hash passord og opprett bruker (bruker funksjonen fra main.py)
        hashed_password = get_password_hash(password)
        new_user = User(username=username, hashed_password=hashed_password)
        db.add(new_user)
        db.commit()
        print(f"✅ Bruker '{username}' ble opprettet!")
        return True
    except Exception as e:
        print(f"❌ Feil ved oppretting av bruker: {e}")
        db.rollback()
        return False
    finally:
        db.close()

def list_users():
    """List alle brukere"""
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
            print(f"ID: {user.id} | Brukernavn: {user.username} | {status}")
        print("-" * 50)
    finally:
        db.close()

def delete_user(username: str):
    """Slett en bruker"""
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

def deactivate_user(username: str):
    """Deaktiver en bruker"""
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

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("""
Bruk: python manage_users.py <kommando> [argumenter]

Kommandoer:
  add <brukernavn> <passord>    Legg til ny bruker
  list                          List alle brukere
  delete <brukernavn>           Slett bruker
  deactivate <brukernavn>       Deaktiver bruker
        """)
        sys.exit(1)
    
    command = sys.argv[1]
    
    if command == "add":
        if len(sys.argv) != 4:
            print("❌ Bruk: python manage_users.py add <brukernavn> <passord>")
            sys.exit(1)
        add_user(sys.argv[2], sys.argv[3])
    
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
    
    else:
        print(f"❌ Ukjent kommando: {command}")
        sys.exit(1)
