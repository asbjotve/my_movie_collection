"""
rate_limit.py – enkel in-memory brute-force-beskyttelse for
POST /auth/login og POST /auth/login/2fa (se auth_route.py).

Hvorfor in-memory (ikke Redis e.l.): backend kjører som én eneste
uvicorn-worker (se ecosystem.config.js: "--workers 1"), så det finnes
ingen flere prosesser å synkronisere telleren mellom - et enkelt,
trådsikkert dict i prosessminnet er nok. Ulempen er at tellerne
nullstilles ved restart av backend-prosessen - akseptert bevisst, se
TODO.md.

To uavhengige lag:

1. Per brukernavn (LOGIN_ATTEMPTS_PER_USERNAME) - stopper gjetting av
   passord/TOTP-/recovery-koder mot én bestemt konto. Nullstilles ved
   vellykket innlogging for den kontoen.

2. Per IP (LOGIN_ATTEMPTS_PER_IP) - stopper username-enumeration (å
   prøve mange ulike brukernavn fra samme kilde). Nullstilles ALDRI
   ved suksess (kun når tidsvinduet går ut) - siden frontend kaller
   backend server-til-server, deler ALLE ekte sluttbrukere samme IP
   sett fra backend (se get_client_ip() nedenfor), så én brukers
   vellykkede innlogging skal ikke kunne "resette" budsjettet for
   resten. Terskelen er derfor satt høyere enn per-brukernavn-laget.

Begge lagene bruker samme lås-mekanikk (se LoginRateLimiter): et gitt
antall mislykkede forsøk innenfor et tidsvindu utløser en tidsbegrenset
lås (429 Too Many Requests + Retry-After) for akkurat den nøkkelen.
"""

import threading
import time

from fastapi import HTTPException, Request, status

# Terskelverdier bekreftet med bruker (se TODO.md "Rate-limiting on
# /auth/login").
USERNAME_MAX_ATTEMPTS = 5
USERNAME_WINDOW_SECONDS = 15 * 60
USERNAME_LOCKOUT_SECONDS = 15 * 60

IP_MAX_ATTEMPTS = 20
IP_WINDOW_SECONDS = 15 * 60
IP_LOCKOUT_SECONDS = 15 * 60


class LoginRateLimiter:
    """Teller mislykkede forsøk per nøkkel (brukernavn ELLER IP,
    avhengig av hvilken instans) innenfor et glidende tidsvindu, og
    låser nøkkelen i en fast periode når terskelen nås.
    """

    def __init__(self, max_attempts: int, window_seconds: int, lockout_seconds: int):
        self._max_attempts = max_attempts
        self._window_seconds = window_seconds
        self._lockout_seconds = lockout_seconds
        self._lock = threading.Lock()
        self._failure_timestamps: dict[str, list[float]] = {}
        self._locked_until: dict[str, float] = {}

    def raise_if_locked(self, key: str) -> None:
        """Kaster HTTPException(429) hvis nøkkelen er låst akkurat nå.
        Kalles FØR passord/kode sjekkes, slik at en låst konto/IP ikke
        trenger å gå gjennom selve verifiseringen i det hele tatt."""
        now = time.time()
        with self._lock:
            locked_until = self._locked_until.get(key)
            if locked_until is None:
                return
            remaining = locked_until - now
            if remaining <= 0:
                # Låsen er utløpt - rydd opp slik at nøkkelen starter
                # helt på nytt ved neste forsøk.
                del self._locked_until[key]
                self._failure_timestamps.pop(key, None)
                return

        retry_after = int(remaining) + 1
        raise HTTPException(
            status_code=status.HTTP_429_TOO_MANY_REQUESTS,
            detail=(
                "For mange mislykkede forsøk. Prøv igjen om "
                f"{retry_after} sekunder."
            ),
            headers={"Retry-After": str(retry_after)},
        )

    def record_failure(self, key: str) -> None:
        now = time.time()
        cutoff = now - self._window_seconds
        with self._lock:
            timestamps = self._failure_timestamps.setdefault(key, [])
            timestamps.append(now)
            timestamps[:] = [t for t in timestamps if t >= cutoff]
            if len(timestamps) >= self._max_attempts:
                self._locked_until[key] = now + self._lockout_seconds

    def record_success(self, key: str) -> None:
        with self._lock:
            self._failure_timestamps.pop(key, None)
            self._locked_until.pop(key, None)


# Delte instanser importert av auth_route.py - ett sett med tellere
# per lag, gjenbrukt på tvers av alle forespørsler i prosessen.
username_rate_limiter = LoginRateLimiter(
    USERNAME_MAX_ATTEMPTS, USERNAME_WINDOW_SECONDS, USERNAME_LOCKOUT_SECONDS
)
ip_rate_limiter = LoginRateLimiter(
    IP_MAX_ATTEMPTS, IP_WINDOW_SECONDS, IP_LOCKOUT_SECONDS
)


def get_client_ip(request: Request) -> str:
    """Henter sluttbrukerens ekte IP.

    Backend kalles server-til-server av frontend (PHP), ikke direkte
    fra nettleseren - se frontend/_shared/auth.php sin auth_api_post().
    Uten videre tiltak ville request.client.host derfor alltid vært
    PHP-serverens IP for ALLE brukere. auth_api_post() setter derfor
    headeren "X-Forwarded-For" til $_SERVER['REMOTE_ADDR'] (sluttbrukerens
    IP sett fra PHP), som brukes her hvis den finnes.

    Det er trygt å stole blindt på denne headeren i dette oppsettet
    fordi uvicorn kun lytter på den interne docker-bro-IP-en
    172.19.0.1 (se ecosystem.config.js), ikke på en offentlig IP - det
    er med andre ord ikke mulig for noen utenfor denne serveren å nå
    dette endepunktet direkte og forfalske headeren.
    """
    forwarded = request.headers.get("x-forwarded-for")
    if forwarded:
        return forwarded.split(",")[0].strip()
    return request.client.host if request.client else "unknown"
