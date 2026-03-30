"""
db.py – databaseoppsett (SQLAlchemy), User-modell og DB-session dependency.

Støtter to måter å konfigurere databasen på via .env:

A) Én samlet URL (klassisk):
   DATABASE_URL=mysql+pymysql://user:pass@localhost:3306/dbname

B) Separate variabler (anbefalt, enklere å vedlikeholde):
   DB_USER=mmc_admin
   DB_PASSWORD=...
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=mmc_userdb

Hvis DATABASE_URL ikke er satt, bygges den automatisk fra DB_*.
Passord URL-encodes automatisk for å tåle spesialtegn.
"""

import os
from urllib.parse import quote_plus

from dotenv import load_dotenv
from sqlalchemy import create_engine, Column, Integer, String
from sqlalchemy.orm import sessionmaker, Session, declarative_base

# Leser inn .env-variabler slik at os.getenv(...) finner dem
load_dotenv()

# ---------------------------------------------------------
# Bygg DATABASE_URL (enten direkte, eller fra DB_* variabler)
# ---------------------------------------------------------

DATABASE_URL = os.getenv("DATABASE_URL")

if not DATABASE_URL:
    DB_USER = os.getenv("DB_USER")
    DB_PASSWORD = os.getenv("DB_PASSWORD")
    DB_HOST = os.getenv("DB_HOST", "localhost")
    DB_PORT = os.getenv("DB_PORT", "3306")
    DB_NAME = os.getenv("DB_NAME")

    # Valider at vi har det vi trenger for å bygge URL
    missing = [k for k, v in {
        "DB_USER": DB_USER,
        "DB_PASSWORD": DB_PASSWORD,
        "DB_NAME": DB_NAME,
    }.items() if not v]

    if missing:
        raise ValueError(
            "DATABASE_URL is not set. Set DATABASE_URL or provide: "
            + ", ".join(missing)
        )

    # URL-encode passord slik at spesialtegn ikke ødelegger URL-en
    encoded_pw = quote_plus(DB_PASSWORD)

    DATABASE_URL = f"mysql+pymysql://{DB_USER}:{encoded_pw}@{DB_HOST}:{DB_PORT}/{DB_NAME}"

# ---------------------------------------------------------
# SQLAlchemy setup
# ---------------------------------------------------------

engine = create_engine(DATABASE_URL)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base = declarative_base()


class User(Base):
    """Database-modell for brukere."""
    __tablename__ = "users"

    id = Column(Integer, primary_key=True, index=True)
    username = Column(String(50), unique=True, index=True, nullable=False)
    hashed_password = Column(String(255), nullable=False)
    is_active = Column(Integer, default=1)


def init_db() -> None:
    """Oppretter tabeller hvis de ikke finnes."""
    Base.metadata.create_all(bind=engine)


def get_db():
    """FastAPI dependency som gir en DB-session per request og lukker den etterpå."""
    db: Session = SessionLocal()
    try:
        yield db
    finally:
        db.close()
