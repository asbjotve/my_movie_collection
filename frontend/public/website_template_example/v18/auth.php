<?php
declare(strict_types=1);

/**
 * auth.php – delt bibliotek for innlogging i website_template_example
 * (v18), brukt av login.php/logout.php og av index.php/detail.php for
 * å sjekke om noen er innlogget.
 *
 * ============================================================
 *  HVORDAN DETTE HENGER SAMMEN MED BACKEND-API-ET
 *  Selve innlogging/2FA-logikken (passord-sjekk, JWT, TOTP) ligger i
 *  FastAPI-backend (se backend/app/routes/auth_route.py):
 *      POST /auth/login       - brukernavn+passord
 *      POST /auth/login/2fa   - andre steg hvis 2FA er på
 *  Denne fila kaller disse endepunktene server-side (samme mønster som
 *  api.php sin proxy mot resten av API-et) og lagrer KUN resultatet
 *  (username + access_token) i en vanlig PHP-sesjon
 *  ($_SESSION - identifisert via en cookie, PHPSESSID, som PHP setter
 *  automatisk). Selve JWT-tokenet eksponeres aldri til nettleseren/JS.
 * ============================================================
 */

const AUTH_API_BASE_URL = 'http://172.19.0.1:9500';

/**
 * Starter PHP-sesjonen (med fornuftige cookie-innstillinger) hvis den
 * ikke allerede er startet. Må kalles før noe leses fra/skrives til
 * $_SESSION - kalles automatisk av alle funksjonene under.
 */
function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,       // utløper når nettleseren lukkes
        'path' => '/',
        'httponly' => true,    // ikke tilgjengelig fra JavaScript
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Returnerer true hvis en bruker er innlogget i denne sesjonen. */
function is_logged_in(): bool
{
    auth_start_session();
    return !empty($_SESSION['auth_username']) && !empty($_SESSION['auth_access_token']);
}

/** Returnerer innlogget brukers brukernavn, eller null hvis ikke innlogget. */
function current_username(): ?string
{
    auth_start_session();
    return $_SESSION['auth_username'] ?? null;
}

/**
 * Sender en autentisert forespørsel (med Bearer access_token fra
 * PHP-sesjonen) til et /auth/...-endepunkt som krever innlogging
 * (f.eks. 2FA-oppsett/aktivering/deaktivering). Returnerer
 * [httpCode, decoded_json_body_or_null] - samme form som
 * auth_api_post(), men uten body er GET, med body er POST.
 */
function auth_api_authenticated(string $method, string $path, ?array $body = null): array
{
    auth_start_session();
    $accessToken = $_SESSION['auth_access_token'] ?? null;
    if (!$accessToken) {
        return [401, ['detail' => 'Ikke innlogget']];
    }

    $ch = curl_init(AUTH_API_BASE_URL . $path);
    $headers = ['Authorization: Bearer ' . $accessToken];

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ];

    if ($method === 'POST') {
        $options[CURLOPT_CUSTOMREQUEST] = 'POST';
        $options[CURLOPT_POSTFIELDS] = json_encode($body ?? []);
        $headers[] = 'Content-Type: application/json';
    }
    $options[CURLOPT_HTTPHEADER] = $headers;

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        return [502, null];
    }

    return [$httpCode, json_decode($response, true)];
}

/**
 * Sender en POST-forespørsel til et /auth/...-endepunkt i backend og
 * returnerer [httpCode, decoded_json_body_or_null].
 */
function auth_api_post(string $path, array $body): array
{
    $ch = curl_init(AUTH_API_BASE_URL . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        return [502, null];
    }

    return [$httpCode, json_decode($response, true)];
}

/**
 * Steg 1 av innlogging: brukernavn + passord.
 *
 * Returnerer en av:
 *   ['ok', username]                    - innlogget med en gang (ingen 2FA)
 *   ['requires_2fa', pre_auth_token]    - trenger kode fra POST /auth/login/2fa
 *   ['error', feilmelding]
 */
function auth_login(string $username, string $password): array
{
    [$httpCode, $data] = auth_api_post('/auth/login', [
        'username' => $username,
        'password' => $password,
    ]);

    if ($httpCode !== 200 || $data === null) {
        return ['error', $data['detail'] ?? 'Kunne ikke logge inn (API-feil)'];
    }

    if (!empty($data['requires_2fa'])) {
        return ['requires_2fa', $data['pre_auth_token']];
    }

    auth_set_session($username, $data['access_token']);
    return ['ok', $username];
}

/**
 * Steg 2 av innlogging (kun hvis auth_login() ga 'requires_2fa'):
 * verifiserer TOTP- eller recovery-koden mot pre_auth_token.
 */
function auth_login_2fa(string $preAuthToken, string $code, string $username): array
{
    [$httpCode, $data] = auth_api_post('/auth/login/2fa', [
        'pre_auth_token' => $preAuthToken,
        'code' => $code,
    ]);

    if ($httpCode !== 200 || $data === null) {
        return ['error', $data['detail'] ?? 'Feil kode'];
    }

    auth_set_session($username, $data['access_token']);
    return ['ok', $username];
}

/** Lagrer innlogget bruker + access_token i PHP-sesjonen. */
function auth_set_session(string $username, string $accessToken): void
{
    auth_start_session();
    session_regenerate_id(true); // hindre session fixation ved innlogging
    $_SESSION['auth_username'] = $username;
    $_SESSION['auth_access_token'] = $accessToken;
}

/** Logger ut - tømmer hele sesjonen. */
function auth_logout(): void
{
    auth_start_session();
    $_SESSION = [];
    session_destroy();
}

/**
 * Krever innlogging for siden den kalles fra - redirecter til
 * login.php (med ?redirect=... tilbake til gjeldende side) hvis ingen
 * er innlogget, og stopper videre kjøring (exit).
 *
 * Brukes ikke av index.php selv i dag (der vises "Administrering"
 * bare som låst/ulåst i samme side - se $sectionAccess), men er klar
 * til bruk for fremtidige egne admin-sider/handlinger som bør kreve
 * et fullstendig sideskifte til innlogging.
 */
function require_login(): string
{
    if (is_logged_in()) {
        return current_username();
    }

    $redirectTo = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: /website_template_example/v18/login.php?redirect=' . urlencode($redirectTo));
    exit;
}
