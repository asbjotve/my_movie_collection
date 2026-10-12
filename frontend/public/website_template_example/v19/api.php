<?php
declare(strict_types=1);

/**
 * api.php – tynn proxy mot FastAPI-backend (v18).
 *
 * ============================================================
 *  ARKITEKTUR-ENDRING FRA v15/v17
 *  v15/v17 kobler PHP direkte til MySQL (se deres config.php/api.php).
 *  Her i v18 gjør PHP ingen SQL selv - all databaselogikk bor nå i
 *  backend (FastAPI), i:
 *      backend/app/services/media_catalog.py
 *      backend/app/routes/media_catalog_route.py  (GET /media/content)
 *  Denne filen henter bare JSON fra det endepunktet server-side (så
 *  nettleseren slipper CORS, siden alt fortsatt serveres fra samme
 *  origin: mmc.plexcity.net) og sender det videre uendret til JS.
 *
 *  VIL DU ENDRE HVILKE FELTER/DATA SOM HENTES?
 *  Gjør det i backend (media_catalog.py), IKKE her.
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';

$sectionRequiresLogin = fetch_section_access(['mine_filmer' => false, 'andre_lister' => true]);
if ($sectionRequiresLogin['mine_filmer'] && !is_logged_in()) {
    require_login_or_json_401();
}

// Last inn frontend/.env (samme mønster som v15/config.php) - kun for
// å hente INTERNAL_API_KEY, som må matche backend sin INTERNAL_API_KEY
// (se backend/config/.env) for å nå lese-endepunktene under.
require_once __DIR__ . '/../../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();

// Base URL to the internal FastAPI/uvicorn backend, loaded from .env
// (MEDIA_API_BASE_URL) so the value can be changed without editing code
define('MEDIA_API_BASE_URL', $_ENV['MEDIA_API_BASE_URL'] ?? '');
if (!MEDIA_API_BASE_URL) {
    throw new Exception('MEDIA_API_BASE_URL is not set in .env file');
}

define('INTERNAL_API_KEY', $_ENV['INTERNAL_API_KEY'] ?? '');
if (!INTERNAL_API_KEY) {
    throw new Exception('INTERNAL_API_KEY er ikke satt i .env-filen');
}

/**
 * Legger til X-API-Key-headeren som backend krever på lese-
 * endepunkter (se app/api_key.py). Brukes for alle GET-kall mot
 * /media/*, /lists, /wishlist/movies.
 */
function with_api_key_header(array $curlHttpHeaders): array
{
    $curlHttpHeaders[] = 'X-API-Key: ' . INTERNAL_API_KEY;
    return $curlHttpHeaders;
}

/**
 * Utfører et curl-kall mot backend med brukerens access_token i
 * Authorization-headeren, og prøver automatisk å fornye tokenet stille
 * (uten å be om passord på nytt) og kjøre kallet på nytt ÉN gang hvis
 * det første forsøket feiler med 401 - se auth_call_with_retry()/
 * auth_refresh_access_token() i _shared/auth.php og TODO.md "Roll out
 * the automatic re-login". Fjerner behovet for at brukeren merker at
 * access_token (30 min levetid) har utløpt og må logge inn på nytt
 * manuelt midt i en handling.
 *
 * $curlOptions skal IKKE sette CURLOPT_RETURNTRANSFER eller
 * CURLOPT_HTTPHEADER selv - de settes her. Ev. ekstra headere (f.eks.
 * 'Content-Type: application/json') sendes via $extraHeaders.
 *
 * @return array{0: string|false, 1: int, 2: string} [response, httpCode, curlError]
 */
function backend_curl_with_retry(string $url, array $curlOptions, array $extraHeaders = []): array
{
    $curlError = '';
    [$httpCode, $response] = auth_call_with_retry(
        function (string $accessToken) use ($url, $curlOptions, $extraHeaders, &$curlError): array {
            $ch = curl_init($url);
            curl_setopt_array($ch, $curlOptions + [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array_merge(['Authorization: Bearer ' . $accessToken], $extraHeaders),
            ]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            return [$code, $raw === false ? null : $raw];
        }
    );

    return [$response === null ? false : $response, $httpCode, $curlError];
}

// POST ?action=refresh_external_source&source=tmdb|tvdb&external_id=...
// Brukes av "Bytt data fra kilde"-knappene på detail.php. Gjør en
// server-side PATCH mot backend, som selv henter fulle detaljer fra
// TMDB/TVDB og lagrer dem i content_external_source.data_json - se
// PATCH /media/external-source/{source}/{external_id} i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'refresh_external_source') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    // Krever et gyldig CSRF-token (header X-CSRF-Token, se csrfHeader()
    // i index.php/detail.php) FØR noen tilstandsendring skjer.
    require_csrf_or_json_403();

    $source = (string)($_GET['source'] ?? '');
    $externalId = (string)($_GET['external_id'] ?? '');

    if (!in_array($source, ['tmdb', 'tvdb'], true) || $externalId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Mangler eller ugyldig source/external_id-parameter']);
        exit;
    }

    $patchUrl = MEDIA_API_BASE_URL . '/media/external-source/' . rawurlencode($source) . '/' . rawurlencode($externalId);

    [$response, $httpCode, $curlError] = backend_curl_with_retry($patchUrl, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_TIMEOUT => 20, // henting fra TMDB/TVDB kan ta noen sekunder
    ]);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=merge_external_source&source=tmdb|tvdb&external_id=...
// Brukes rett etter refresh_external_source over: fletter sist lagrede
// data_json for kilden inn i content-tabellen (title/overview/runtime
// osv., med mindre feltet er låst via content.locked_fields) - se
// POST /media/external-source/{source}/{external_id}/merge i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'merge_external_source') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    // Krever et gyldig CSRF-token (header X-CSRF-Token, se csrfHeader()
    // i index.php/detail.php) FØR noen tilstandsendring skjer.
    require_csrf_or_json_403();

    $source = (string)($_GET['source'] ?? '');
    $externalId = (string)($_GET['external_id'] ?? '');

    if (!in_array($source, ['tmdb', 'tvdb'], true) || $externalId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Mangler eller ugyldig source/external_id-parameter']);
        exit;
    }

    $mergeUrl = MEDIA_API_BASE_URL . '/media/external-source/' . rawurlencode($source) . '/' . rawurlencode($externalId) . '/merge';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($mergeUrl, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_TIMEOUT => 10,
    ]);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=set_external_id&content_id=...&source=tmdb|tvdb&external_id=...[&confirm_merge=1][&content_type=movie|tv]
// Brukes for å SETTE en TMDB/TVDB-id på en film som ikke har en kobling
// fra før (i motsetning til refresh_external_source over, som krever at
// koblingen finnes). Hvis IDen allerede tilhører en annen content-rad,
// svarer backend med 409 og konflikt-info - frontend spør da brukeren
// om å slå sammen, og kaller denne på nytt med confirm_merge=1 - se
// POST /media/content/{content_id}/external-source/{source} i
// backend/app/routes/media_catalog_route.py.
// content_type er valgfri og er typen brukeren faktisk søkte med i
// modalen (movie/tv) - brukes av backend til å slå opp riktig
// TMDB/TVDB-endepunkt FREMFOR content-radens egen (muligens feilimporterte,
// f.eks. fra Plex) content_type, og til å rette opp den lagrede typen hvis
// de avviker.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'set_external_id') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    require_csrf_or_json_403();

    $contentId = (string)($_GET['content_id'] ?? '');
    $source = (string)($_GET['source'] ?? '');
    $externalId = (string)($_GET['external_id'] ?? '');
    $confirmMerge = ($_GET['confirm_merge'] ?? '') === '1';
    $contentType = (string)($_GET['content_type'] ?? '');
    if (!in_array($contentType, ['movie', 'tv'], true)) {
        $contentType = null;
    }

    if ($contentId === '' || !in_array($source, ['tmdb', 'tvdb'], true) || $externalId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Mangler eller ugyldig content_id/source/external_id-parameter']);
        exit;
    }

    $setUrl = MEDIA_API_BASE_URL . '/media/content/' . rawurlencode($contentId) . '/external-source/' . rawurlencode($source);

    [$response, $httpCode, $curlError] = backend_curl_with_retry($setUrl, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode([
            'external_id' => $externalId,
            'confirm_merge' => $confirmMerge,
            'content_type' => $contentType,
        ]),
        CURLOPT_TIMEOUT => 20, // henting fra TMDB/TVDB for validering kan ta noen sekunder
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// GET ?action=search_external_source&source=tmdb|tvdb&query=...&content_type=movie|tv
// Brukes av søkemodalen for å SETTE en manglende TMDB/TVDB-id: søker
// TMDB/TVDB server-side og returnerer en kort, normalisert treffliste
// (ingenting lagres) - se GET /media/external-search/{source} i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'search_external_source') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    $source = (string)($_GET['source'] ?? '');
    $query = (string)($_GET['query'] ?? '');
    $contentType = (string)($_GET['content_type'] ?? 'movie');

    if (!in_array($source, ['tmdb', 'tvdb'], true) || trim($query) === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Mangler eller ugyldig source/query-parameter']);
        exit;
    }

    $searchUrl = MEDIA_API_BASE_URL . '/media/external-search/' . rawurlencode($source) . '?' . http_build_query([
        'query' => $query,
        'content_type' => $contentType,
    ]);

    [$response, $httpCode, $curlError] = backend_curl_with_retry($searchUrl, [
        CURLOPT_TIMEOUT => 15,
    ]);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// GET ?action=list_custom_lists
// Brukes av "Andre lister"-panelet for å vise de faktiske
// egendefinerte listene (navn + antall elementer) i stedet for
// plassholder-data - se GET /lists i
// backend/app/routes/add_data/custom_list_manager_route.py.
// Krever innlogging hvis "andre_lister" er satt til å kreve det (se
// $sectionRequiresLogin over) - panelet er selv skjult for ikke-
// innloggede, men endepunktet skal ikke lekke listenavn/antall likevel.
if (($_GET['action'] ?? '') === 'list_custom_lists') {
    if ($sectionRequiresLogin['andre_lister'] && !is_logged_in()) {
        require_login_or_json_401();
    }

    $listsUrl = MEDIA_API_BASE_URL . '/lists';

    $ch = curl_init($listsUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => with_api_key_header([]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// GET ?action=list_custom_list_items&list_id=<uuid>
// Brukes av "Andre lister"-panelet for å vise innholdet i én valgt
// liste (tittel, år, cover, gruppe) - se
// GET /lists/{list_id}/items i
// backend/app/routes/add_data/custom_list_manager_route.py.
if (($_GET['action'] ?? '') === 'list_custom_list_items') {
    if ($sectionRequiresLogin['andre_lister'] && !is_logged_in()) {
        require_login_or_json_401();
    }

    $listId = (string)($_GET['list_id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F-]{32,36}$/', $listId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig list_id-parameter']);
        exit;
    }

    $itemsUrl = MEDIA_API_BASE_URL . '/lists/' . rawurlencode($listId) . '/items';

    $ch = curl_init($itemsUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => with_api_key_header([]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// Brukes til autofullføring i redigerings-popupen for "group"-feltet på
// detail.php: lister alle eksisterende filmgrupper (movie_group), slik
// at brukeren kan velge en eksisterende gruppe fremfor å skrive inn
// navnet på nytt - se GET /media/groups i
// backend/app/routes/media_catalog_route.py.
if (($_GET['action'] ?? '') === 'list_groups') {
    $groupsUrl = MEDIA_API_BASE_URL . '/media/groups';

    $ch = curl_init($groupsUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => with_api_key_header([]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// GET ?action=list_owners
// Brukes til autofullføring i redigerings-popupen for "owner"-feltet på
// "Samlingsopplysninger" i detail.php: lister alle eksisterende eiere
// (owner), slik at brukeren kan velge en eksisterende eier fremfor å
// skrive inn navnet på nytt - se GET /media/owners i
// backend/app/routes/media_catalog_route.py.
if (($_GET['action'] ?? '') === 'list_owners') {
    $ownersUrl = MEDIA_API_BASE_URL . '/media/owners';

    $ch = curl_init($ownersUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => with_api_key_header([]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// GET ?action=list_pending_duplicates
// Brukes av duplicate_review.php: lister rader som
// bulk_add_movies_form/v14 la i venteliste fordi strekkoden allerede
// var registrert - se GET /import/physical-collection/pending i
// backend/app/routes/add_data/physical_collection_import_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list_pending_duplicates') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    $pendingUrl = MEDIA_API_BASE_URL . '/import/physical-collection/pending';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($pendingUrl, [
        CURLOPT_TIMEOUT => 10,
    ]);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=resolve_pending_duplicate&id=<int>
// (body: {"action": "add_copy"|"discard"})
// Brukes av duplicate_review.php: legger til en ny kopi av den
// allerede-registrerte utgivelsen, eller forkaster ventelisteraden -
// se POST /import/physical-collection/pending/{id}/resolve i
// backend/app/routes/add_data/physical_collection_import_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'resolve_pending_duplicate') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    require_csrf_or_json_403();

    $pendingId = (string)($_GET['id'] ?? '');
    if (!preg_match('/^\d+$/', $pendingId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $resolveUrl = MEDIA_API_BASE_URL . '/import/physical-collection/pending/' . $pendingId . '/resolve';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($resolveUrl, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 10,
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// GET ?action=similar&id=<content_id>
// Brukes av detail.php: "Lignende filmer"-forslag basert på delt
// sjanger (filmer i samme filmgruppe er allerede dekket av den
// separate "Andre filmer i denne filmgruppen"-seksjonen, og
// ekskluderes derfor her) - se GET /media/content/{id}/similar og
// get_similar_content() i backend/app/services/media_catalog.py.
if (($_GET['action'] ?? '') === 'similar') {
    $contentId = $_GET['id'] ?? '';
    if ($contentId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Mangler id']);
        exit;
    }

    $similarUrl = MEDIA_API_BASE_URL . '/media/content/' . rawurlencode($contentId) . '/similar';

    $ch = curl_init($similarUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => with_api_key_header([]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// GET ?action=list_stats
// Brukes av stats.php: aggregerte statistikk-tall for samlingen
// (totalt antall, per tiår/sjanger/format, mest-fylte filmgrupper) -
// se GET /media/stats i backend/app/routes/media_catalog_route.py.
if (($_GET['action'] ?? '') === 'list_stats') {
    $statsUrl = MEDIA_API_BASE_URL . '/media/stats';

    $ch = curl_init($statsUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => with_api_key_header([]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// GET ?action=list_health_check
// Brukes av health_check.php: content-rader som mangler cover,
// overview, runtime, IMDb-ID eller TMDB/TVDB-kilde - se
// GET /media/health-check i backend/app/routes/media_catalog_route.py.
if (($_GET['action'] ?? '') === 'list_health_check') {
    $healthUrl = MEDIA_API_BASE_URL . '/media/health-check';

    $ch = curl_init($healthUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => with_api_key_header([]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=bulk_refresh_tmdb
// Brukes av "Oppdater flaggede fra TMDB"-knappen på health_check.php:
// STARTER "hent fra TMDB" + "flett inn i content" for alle flaggede
// content-rader med en TMDB-kobling, i en bakgrunnstråd på backend -
// se POST /media/health-check/bulk-refresh-tmdb i
// backend/app/routes/media_catalog_route.py. Returnerer med en gang
// (selve jobben kan ta 20-30+ minutter - se
// bulk_refresh_tmdb_for_flagged_content() for hvorfor); frontend
// poller bulk_refresh_tmdb_status under for fremdrift. Krever
// innlogging (samme som refresh_external_source/merge_external_source
// over) siden dette er en skrivende bulk-operasjon.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'bulk_refresh_tmdb') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    // Krever et gyldig CSRF-token (header X-CSRF-Token, se csrfHeader()
    // i index.php/detail.php) FØR noen tilstandsendring skjer.
    require_csrf_or_json_403();

    $bulkUrl = MEDIA_API_BASE_URL . '/media/health-check/bulk-refresh-tmdb';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($bulkUrl, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_TIMEOUT => 15,
    ]);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// GET ?action=bulk_refresh_tmdb_status
// Brukes til å polle fremdriften på bulk-TMDB-refresh-jobben som
// bulk_refresh_tmdb over startet - se
// GET /media/health-check/bulk-refresh-tmdb/status i
// backend/app/routes/media_catalog_route.py.
if (($_GET['action'] ?? '') === 'bulk_refresh_tmdb_status') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    $statusUrl = MEDIA_API_BASE_URL . '/media/health-check/bulk-refresh-tmdb/status';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($statusUrl, [
        CURLOPT_TIMEOUT => 10,
    ]);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// Brukes av "Bytt cover"-modalen på detail.php: lister alle TMDB-
// postere som er tilgjengelige (fra sist lagrede data_json - ingen nye
// TMDB-kall gjøres) - se GET /media/content/{id}/covers i
// backend/app/routes/media_catalog_route.py.
if (($_GET['action'] ?? '') === 'list_covers') {
    $contentId = (string)($_GET['id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $contentId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig id-parameter']);
        exit;
    }

    $coversUrl = MEDIA_API_BASE_URL . '/media/content/' . $contentId . '/covers';

    $ch = curl_init($coversUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => with_api_key_header([]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=set_cover&id=<hex content_id>  (body: {"file_path": "..."})
// Setter content.cover_image til et valgt posterbilde og låser feltet
// (content.locked_fields) slik at senere merge/backfill fra TMDB ikke
// overskriver valget - se POST /media/content/{id}/cover i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'set_cover') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    // Krever et gyldig CSRF-token (header X-CSRF-Token, se csrfHeader()
    // i index.php/detail.php) FØR noen tilstandsendring skjer.
    require_csrf_or_json_403();

    $contentId = (string)($_GET['id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $contentId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');
    $decoded = json_decode($body, true);
    $filePath = (string)($decoded['file_path'] ?? '');
    if ($filePath === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Mangler file_path i request-body']);
        exit;
    }

    $setCoverUrl = MEDIA_API_BASE_URL . '/media/content/' . $contentId . '/cover';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($setCoverUrl, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode(['file_path' => $filePath]),
        CURLOPT_TIMEOUT => 10,
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=bulk_add_to_group
// (body: {"content_ids": ["<hex id>", ...], "group": "Navn"})
// Brukes av "velg-modus" i Mine filmer (index.php) - tildeler flere
// valgte filmer samme filmgruppe i ett kall. Kroppen sendes bare videre
// uendret - validering (tomme lister/navn, ukjente id-er) skjer i
// BulkGroupAssignRequest/bulk_assign_group() på backend-siden. Se
// POST /media/content/bulk-group i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'bulk_add_to_group') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    // Krever et gyldig CSRF-token (header X-CSRF-Token, se csrfHeader()
    // i index.php/detail.php) FØR noen tilstandsendring skjer.
    require_csrf_or_json_403();

    $body = file_get_contents('php://input');

    $bulkGroupUrl = MEDIA_API_BASE_URL . '/media/content/bulk-group';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($bulkGroupUrl, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 10,
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// PATCH ?action=reorder_group&group_id=<int>
// (body: {"content_ids": ["<hex id>", ...]} i ønsket rekkefølge)
// Brukes av dra-og-slipp-sortering av filmgruppe-listen på
// detaljsiden - se PATCH /media/groups/{group_id}/reorder i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'PATCH' && ($_GET['action'] ?? '') === 'reorder_group') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    // Krever et gyldig CSRF-token (header X-CSRF-Token, se csrfHeader()
    // i index.php/detail.php) FØR noen tilstandsendring skjer.
    require_csrf_or_json_403();

    $groupId = (int)($_GET['group_id'] ?? 0);
    if ($groupId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig group_id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $reorderUrl = MEDIA_API_BASE_URL . '/media/groups/' . $groupId . '/reorder';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($reorderUrl, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 10,
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=add_content_to_group&id=<hex content_id>
// (body: {"group": "<navn>"})
// Legger filmen til i én filmgruppe til (en film kan tilhøre flere
// grupper samtidig) - se POST /media/content/{id}/groups i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'add_content_to_group') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    // Krever et gyldig CSRF-token (header X-CSRF-Token, se csrfHeader()
    // i index.php/detail.php) FØR noen tilstandsendring skjer.
    require_csrf_or_json_403();

    $contentId = $_GET['id'] ?? '';
    if ($contentId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Mangler id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $addGroupUrl = MEDIA_API_BASE_URL . '/media/content/' . rawurlencode($contentId) . '/groups';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($addGroupUrl, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 10,
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=remove_content_from_group&id=<hex content_id>&group_id=<int>
// Fjerner filmen fra én filmgruppe (beholder andre gruppetilhørigheter
// uendret) - se DELETE /media/content/{id}/groups/{group_id} i
// backend/app/routes/media_catalog_route.py. Bruker POST i stedet for
// DELETE her (samme begrunnelse som andre steder i denne filen der
// enkelte hostingmiljøer strupper DELETE-forespørsler via PHP) - selve
// backend-kallet er fortsatt en ekte HTTP DELETE.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'remove_content_from_group') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    // Krever et gyldig CSRF-token (header X-CSRF-Token, se csrfHeader()
    // i index.php/detail.php) FØR noen tilstandsendring skjer.
    require_csrf_or_json_403();

    $contentId = $_GET['id'] ?? '';
    $groupId = (int)($_GET['group_id'] ?? 0);
    if ($contentId === '' || $groupId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Mangler id- eller group_id-parameter']);
        exit;
    }

    $removeGroupUrl = MEDIA_API_BASE_URL . '/media/content/' . rawurlencode($contentId) . '/groups/' . $groupId;

    [$response, $httpCode, $curlError] = backend_curl_with_retry($removeGroupUrl, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_TIMEOUT => 10,
    ]);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// PATCH ?action=update_content_field&id=<hex content_id>
// (body: kun feltet(ene) som redigeres, f.eks. {"runtime": 92})
// Brukes av penne-ikon-redigering på detaljsiden for felter som ligger
// direkte på content-tabellen (title/overview/runtime/osv.) - se
// PATCH /media/content/{id} i backend/app/routes/media_catalog_route.py.
// Kun feltnavn som finnes i ContentFieldUpdateRequest slipper gjennom
// på backend-siden - denne proxyen sender bare kroppen videre uendret.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'update_content_field') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    // Krever et gyldig CSRF-token (header X-CSRF-Token, se csrfHeader()
    // i index.php/detail.php) FØR noen tilstandsendring skjer.
    require_csrf_or_json_403();

    $contentId = (string)($_GET['id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $contentId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $updateUrl = MEDIA_API_BASE_URL . '/media/content/' . $contentId;

    [$response, $httpCode, $curlError] = backend_curl_with_retry($updateUrl, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 10,
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// PATCH ?action=set_content_field_lock&id=<hex content_id>
// (body: {"field": "runtime", "locked": true})
// Brukes av hengelås-ikon-redigering på detaljsiden for å låse/åpne
// ett content-felt mot TMDB/TVDB-fletting, uten å endre selve verdien
// - se PATCH /media/content/{id}/lock i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'set_content_field_lock') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    // Krever et gyldig CSRF-token (header X-CSRF-Token, se csrfHeader()
    // i index.php/detail.php) FØR noen tilstandsendring skjer.
    require_csrf_or_json_403();

    $contentId = (string)($_GET['id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $contentId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $lockUrl = MEDIA_API_BASE_URL . '/media/content/' . $contentId . '/lock';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($lockUrl, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 10,
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// PATCH ?action=update_physical_copy_field&collection_id=<hex>&copy_id=<int>
// (body: kun feltet(ene) som redigeres, f.eks. {"price": 179})
// Brukes av penne-ikon-redigering for eier-/kjøpsinformasjon på ett
// fysisk eksemplar (owner/store/purchased_at/price/currency) - se
// PATCH /media/physical-copy/{collection_id}/{copy_id} i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'update_physical_copy_field') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    // Krever et gyldig CSRF-token (header X-CSRF-Token, se csrfHeader()
    // i index.php/detail.php) FØR noen tilstandsendring skjer.
    require_csrf_or_json_403();

    $collectionId = (string)($_GET['collection_id'] ?? '');
    $copyId = (string)($_GET['copy_id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $collectionId) || !preg_match('/^\d+$/', $copyId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig collection_id/copy_id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $updateUrl = MEDIA_API_BASE_URL . '/media/physical-copy/' . $collectionId . '/' . $copyId;

    [$response, $httpCode, $curlError] = backend_curl_with_retry($updateUrl, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 10,
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// GET ?action=list_seasons&id=<hex content_id>
// Brukes av "Sesonger"-fanen på detaljsiden: henter alle sesonger (med
// nøstede episoder, inkl. tilkoblet disk) for én serie - se
// GET /media/content/{id}/seasons i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list_seasons') {
    $contentId = (string)($_GET['id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $contentId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig id-parameter']);
        exit;
    }

    $seasonsUrl = MEDIA_API_BASE_URL . '/media/content/' . $contentId . '/seasons';

    $ch = curl_init($seasonsUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => with_api_key_header([]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// GET ?action=list_discs&id=<hex content_id>
// Brukes til disk-velgeren i episode-redigering (hvilken fysisk disk
// en episode ligger på) - se GET /media/content/{id}/discs i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list_discs') {
    $contentId = (string)($_GET['id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $contentId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig id-parameter']);
        exit;
    }

    $discsUrl = MEDIA_API_BASE_URL . '/media/content/' . $contentId . '/discs';

    $ch = curl_init($discsUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => with_api_key_header([]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=create_season&id=<hex content_id>
// (body: {"season_number": 1, "title": "...", "air_date": "...", "overview": "..."})
// Oppretter en ny sesong manuelt - se POST /media/content/{id}/seasons
// i backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'create_season') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    require_csrf_or_json_403();

    $contentId = (string)($_GET['id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $contentId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $createUrl = MEDIA_API_BASE_URL . '/media/content/' . $contentId . '/seasons';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($createUrl, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 10,
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=update_season&season_id=<hex>
// (body: kun feltet(ene) som redigeres, f.eks. {"title": "..."})
// Bruker POST i stedet for PATCH her (samme begrunnelse som andre
// steder i denne filen) - selve backend-kallet er fortsatt en ekte
// HTTP PATCH. Se PATCH /media/seasons/{season_id} i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'update_season') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    require_csrf_or_json_403();

    $seasonId = (string)($_GET['season_id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $seasonId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig season_id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $updateUrl = MEDIA_API_BASE_URL . '/media/seasons/' . $seasonId;

    [$response, $httpCode, $curlError] = backend_curl_with_retry($updateUrl, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 10,
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=update_season_watched&season_id=<hex>
// (body: {"watched_flag": true|false}) - merker samtlige episoder i
// sesongen som sett/usett under ett. Bruker POST i stedet for PATCH
// her (samme begrunnelse som andre steder i denne filen) - selve
// backend-kallet er fortsatt en ekte HTTP PATCH. Se
// PATCH /media/seasons/{season_id}/watched i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'update_season_watched') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    require_csrf_or_json_403();

    $seasonId = (string)($_GET['season_id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $seasonId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig season_id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $updateUrl = MEDIA_API_BASE_URL . '/media/seasons/' . $seasonId . '/watched';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($updateUrl, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 10,
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=delete_season&season_id=<hex>
// Sletter en sesong og alle dens episoder (ON DELETE CASCADE). Bruker
// POST i stedet for DELETE (samme begrunnelse som andre steder i
// denne filen) - selve backend-kallet er fortsatt en ekte HTTP DELETE.
// Se DELETE /media/seasons/{season_id} i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'delete_season') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    require_csrf_or_json_403();

    $seasonId = (string)($_GET['season_id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $seasonId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig season_id-parameter']);
        exit;
    }

    $deleteUrl = MEDIA_API_BASE_URL . '/media/seasons/' . $seasonId;

    [$response, $httpCode, $curlError] = backend_curl_with_retry($deleteUrl, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_TIMEOUT => 10,
    ]);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=refresh_season&season_id=<hex>&source=tmdb|tvdb&season_type=default|dvd|...
// Henter hele sesongens episodeliste fra TMDB/TVDB og oppdaterer/
// oppretter episoder deretter (kan ta noen sekunder). season_type
// gjelder kun source=tvdb (f.eks. 'dvd' for DVD-rekkefølge). Se
// POST /media/seasons/{season_id}/refresh/{source} i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'refresh_season') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    require_csrf_or_json_403();

    $seasonId = (string)($_GET['season_id'] ?? '');
    $source = (string)($_GET['source'] ?? '');
    $seasonType = (string)($_GET['season_type'] ?? 'default');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $seasonId) || !in_array($source, ['tmdb', 'tvdb'], true)
        || !in_array($seasonType, ['default', 'dvd', 'official', 'absolute', 'alternate', 'regional'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig season_id/source/season_type-parameter']);
        exit;
    }

    $refreshUrl = MEDIA_API_BASE_URL . '/media/seasons/' . $seasonId . '/refresh/' . $source
        . '?season_type=' . urlencode($seasonType);

    [$response, $httpCode, $curlError] = backend_curl_with_retry($refreshUrl, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_TIMEOUT => 20, // henting av hele episodelisten fra TMDB/TVDB kan ta noen sekunder
    ]);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=create_episode&season_id=<hex>
// (body: {"episode_number": 1, "title": "...", ...})
// Legger til én episode manuelt i en eksisterende sesong - se
// POST /media/seasons/{season_id}/episodes i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'create_episode') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    require_csrf_or_json_403();

    $seasonId = (string)($_GET['season_id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $seasonId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig season_id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $createUrl = MEDIA_API_BASE_URL . '/media/seasons/' . $seasonId . '/episodes';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($createUrl, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 10,
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=update_episode&episode_id=<hex>
// (body: kun feltet(ene) som redigeres, f.eks. {"watched_flag": true})
// Bruker POST i stedet for PATCH her (samme begrunnelse som andre
// steder i denne filen). Se PATCH /media/episodes/{episode_id} i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'update_episode') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    require_csrf_or_json_403();

    $episodeId = (string)($_GET['episode_id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $episodeId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig episode_id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $updateUrl = MEDIA_API_BASE_URL . '/media/episodes/' . $episodeId;

    [$response, $httpCode, $curlError] = backend_curl_with_retry($updateUrl, [
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 10,
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=delete_episode&episode_id=<hex>
// Sletter en episode. Bruker POST i stedet for DELETE (samme
// begrunnelse som andre steder i denne filen). Se
// DELETE /media/episodes/{episode_id} i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'delete_episode') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    require_csrf_or_json_403();

    $episodeId = (string)($_GET['episode_id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $episodeId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig episode_id-parameter']);
        exit;
    }

    $deleteUrl = MEDIA_API_BASE_URL . '/media/episodes/' . $episodeId;

    [$response, $httpCode, $curlError] = backend_curl_with_retry($deleteUrl, [
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_TIMEOUT => 10,
    ]);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=assign_episode_disc&episode_id=<hex>
// (body: {"disc_ids": ["<hex>", ...]} - erstatter ALLE eksisterende
// disk-koblinger for episoden med denne listen, tom liste fjerner
// alle. En episode kan ligge på flere disker samtidig, f.eks. en
// episode som er delt i to deler på to ulike disker i en boks.
// Bruker POST i stedet for PUT her (samme begrunnelse som andre steder
// i denne filen). Se PUT /media/episodes/{episode_id}/disc i
// backend/app/routes/media_catalog_route.py.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'assign_episode_disc') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    require_csrf_or_json_403();

    $episodeId = (string)($_GET['episode_id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $episodeId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig episode_id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $assignUrl = MEDIA_API_BASE_URL . '/media/episodes/' . $episodeId . '/disc';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($assignUrl, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 10,
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// GET ?action=reset_data_preview
// Brukes av "Nullstill alle data"-dialogen i admin_tilganger.php: viser
// hvor mange rader som VILLE blitt slettet per tabell, uten å slette
// noe, slik at brukeren ser nøyaktig konsekvensen FØR de bekrefter -
// se GET /admin/reset-collection-data/preview i
// backend/app/routes/admin_reset_route.py.
if (($_GET['action'] ?? '') === 'reset_data_preview') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    $previewUrl = MEDIA_API_BASE_URL . '/admin/reset-collection-data/preview';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($previewUrl, [
        CURLOPT_TIMEOUT => 15,
    ]);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// POST ?action=reset_data_confirm  (body: {"confirm": "NULLSTILL ALT"})
// Selve slettingen - se POST /admin/reset-collection-data i
// backend/app/routes/admin_reset_route.py. Krever at body.confirm
// matcher bekreftelsesfrasen EKSAKT (ekstra sperre mot feilklikk/
// automatiserte kall, i tillegg til at frontend-dialogen selv krever
// at brukeren skriver frasen inn på nytt rett før dette sendes).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'reset_data_confirm') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    require_csrf_or_json_403();

    $body = file_get_contents('php://input');

    $resetUrl = MEDIA_API_BASE_URL . '/admin/reset-collection-data';

    [$response, $httpCode, $curlError] = backend_curl_with_retry($resetUrl, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_TIMEOUT => 30, // mange DELETE-setninger - gi det litt ekstra tid
    ], ['Content-Type: application/json']);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// GET ?action=backup_download
// Proxyer rett gjennom til GET /admin/backup/download i
// backend/app/routes/admin_backup_route.py - en ren mysqldump av
// db_mediearkiv (ekskl. coverbilder, siden cover_image bare er en
// URL-streng, ikke binærdata). Dette er IKKE et JSON-endepunkt, så vi
// må overstyre Content-Type-headeren satt helt øverst i filen og
// strømme filen rått i stedet for å echo'e JSON.
if (($_GET['action'] ?? '') === 'backup_download') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    $downloadUrl = MEDIA_API_BASE_URL . '/admin/backup/download';

    $curlError = '';
    [$httpCode, $raw] = auth_call_with_retry(
        function (string $accessToken) use ($downloadUrl, &$curlError): array {
            $ch = curl_init($downloadUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
                CURLOPT_TIMEOUT => 60,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            return [$code, $body === false ? null : $body];
        }
    );

    if ($raw === null) {
        http_response_code(502);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    if ($httpCode !== 200) {
        http_response_code($httpCode ?: 502);
        header('Content-Type: application/json; charset=utf-8');
        echo $raw;
        exit;
    }

    header_remove('Content-Type');
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . date('Y-m-d_His') . '_db_mediearkiv_backup.sql"');
    header('Content-Length: ' . strlen($raw));
    echo $raw;
    exit;
}

// POST ?action=backup_restore  (multipart/form-data: confirm, file)
// Proxyer videre til POST /admin/backup/restore i
// backend/app/routes/admin_backup_route.py. Siden PHP mottar filen som
// multipart/form-data (ikke JSON, som resten av api.php), bygges et
// nytt multipart-kall mot backend med CURLFile - vi kan ikke gjenbruke
// backend_curl_with_retry() rått siden den antar en enkel
// JSON-body/CURLOPT_POSTFIELDS-streng.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'backup_restore') {
    if (!is_logged_in()) {
        require_login_or_json_401();
    }

    require_csrf_or_json_403();

    $confirm = (string)($_POST['confirm'] ?? '');

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Mangler eller ugyldig fil']);
        exit;
    }

    $restoreUrl = MEDIA_API_BASE_URL . '/admin/backup/restore';
    $uploadedTmpPath = $_FILES['file']['tmp_name'];
    $uploadedName = $_FILES['file']['name'];

    [$response, $httpCode, $curlError] = backend_curl_with_retry($restoreUrl, [
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => [
            'confirm' => $confirm,
            'file' => new CURLFile($uploadedTmpPath, 'application/sql', $uploadedName),
        ],
        CURLOPT_TIMEOUT => 60, // restore kan ta litt tid på en større dump
    ]);

    if ($response === false) {
        http_response_code(502);
        echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
        exit;
    }

    http_response_code($httpCode ?: 502);
    echo $response;
    exit;
}

// Uten ?id=... hentes hele listen (GET /media/content), som før.
// Med ?id=<hex content_id> hentes én enkelt rad for detaljsiden
// (GET /media/content/{id}), brukt av detail.php.
$contentId = $_GET['id'] ?? null;
if ($contentId !== null && !preg_match('/^[0-9a-fA-F]{32}$/', $contentId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ugyldig id-parameter']);
    exit;
}

$url = $contentId !== null
    ? MEDIA_API_BASE_URL . '/media/content/' . $contentId
    : MEDIA_API_BASE_URL . '/media/content';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => with_api_key_header([]),
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode ?: 502);
    echo json_encode(['error' => 'API-et svarte med feilkode ' . $httpCode, 'body' => $response]);
    exit;
}

// Send JSON-responsen fra API-et videre uendret.
echo $response;
