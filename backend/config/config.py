import os
from pathlib import Path
from urllib.parse import quote_plus

from pydantic import Field
from pydantic_settings import BaseSettings, SettingsConfigDict

ENV = os.getenv("OSLOMET_ENV", "dev")

BASE_DIR = Path(__file__).resolve().parent
DEFAULT_ENV_FILE = BASE_DIR / ".env"
SCOPED_ENV_FILE = BASE_DIR / f".env.{ENV}"
ACTIVE_ENV_FILE = SCOPED_ENV_FILE if SCOPED_ENV_FILE.exists() else DEFAULT_ENV_FILE


class Settings(BaseSettings):
    SECRET_KEY: str | None = None

    DATABASE_URL: str | None = None
    DB_USER: str | None = None
    DB_PASSWORD: str | None = None
    DB_HOST: str = Field(default="localhost")
    DB_PORT: int = Field(default=3306)
    DB_NAME: str | None = None

    MEDIA_DATABASE_URL: str | None = None
    MEDIA_DB_USER: str | None = None
    MEDIA_DB_PASSWORD: str | None = None
    MEDIA_DB_HOST: str = Field(default="localhost")
    MEDIA_DB_PORT: int = Field(default=3306)
    MEDIA_DB_NAME: str | None = None

    model_config = SettingsConfigDict(
        env_file=str(ACTIVE_ENV_FILE),
        env_file_encoding="utf-8",
        extra="ignore",
    )

    @staticmethod
    def _build_database_url(
        direct_url: str | None,
        user: str | None,
        password: str | None,
        host: str,
        port: int,
        name: str | None,
        label: str,
    ) -> str:
        if direct_url:
            return direct_url

        missing = [
            key
            for key, value in {
                f"{label}_USER": user,
                f"{label}_PASSWORD": password,
                f"{label}_NAME": name,
            }.items()
            if not value
        ]
        if missing:
            url_name = "DATABASE_URL" if label == "DB" else "MEDIA_DATABASE_URL"
            raise ValueError(
                f"{url_name} is not set. Set {url_name} or provide: {', '.join(missing)}"
            )

        encoded_password = quote_plus(password)
        return f"mysql+pymysql://{user}:{encoded_password}@{host}:{port}/{name}"

    @property
    def database_url(self) -> str:
        return self._build_database_url(
            direct_url=self.DATABASE_URL,
            user=self.DB_USER,
            password=self.DB_PASSWORD,
            host=self.DB_HOST,
            port=self.DB_PORT,
            name=self.DB_NAME,
            label="DB",
        )

    @property
    def media_database_url(self) -> str:
        return self._build_database_url(
            direct_url=self.MEDIA_DATABASE_URL,
            user=self.MEDIA_DB_USER,
            password=self.MEDIA_DB_PASSWORD,
            host=self.MEDIA_DB_HOST,
            port=self.MEDIA_DB_PORT,
            name=self.MEDIA_DB_NAME,
            label="MEDIA_DB",
        )


settings = Settings()
