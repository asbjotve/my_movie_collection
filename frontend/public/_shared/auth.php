<?php
declare(strict_types=1);

/**
 * auth.php - shared login library used across the project (formerly
 * lived under website_template_example/v18/, moved here so it isn't
 * tied to any single versioned app folder and can be included via
 * $_SERVER['DOCUMENT_ROOT'] regardless of caller location).
 *
 * Used by website_template_example/v18 (login.php/logout.php/index.php/
 * 2fa_setup.php/admin_tilganger.php) as well as several standalone
 * tools that share the same PHP session/login
 * (custom_list_manager/v3, add_to_wishlist/v4, bulk_add_movies_form/v14,
 * temp_add_movie_barcode/v1).
 *
 * ============================================================
 *  HOW THIS RELATES TO THE BACKEND API
 *  The actual login/2FA logic (password check, JWT, TOTP) lives in the
 *  FastAPI backend (see backend/app/routes/auth_route.py):
 *      POST /auth/login       - username+password
 *      POST /auth/login/2fa   - second step if 2FA is enabled
 *  This file calls those endpoints server-side (same pattern as
 *  api.php's proxy to the rest of the API) and stores ONLY the result
 *  (username + access_token) in a regular PHP session
 *  ($_SESSION - identified via a cookie, PHPSESSID, set automatically
 *  by PHP). The JWT token itself is never exposed to the browser/JS.
 * ============================================================
 */

// Load frontend/.env (same pattern as api.php) - only needed here to
// read AUTH_API_BASE_URL (via MEDIA_API_BASE_URL, see below).
require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Base URL to the internal FastAPI/uvicorn backend, loaded from .env
// (MEDIA_API_BASE_URL) so the value can be changed without editing code.
define('AUTH_API_BASE_URL', $_ENV['MEDIA_API_BASE_URL'] ?? '');
if (!AUTH_API_BASE_URL) {
    throw new Exception('MEDIA_API_BASE_URL is not set in .env file');
}

// URL path prefix of the website_template_example app (used to build
// absolute links/redirects to login.php, logout.php, index.php, etc.
// below). Loaded from .env (BASE_PATH) so the app can be moved/renamed
// to a different URL path without editing code in multiple files.
define('BASE_PATH', $_ENV['BASE_PATH'] ?? '');
if (!BASE_PATH) {
    throw new Exception('BASE_PATH is not set in .env file');
}


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

/** Henter rollen til innlogget bruker fra GET /auth/me. Returnerer
 * null hvis kallet feiler - da lagres ingen rolle i sesjonen, og
 * current_user_role() vil returnere null (ikke en gjettet verdi). */
function auth_fetch_role(string $accessToken): ?string
{
    $ch = curl_init(AUTH_API_BASE_URL . '/auth/me');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false || $httpCode !== 200) {
        return null;
    }

    $data = json_decode($response, true);
    return $data['role'] ?? null;
}

/** Lagrer innlogget bruker + access_token i PHP-sesjonen. */
function auth_set_session(string $username, string $accessToken): void
{
    auth_start_session();
    session_regenerate_id(true); // hindre session fixation ved innlogging
    $_SESSION['auth_username'] = $username;
    $_SESSION['auth_access_token'] = $accessToken;
    // Rolle er kun til fremtidig bruk (per i dag finnes bare "admin", og
    // ingenting i frontend skiller på den) - lagres likevel her slik at
    // current_user_role() er klar til bruk uten videre endringer.
    $_SESSION['auth_role'] = auth_fetch_role($accessToken);
}

/** Returnerer innlogget brukers rolle, eller null hvis ikke innlogget /
 * rollen ikke kunne hentes. Ikke brukt til noe ennå - se auth_role i
 * auth_set_session() for kontekst. */
function current_user_role(): ?string
{
    auth_start_session();
    return $_SESSION['auth_role'] ?? null;
}

/**
 * Henter innstillinger for hvilke seksjoner (i $sectionAccess-stil) som
 * krever innlogging, fra GET /settings/section-access. Endepunktet er
 * bevisst åpent (ingen API-nøkkel/JWT), så alle besøkende kan slå det
 * opp for å vise riktig hengelås-status.
 *
 * Returnerer $fallback (index.php sine tidligere hardkodede verdier)
 * hvis kallet feiler, slik at siden fortsatt fungerer selv om
 * backend/nettverk er nede.
 */
function fetch_section_access(array $fallback): array
{
    $ch = curl_init(AUTH_API_BASE_URL . '/settings/section-access');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false || $httpCode !== 200) {
        return $fallback;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return $fallback;
    }

    return array_merge($fallback, $data);
}

/**
 * Oppdaterer innstillinger for hvilke seksjoner som krever innlogging,
 * via PUT /settings/section-access. Krever et gyldig access_token for
 * en innlogget admin-bruker (require_role("admin") i backend).
 *
 * $sections er et assoc-array som ['onskeliste' => true, ...] - kun
 * nøklene som faktisk skal endres trenger å være med.
 *
 * Returnerer [httpCode, data].
 */
function update_section_access(array $sections): array
{
    $accessToken = $_SESSION['auth_access_token'] ?? null;
    if (!$accessToken) {
        return [401, ['error' => 'Ikke innlogget']];
    }

    $ch = curl_init(AUTH_API_BASE_URL . '/settings/section-access');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_POSTFIELDS => json_encode(['sections' => $sections]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        return [502, null];
    }

    return [$httpCode, json_decode($response, true)];
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
    header('Location: ' . BASE_PATH . '/login.php?redirect=' . urlencode($redirectTo));
    exit;
}

/**
 * Variant av require_login() for AJAX/API-endepunkter (f.eks.
 * bulk_add_movies_form/v14/api.php, temp_add_movie_barcode/v1/submit.php)
 * som kalles via fetch/XHR fra klient-JS - der gir en redirect (Location-
 * header) ingen mening, siden svaret aldri vises som en side i
 * nettleseren. Returnerer i stedet JSON 401 og stopper videre kjøring.
 */
function require_login_or_json_401(): string
{
    if (is_logged_in()) {
        return current_username();
    }

    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Ikke innlogget. Logg inn på ' . BASE_PATH . '/login.php først.',
    ]);
    exit;
}

/**
 * Returnerer en ferdig "Authorization: Bearer ..."-header-streng basert
 * på access_token i PHP-sesjonen. Brukes av frittstående verktøy som har
 * sin egen curl-oppsett (multipart/skjema-opplasting o.l.) og derfor ikke
 * kan bruke auth_api_authenticated() direkte, men som likevel må sende
 * med brukerens JWT til beskyttede skrive-endepunkter.
 */
function auth_bearer_header(): string
{
    auth_start_session();
    return 'Authorization: Bearer ' . ($_SESSION['auth_access_token'] ?? '');
}
