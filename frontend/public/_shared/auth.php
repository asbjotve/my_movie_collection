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
 * (custom_list_manager/v4, add_to_wishlist/v4, bulk_add_movies_form/v14,
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
 * Returns the URL path of the directory the currently running script
 * lives in (e.g. "/website_template_example/v19" for a request to
 * .../v19/login.php). Used to build same-directory links/redirects
 * (login.php, logout.php, index.php) without relying on the global
 * BASE_PATH constant above - BASE_PATH only ever points to a single
 * tool/version, so it silently breaks as soon as more than one
 * version of the same tool exists side by side (e.g. website_template_
 * example v18 + v19 at the same time). Deriving the path per-request
 * instead means every version resolves correctly to itself, and the
 * app can still be freely moved/renamed without touching any config.
 */
function current_script_dir(): string
{
    return rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
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
        'secure' => true,      // siten kjøres alltid over HTTPS i produksjon (openresty-proxy foran Apache)
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
 * ============================================================
 *  CSRF-BESKYTTELSE
 *  Ett token per PHP-sesjon (overlever hele innloggingsøkten, også
 *  session_regenerate_id() ved innlogging - se auth_set_session()).
 *  Brukes to steder:
 *   - Klassiske HTML-skjemaer (login.php, 2fa_setup.php,
 *     admin_tilganger.php) - token legges inn som skjult input via
 *     csrf_field(), verifiseres server-side via require_csrf_or_redirect().
 *   - JS fetch()-kall mot api.php (JSON) - token leses fra en <meta>-tag
 *     (se csrf_meta_tag()) av JS og sendes som header X-CSRF-Token,
 *     verifiseres server-side via require_csrf_or_json_403().
 *  Uten et gyldig token (eller med et token som ikke matcher sesjonen)
 *  avvises forespørselen FØR noen tilstandsendring skjer.
 * ============================================================
 */

/** Henter (eller oppretter) CSRF-token for denne sesjonen. */
function csrf_token(): string
{
    auth_start_session();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Returnerer et ferdig skjult <input>-felt for klassiske HTML-skjemaer. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/**
 * Returnerer en <meta>-tag med CSRF-token, til bruk i <head> på sider
 * som sender fetch()-kall til api.php - JS leser verdien derfra og
 * sender den som X-CSRF-Token-header (se require_csrf_or_json_403()).
 */
function csrf_meta_tag(): string
{
    return '<meta name="csrf-token" content="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/** Tidssikker sammenligning av et innsendt token mot sesjonens token. */
function csrf_verify(?string $token): bool
{
    auth_start_session();
    $expected = $_SESSION['csrf_token'] ?? null;
    if (!is_string($expected) || !is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($expected, $token);
}

/**
 * Krever et gyldig CSRF-token fra $_POST['csrf_token'] (klassiske
 * HTML-skjemaer). Avbryter med 403 og en enkel feilmelding hvis
 * tokenet mangler/er ugyldig - kalles FØR $_POST ellers leses/brukes.
 */
function require_csrf_or_403(string $errorMessage = 'Ugyldig eller utløpt skjema (CSRF) - last siden på nytt og prøv igjen.'): void
{
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo htmlspecialchars($errorMessage);
        exit;
    }
}

/**
 * Variant av require_csrf_or_403() for JSON/fetch-baserte endepunkter
 * (api.php) - leser token fra X-CSRF-Token-headeren (sendt av JS, se
 * csrf_meta_tag()) i stedet for $_POST, og svarer med JSON 403.
 */
function require_csrf_or_json_403(): void
{
    if (!csrf_verify($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Ugyldig eller utløpt sesjon (CSRF) - last siden på nytt og prøv igjen.']);
        exit;
    }
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
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            // Backend kalles server-til-server herfra, så uten dette
            // ville den alltid sett DENNE serverens IP, ikke
            // sluttbrukerens - se app/rate_limit.py sin get_client_ip()
            // i backend-repoet for hvordan den brukes (brute-force-
            // beskyttelse på /auth/login og /auth/login/2fa).
            'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
        ],
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

    auth_set_session($username, $data['access_token'], $data['refresh_token'] ?? null);
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

    auth_set_session($username, $data['access_token'], $data['refresh_token'] ?? null);
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

/** Lagrer innlogget bruker + access_token (+ ev. refresh_token) i
 * PHP-sesjonen. */
function auth_set_session(string $username, string $accessToken, ?string $refreshToken = null): void
{
    auth_start_session();
    session_regenerate_id(true); // hindre session fixation ved innlogging
    $_SESSION['auth_username'] = $username;
    $_SESSION['auth_access_token'] = $accessToken;
    if ($refreshToken !== null) {
        $_SESSION['auth_refresh_token'] = $refreshToken;
    }
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

/**
 * Henter admin-overstyrt default-språk fra GET /settings/default-language
 * (brukt av website_template_example/v19 sin wte_default_lang() - se
 * lang.php). Endepunktet er bevisst åpent (ingen API-nøkkel/JWT), siden
 * lang.php må slå opp verdien for ALLE besøkende på hver sidevisning.
 *
 * Returnerer null hvis ingen overstyring er lagret ennå, eller hvis
 * kallet feiler (kaller-siden faller da videre tilbake til .env / "no").
 */
function fetch_default_language_setting(): ?string
{
    $ch = curl_init(AUTH_API_BASE_URL . '/settings/default-language');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false || $httpCode !== 200) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['default_language']) || !is_string($data['default_language'])) {
        return null;
    }

    return $data['default_language'];
}

/**
 * Oppdaterer admin-overstyrt default-språk via PUT
 * /settings/default-language. Krever et gyldig access_token for en
 * innlogget admin-bruker (require_role("admin") i backend).
 *
 * Returnerer [httpCode, data].
 */
function update_default_language_setting(string $lang): array
{
    $accessToken = $_SESSION['auth_access_token'] ?? null;
    if (!$accessToken) {
        return [401, ['error' => 'Ikke innlogget']];
    }

    $ch = curl_init(AUTH_API_BASE_URL . '/settings/default-language');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_POSTFIELDS => json_encode(['default_language' => $lang]),
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
 * Henter admin-overstyrt default-valuta via GET
 * /settings/default-currency (offentlig endepunkt, ingen auth). Returnerer
 * null hvis ingen overstyring er lagret ennå (backend faller da tilbake
 * til NOK) - samme mønster som fetch_default_language_setting().
 */
function fetch_default_currency_setting(): ?string
{
    $ch = curl_init(AUTH_API_BASE_URL . '/settings/default-currency');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false || $httpCode !== 200) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['default_currency']) || !is_string($data['default_currency'])) {
        return null;
    }

    return $data['default_currency'];
}

/**
 * Oppdaterer admin-overstyrt default-valuta via PUT
 * /settings/default-currency. Krever et gyldig access_token for en
 * innlogget admin-bruker (require_role("admin") i backend).
 */
function update_default_currency_setting(string $currency): array
{
    $accessToken = $_SESSION['auth_access_token'] ?? null;
    if (!$accessToken) {
        return [401, ['error' => 'Ikke innlogget']];
    }

    $ch = curl_init(AUTH_API_BASE_URL . '/settings/default-currency');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_POSTFIELDS => json_encode(['default_currency' => $currency]),
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
 * Henter lagrede Plex-innstillinger via GET /settings/plex. Krever et
 * gyldig access_token for en innlogget admin-bruker (require_role
 * "admin" i backend) - i motsetning til
 * fetch_section_access()/fetch_default_language_setting() er dette
 * endepunktet bevisst IKKE åpent, siden selve eksistensen av en
 * lagret Plex-URL/token er mer sensitivt enn f.eks. default-språk.
 *
 * Returnerer null hvis kallet feiler (f.eks. ikke innlogget ennå) -
 * kaller-siden (plex_settings.php) krever uansett innlogging før den
 * i det hele tatt viser skjemaet.
 */
function fetch_plex_settings(): ?array
{
    $accessToken = $_SESSION['auth_access_token'] ?? null;
    if (!$accessToken) {
        return null;
    }

    $ch = curl_init(AUTH_API_BASE_URL . '/settings/plex');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_TIMEOUT => 5,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false || $httpCode !== 200) {
        return null;
    }

    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

/**
 * Lagrer Plex-URL/token via PUT /settings/plex. $token kan være null
 * (eller tom streng) for å la et allerede lagret token stå uendret -
 * se PlexSettingsUpdateRequest i backend (plex_settings.py).
 *
 * Returnerer [httpCode, data].
 */
function update_plex_settings(string $baseUrl, ?string $token, ?string $serverIdentifier = null, bool $verifySsl = true): array
{
    $accessToken = $_SESSION['auth_access_token'] ?? null;
    if (!$accessToken) {
        return [401, ['error' => 'Ikke innlogget']];
    }

    $body = ['base_url' => $baseUrl, 'server_identifier' => $serverIdentifier, 'verify_ssl' => $verifySsl];
    if ($token !== null && $token !== '') {
        $body['token'] = $token;
    }

    $ch = curl_init(AUTH_API_BASE_URL . '/settings/plex');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
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
 * Tester de FAKTISK LAGREDE Plex-credentials via POST
 * /settings/plex/test (pinger Plex sitt /identity-endepunkt
 * server-side - se test_plex_connection() i backend/plex_client.py).
 *
 * Returnerer [httpCode, data]. data['detail'] inneholder en norsk
 * feilmelding ved feil (502), data['machine_identifier']/['version']
 * ved suksess (200).
 */
function test_plex_connection_setting(): array
{
    $accessToken = $_SESSION['auth_access_token'] ?? null;
    if (!$accessToken) {
        return [401, ['error' => 'Ikke innlogget']];
    }

    $ch = curl_init(AUTH_API_BASE_URL . '/settings/plex/test');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        // Plex-serveren kan være treg å svare (lokalt nettverk, evt.
        // "sleeping"-tilstand) - lengre timeout enn de andre
        // settings-kallene, som bare snakker med vårt eget API.
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        return [502, ['detail' => 'Fikk ikke kontakt med API-et']];
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
 * Finner URL-en til login.php som skal brukes for gjeldende kall.
 *
 * De fleste tools (website_template_example v18/v19) har sin egen
 * login.php i samme mappe - da brukes den (current_script_dir()).
 * Men flere frittstående verktøy deler samme innloggings-sesjon
 * (session-cookien har path "/", se auth_start_session()) UTEN å ha
 * sin egen login.php - f.eks. custom_list_manager, bulk_add_movies_form,
 * temp_add_movie_barcode. For disse ville current_script_dir() . '/login.php'
 * pekt på en side som ikke finnes (404) - fall derfor tilbake til en delt
 * login-side, konfigurerbar via SHARED_LOGIN_PATH i .env slik at den kan
 * endres uten kodeendring hvis f.eks. v18 en dag fjernes/erstattes.
 */
function resolve_login_url(): string
{
    $ownLogin = $_SERVER['DOCUMENT_ROOT'] . current_script_dir() . '/login.php';
    if (is_file($ownLogin)) {
        return current_script_dir() . '/login.php';
    }

    return $_ENV['SHARED_LOGIN_PATH'] ?? '/website_template_example/v19/login.php';
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
    header('Location: ' . resolve_login_url() . '?redirect=' . urlencode($redirectTo));
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
        'error' => 'Ikke innlogget. Logg inn på ' . resolve_login_url() . ' først.',
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

/**
 * Prøver å fornye access_token stille, uten å be brukeren om passord
 * på nytt - via POST /auth/refresh med refresh_token fra sesjonen (satt
 * av auth_set_session() ved innlogging, se der).
 *
 * Returnerer true og oppdaterer $_SESSION['auth_access_token'] hvis det
 * lyktes, false ellers (f.eks. hvis det ikke finnes noe refresh_token i
 * sesjonen fordi brukeren logget inn før denne funksjonen fantes, eller
 * hvis selve refresh_token-et også er utløpt/ugyldig - da må brukeren
 * faktisk logge inn på nytt med passord).
 *
 * Brukes av auth_call_with_retry() under for å gi verktøy et
 * "automatisk re-login" ved 401 - se tv_series_add_form/v1/api.php.
 */
function auth_refresh_access_token(): bool
{
    auth_start_session();
    $refreshToken = $_SESSION['auth_refresh_token'] ?? null;
    if (!$refreshToken) {
        return false;
    }

    [$httpCode, $data] = auth_api_post('/auth/refresh', [
        'refresh_token' => $refreshToken,
    ]);

    if ($httpCode !== 200 || $data === null || empty($data['access_token'])) {
        return false;
    }

    $_SESSION['auth_access_token'] = $data['access_token'];
    return true;
}

/**
 * Kjører $makeRequest (en funksjon som tar access_token som
 * parameter og returnerer [httpCode, decoded_json_body_or_null], f.eks.
 * et curl-kall mot backend) og prøver automatisk å fornye access_token
 * og kjøre forespørselen på nytt ÉN gang hvis den først feiler med 401 -
 * slik at et utløpt access_token (30 min levetid, se backend/app/
 * security.py) ikke lenger krever at brukeren merker det og logger inn
 * på nytt manuelt.
 *
 * Hvis fornyingen også feiler (f.eks. refresh_token selv er utløpt, eller
 * brukeren logget inn før refresh_token fantes), returneres det
 * opprinnelige 401-svaret uendret - da er brukeren reelt sett logget ut
 * og må faktisk skrive inn passord på nytt.
 */
function auth_call_with_retry(callable $makeRequest): array
{
    auth_start_session();
    $accessToken = $_SESSION['auth_access_token'] ?? '';
    [$httpCode, $data] = $makeRequest($accessToken);

    if ($httpCode === 401 && auth_refresh_access_token()) {
        [$httpCode, $data] = $makeRequest($_SESSION['auth_access_token']);
    }

    return [$httpCode, $data];
}
