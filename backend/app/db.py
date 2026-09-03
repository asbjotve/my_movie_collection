from sqlalchemy import Column, Integer, String, Text, create_engine
from sqlalchemy.orm import Session, declarative_base, sessionmaker

from config.config import settings

engine = create_engine(settings.database_url)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base = declarative_base()


class User(Base):
    """Database-modell for brukere."""

    __tablename__ = "users"

    id = Column(Integer, primary_key=True, index=True)
    username = Column(String(50), unique=True, index=True, nullable=False)
    hashed_password = Column(String(255), nullable=False)
    # Enkel rolle-infrastruktur - kun "admin" finnes/brukes i dag, men
    # feltet + JWT-claim + require_role()-dependency (se security.py) er
    # på plass i forkant, slik at flere roller kan legges til senere uten
    # en ny runde med skjema-/login-endringer.
    role = Column(String(32), nullable=False, default="admin")
    # TOTP-basert 2FA (se app/auth.py for hjelpefunksjoner):
    # - totp_secret: aktivt secret, satt først når 2FA er bekreftet/på.
    # - totp_secret_pending: secret generert av /auth/2fa/setup, men ikke
    #   bekreftet ennå - flyttes til totp_secret av /auth/2fa/enable.
    # - totp_enabled: om innlogging krever en TOTP-kode i tillegg til passord.
    # - recovery_codes_json: JSON-array med hashede (Argon2) engangskoder.
    totp_secret = Column(String(64), nullable=True)
    totp_secret_pending = Column(String(64), nullable=True)
    totp_enabled = Column(Integer, default=0, nullable=False)
    recovery_codes_json = Column(Text, nullable=True)
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
