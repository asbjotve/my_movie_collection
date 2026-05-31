import os
from urllib.parse import quote_plus

from dotenv import load_dotenv
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker, Session

load_dotenv()


def _build_media_database_url() -> str:
    media_database_url = os.getenv("MEDIA_DATABASE_URL")
    if media_database_url:
        return media_database_url

    db_user = os.getenv("MEDIA_DB_USER")
    db_password = os.getenv("MEDIA_DB_PASSWORD")
    db_host = os.getenv("MEDIA_DB_HOST", "localhost")
    db_port = os.getenv("MEDIA_DB_PORT", "3306")
    db_name = os.getenv("MEDIA_DB_NAME")

    missing = [
        k for k, v in {
            "MEDIA_DB_USER": db_user,
            "MEDIA_DB_PASSWORD": db_password,
            "MEDIA_DB_NAME": db_name,
        }.items() if not v
    ]

    if missing:
        raise ValueError(
            "MEDIA_DATABASE_URL is not set. Set MEDIA_DATABASE_URL or provide: "
            + ", ".join(missing)
        )

    encoded_pw = quote_plus(db_password)
    return f"mysql+pymysql://{db_user}:{encoded_pw}@{db_host}:{db_port}/{db_name}"


MEDIA_DATABASE_URL = _build_media_database_url()

media_engine = create_engine(MEDIA_DATABASE_URL)
MediaSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=media_engine)


def get_media_db():
    db: Session = MediaSessionLocal()
    try:
        yield db
    finally:
        db.close()
