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

$sectionRequiresLogin = fetch_section_access(['mine_filmer' => false]);
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

    $source = (string)($_GET['source'] ?? '');
    $externalId = (string)($_GET['external_id'] ?? '');

    if (!in_array($source, ['tmdb', 'tvdb'], true) || $externalId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Mangler eller ugyldig source/external_id-parameter']);
        exit;
    }

    $patchUrl = MEDIA_API_BASE_URL . '/media/external-source/' . rawurlencode($source) . '/' . rawurlencode($externalId);

    $ch = curl_init($patchUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_HTTPHEADER => [auth_bearer_header()],
        CURLOPT_TIMEOUT => 20, // henting fra TMDB/TVDB kan ta noen sekunder
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

    $source = (string)($_GET['source'] ?? '');
    $externalId = (string)($_GET['external_id'] ?? '');

    if (!in_array($source, ['tmdb', 'tvdb'], true) || $externalId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Mangler eller ugyldig source/external_id-parameter']);
        exit;
    }

    $mergeUrl = MEDIA_API_BASE_URL . '/media/external-source/' . rawurlencode($source) . '/' . rawurlencode($externalId) . '/merge';

    $ch = curl_init($mergeUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [auth_bearer_header()],
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

// GET ?action=list_groups
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

// GET ?action=list_covers&id=<hex content_id>
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

    $ch = curl_init($setCoverUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', auth_bearer_header()],
        CURLOPT_POSTFIELDS => json_encode(['file_path' => $filePath]),
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

    $contentId = (string)($_GET['id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $contentId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $updateUrl = MEDIA_API_BASE_URL . '/media/content/' . $contentId;

    $ch = curl_init($updateUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', auth_bearer_header()],
        CURLOPT_POSTFIELDS => $body,
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

    $contentId = (string)($_GET['id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $contentId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $lockUrl = MEDIA_API_BASE_URL . '/media/content/' . $contentId . '/lock';

    $ch = curl_init($lockUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', auth_bearer_header()],
        CURLOPT_POSTFIELDS => $body,
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

    $collectionId = (string)($_GET['collection_id'] ?? '');
    $copyId = (string)($_GET['copy_id'] ?? '');
    if (!preg_match('/^[0-9a-fA-F]{32}$/', $collectionId) || !preg_match('/^\d+$/', $copyId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig collection_id/copy_id-parameter']);
        exit;
    }

    $body = file_get_contents('php://input');

    $updateUrl = MEDIA_API_BASE_URL . '/media/physical-copy/' . $collectionId . '/' . $copyId;

    $ch = curl_init($updateUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', auth_bearer_header()],
        CURLOPT_POSTFIELDS => $body,
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
