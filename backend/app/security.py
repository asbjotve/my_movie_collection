"""
security.py – JWT-håndtering (opprette/dekode tokens) + get_current_user
dependency, brukt av /auth-rutene og av alle fremtidige beskyttede
endepunkter.

Skilt ut fra server.py slik at routers (f.eks. app/routes/auth_route.py)
kan importere dette uten å importere hele FastAPI-appen (unngår
sirkulær import).

To typer tokens brukes (skilt via "type"-claim i JWT-payloaden):
- "access": det vanlige, fullverdige tokenet - kreves av get_current_user.
- "preauth": kortlevd token gitt av POST /auth/login når en bruker har
  2FA på, men ikke har oppgitt kode ennå. Kan KUN brukes til å kalle
  POST /auth/login/2fa (se app/routes/auth_route.py) - fungerer ikke
  som Bearer-token på andre endepunkter.
"""

from datetime import datetime, timedelta

from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from jose import JWTError, jwt
from sqlalchemy.orm import Session

from app.db import User, get_db
from config.config import settings

SECRET_KEY = settings.SECRET_KEY
if not SECRET_KEY:
    raise ValueError("SECRET_KEY må være satt i config/.env filen!")

ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_MINUTES = 30
PRE_AUTH_TOKEN_EXPIRE_MINUTES = 5

security = HTTPBearer()


def create_token(data: dict, token_type: str, expires_delta: timedelta) -> str:
    """Lager et JWT-token. 'type'-claim settes til token_type ("access"
    eller "preauth") slik at tokens ikke kan brukes om hverandre."""
    to_encode = data.copy()
    expire = datetime.utcnow() + expires_delta
    to_encode.update({"exp": expire, "type": token_type})
    return jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)


def create_access_token(username: str) -> str:
    return create_token(
        {"sub": username},
        token_type="access",
        expires_delta=timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES),
    )


def create_preauth_token(username: str) -> str:
    return create_token(
        {"sub": username},
        token_type="preauth",
        expires_delta=timedelta(minutes=PRE_AUTH_TOKEN_EXPIRE_MINUTES),
    )


def decode_token(token: str, expected_type: str) -> str:
    """Dekoder et JWT og returnerer 'sub' (username) hvis tokenet er
    gyldig og har riktig 'type'-claim. Kaster HTTPException(401) ellers."""
    invalid_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Kunne ikke validere token",
        headers={"WWW-Authenticate": "Bearer"},
    )
    try:
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])
    except JWTError:
        raise invalid_exception

    if payload.get("type") != expected_type:
        raise invalid_exception

    username = payload.get("sub")
    if not username:
        raise invalid_exception

    return username


def get_user_by_username(db: Session, username: str) -> User | None:
    return db.query(User).filter(User.username == username).first()


async def get_current_user(
    credentials: HTTPAuthorizationCredentials = Depends(security),
    db: Session = Depends(get_db),
) -> User:
    """Dependency for beskyttede endepunkter. Krever et gyldig,
    fullverdig ("access") Bearer-token - et "preauth"-token (utstedt før
    2FA-koden er bekreftet) godtas ikke her."""
    username = decode_token(credentials.credentials, expected_type="access")

    user = get_user_by_username(db, username=username)
    if user is None or not user.is_active:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Kunne ikke validere token",
            headers={"WWW-Authenticate": "Bearer"},
        )

    return user
