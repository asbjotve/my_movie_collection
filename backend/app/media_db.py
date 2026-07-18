from sqlalchemy import create_engine
from sqlalchemy.orm import Session, sessionmaker

from config.config import settings

media_engine = create_engine(settings.media_database_url)
MediaSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=media_engine)


def get_media_db():
    db: Session = MediaSessionLocal()
    try:
        yield db
    finally:
        db.close()
