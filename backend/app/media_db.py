from sqlalchemy import create_engine
from sqlalchemy.orm import Session, sessionmaker

from config.config import settings

# pool_pre_ping: tester at en tilkobling fra poolen fortsatt er i live
# før den brukes (kobler automatisk til på nytt hvis ikke) - uten dette
# feilet det første kallet mot databasen etter en lengre stille periode
# (f.eks. over natten) med "Lost connection to MySQL server during
# query", fordi MariaDB sin wait_timeout (8 timer) hadde lukket
# tilkoblingen i bakgrunnen uten at SQLAlchemy sin pool visste om det.
# pool_recycle resirkulerer tilkoblinger noe før wait_timeout uansett,
# som en ekstra sikkerhet.
media_engine = create_engine(
    settings.media_database_url,
    pool_pre_ping=True,
    pool_recycle=3600,
)
MediaSessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=media_engine)


def get_media_db():
    db: Session = MediaSessionLocal()
    try:
        yield db
    finally:
        db.close()
