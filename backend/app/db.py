"""
db.py – databaseoppsett (SQLAlchemy), User-modell og DB-session dependency.

Denne fila skal kunne brukes av både:
- main.py (FastAPI)
- manage_users.py (CLI)

Inneholder:
- DATABASE_URL fra .env
- engine + SessionLocal
- Base + User-modell
- init_db() for å opprette tabeller
- get_db() dependency for FastAPI
"""

import os
from dotenv import load_dotenv

from sqlalchemy import create_engine, Column, Integer, String
from sqlalchemy.orm import sessionmaker, Session, declarative_base

# Leser inn .env slik at DATABASE_URL blir tilgjengelig
load_dotenv()

DATABASE_URL = os.getenv("DATABASE_URL")
if not DATABASE_URL:
    raise ValueError("DATABASE_URL må være satt i .env filen!")

# Oppretter engine mot databasen
engine = create_engine(DATABASE_URL)

# Lager en Session factory
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)

# Base for ORM-modeller
Base = declarative_base()


class User(Base):
    """Database-modell for brukere."""
    __tablename__ = "users"

    id = Column(Integer, primary_key=True, index=True)
    username = Column(String(50), unique=True, index=True, nullable=False)
    hashed_password = Column(String(255), nullable=False)
    is_active = Column(Integer, default=1)


def init_db() -> None:
    """
    Oppretter tabeller hvis de ikke finnes.
    Kalles typisk ved oppstart av API og/eller CLI.
    """
    Base.metadata.create_all(bind=engine)


def get_db():
    """
    FastAPI dependency som gir en DB-session per request og lukker den etterpå.
    """
    db: Session = SessionLocal()
    try:
        yield db
    finally:
        db.close()
