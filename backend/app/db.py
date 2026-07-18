from sqlalchemy import Column, Integer, String, create_engine
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
