"""
main.py – FastAPI-app med JWT-autentisering

Denne fila inneholder API-et:
- /health (helsesjekk)
- /protected (krever Bearer-token)
- /login (verifiserer brukernavn/passord og utsteder JWT)

Database (User, session, init) ligger i db.py
Passord-hashing ligger i auth.py
"""

import os
from datetime import datetime, timedelta

from dotenv import load_dotenv
from fastapi import FastAPI, Depends, HTTPException, status
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from jose import JWTError, jwt
from pydantic import BaseModel
from sqlalchemy.orm import Session

from db import User, get_db, init_db
from auth import verify_password

load_dotenv()

# =========================================================
# JWT-konfig
# =========================================================

SECRET_KEY = os.getenv("SECRET_KEY")
if not SECRET_KEY:
    raise ValueError("SECRET_KEY må være satt i .env filen!")

ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_MINUTES = 30

# =========================================================
# Init DB ved oppstart (opprett tabeller om nødvendig)
# =========================================================
init_db()

# =========================================================
# FastAPI app + security
# =========================================================

app = FastAPI(title="FastAPI JWT Auth")
security = HTTPBearer()


# =========================================================
# Hjelpefunksjoner (JWT + DB-oppslag)
# =========================================================

def create_access_token(data: dict, expires_delta: timedelta | None = None) -> str:
    """
    Lager et JWT-token.
    - data bør inneholde f.eks. {"sub": "<username>"}
    - exp settes alltid for utløp
    """
    to_encode = data.copy()

    expire = datetime.utcnow() + (expires_delta or timedelta(minutes=15))
    to_encode.update({"exp": expire})

    return jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)


def get_user_by_username(db: Session, username: str) -> User | None:
    """Hent bruker fra DB basert på username."""
    return db.query(User).filter(User.username == username).first()


async def get_current_user(
    credentials: HTTPAuthorizationCredentials = Depends(security),
    db: Session = Depends(get_db),
) -> User:
    """
    Dependency for beskyttede endepunkter.
    Leser Bearer-token, dekoder JWT, slår opp bruker, sjekker at den er aktiv.
    """
    credentials_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Kunne ikke validere legitimasjon",
        headers={"WWW-Authenticate": "Bearer"},
    )

    try:
        token = credentials.credentials
        payload = jwt.decode(token, SECRET_KEY, algorithms=[ALGORITHM])

        username: str | None = payload.get("sub")
        if not username:
            raise credentials_exception

    except JWTError:
        raise credentials_exception

    user = get_user_by_username(db, username=username)
    if user is None or not user.is_active:
        raise credentials_exception

    return user


# =========================================================
# Ruter
# =========================================================

@app.get("/")
async def root():
    return {"message": "FastAPI JWT Auth App"}


@app.get("/health")
async def health_check():
    return {"status": "ok"}


@app.get("/protected")
async def protected_route(current_user: User = Depends(get_current_user)):
    return {
        "message": "Du har tilgang til denne beskyttede ruten",
        "user": current_user.username,
    }


# =========================================================
# Login
# =========================================================

class LoginRequest(BaseModel):
    username: str
    password: str


@app.post("/login")
async def login(credentials: LoginRequest, db: Session = Depends(get_db)):
    """
    Validerer brukernavn/passord og returnerer JWT-token ved suksess.
    """
    user = get_user_by_username(db, credentials.username)

    if not user or not verify_password(credentials.password, user.hashed_password):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Feil brukernavn eller passord",
            headers={"WWW-Authenticate": "Bearer"},
        )

    if not user.is_active:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Bruker er deaktivert",
        )

    access_token_expires = timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES)
    access_token = create_access_token(
        data={"sub": user.username},
        expires_delta=access_token_expires,
    )

    return {"access_token": access_token, "token_type": "bearer"}


# =========================================================
# Lokal kjøring
# =========================================================

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
