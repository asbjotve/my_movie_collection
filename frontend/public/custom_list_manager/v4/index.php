<?php
declare(strict_types=1);

require_once __DIR__ . '/lang.php';

// Krever innlogget bruker (samme JWT/sesjon som website_template_example/v18)
// siden denne siden POST-er til skrive-endepunkter i backend.
require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';
require_login();

$listsEndpoint = 'http://172.19.0.1:9500/lists';
$listItemsEndpoint = 'http://172.19.0.1:9500/lists/items';
// Base for the v4 inline "edit list" endpoints (GET .../{list_id}/items,
// PATCH .../items/{id}, DELETE .../{list_id}/items/{id}).
$listsBaseEndpoint = 'http://172.19.0.1:9500/lists';

$createMessage = null;
$createMessageType = 'info';
$createdList = null;

// Which tab should be active on load — defaults to "create" unless the
// page was reloaded right after creating a list.
$activeTab = 'create';

/**
 * Henter INTERNAL_API_KEY fra frontend/.env (samme mønster som
 * fetchListsWithRetry() lenger ned) - brukes av lese-endepunktet
 * GET /lists/{id}/items som krever X-API-Key i stedet for JWT.
 */
function internalApiKey(): string
{
    static $key = null;
    if ($key === null) {
        require_once __DIR__ . '/../../../vendor/autoload.php';
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
        $dotenv->load();
        $key = $_ENV['INTERNAL_API_KEY'] ?? '';
    }
    return $key;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function uploadErrorMessage(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => t('clm.upload_errors.size'),
        UPLOAD_ERR_PARTIAL => t('clm.upload_errors.partial'),
        UPLOAD_ERR_NO_FILE => t('clm.upload_errors.no_file'),
        UPLOAD_ERR_NO_TMP_DIR => t('clm.upload_errors.no_tmp_dir'),
        UPLOAD_ERR_CANT_WRITE => t('clm.upload_errors.cant_write'),
        UPLOAD_ERR_EXTENSION => t('clm.upload_errors.extension'),
        default => t('clm.upload_errors.unknown'),
    };
}

function apiRequest(string $url, array $postFields): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [auth_bearer_header()],
        CURLOPT_TIMEOUT => 30,
    ]);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$rawResponse, $curlError, $httpCode];
}

/**
 * Generic curl helper for the v4 inline "edit list" AJAX endpoints below -
 * supports GET (with X-API-Key, for reads) and PATCH/DELETE (with JWT
 * bearer, for writes), unlike apiRequest() above which is POST-only.
 */
function apiCall(string $method, string $url, array $postFields = [], bool $useApiKey = false): array
{
    $ch = curl_init($url);
    $headers = $useApiKey ? ['X-API-Key: ' . internalApiKey()] : [auth_bearer_header()];

    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ];

    if ($method !== 'GET' && $postFields !== []) {
        $options[CURLOPT_POSTFIELDS] = $postFields;
    }

    curl_setopt_array($ch, $options);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$rawResponse, $curlError, $httpCode];
}

// ============================================================
//  AJAX gate for the v4 inline "edit list" view.
//  Handles all read/write calls the item table/add-form makes
//  (list_items, add_item, update_item, delete_item), each proxied
//  through here so the browser never needs the JWT/API key directly
//  (same reasoning as apiRequest()/fetchListsWithRetry() below).
// ============================================================
if (($_GET['ajax'] ?? '') !== '') {
    header('Content-Type: application/json; charset=utf-8');
    $ajaxAction = (string) $_GET['ajax'];

    $respond = function (int $httpCode, $payload): never {
        http_response_code($httpCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    };

    $forwardResult = function (?string $rawResponse, string $curlError, int $httpCode) use ($respond): never {
        if ($rawResponse === false) {
            $respond(502, ['error' => t('clm.messages.api_unreachable', $curlError)]);
        }
        $decoded = json_decode($rawResponse, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            $respond(200, is_array($decoded) ? $decoded : []);
        }
        $respond(
            $httpCode >= 400 ? $httpCode : 502,
            ['error' => is_array($decoded) ? ($decoded['detail'] ?? t('clm.messages.api_unknown_error')) : t('clm.messages.api_invalid_response')]
        );
    };

    if ($ajaxAction === 'list_items') {
        $listId = trim((string) ($_GET['list_id'] ?? ''));
        if ($listId === '') {
            $respond(400, ['error' => t('clm.messages.list_required')]);
        }
        [$rawResponse, $curlError, $httpCode] = apiCall('GET', "$listsBaseEndpoint/" . rawurlencode($listId) . '/items', [], true);
        $forwardResult($rawResponse, $curlError, $httpCode);
    }

    if ($ajaxAction === 'add_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $listId = trim((string) ($_POST['list_id'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $originalTitle = trim((string) ($_POST['original_title'] ?? ''));
        $firstReleaseYear = trim((string) ($_POST['first_release_year'] ?? ''));
        $imdbId = trim((string) ($_POST['imdb_id'] ?? ''));
        $tmdbId = trim((string) ($_POST['tmdb_id'] ?? ''));
        $tvdbId = trim((string) ($_POST['tvdb_id'] ?? ''));
        $season = trim((string) ($_POST['season'] ?? ''));
        $coverImage = $_FILES['cover_image'] ?? null;

        if ($listId === '') {
            $respond(400, ['error' => t('clm.messages.list_required')]);
        }
        if ($title === '') {
            $respond(400, ['error' => t('clm.messages.title_required')]);
        }
        if (is_array($coverImage) && ($coverImage['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK && ($coverImage['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $respond(400, ['error' => uploadErrorMessage((int) $coverImage['error'])]);
        }

        $hasCoverImage = is_array($coverImage) && ($coverImage['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

        $postFields = ['list_id' => $listId, 'title' => $title];
        if ($hasCoverImage) {
            $postFields['cover_image'] = new CURLFile($coverImage['tmp_name'], $coverImage['type'] ?: 'application/octet-stream', $coverImage['name']);
        }
        if ($originalTitle !== '') $postFields['original_title'] = $originalTitle;
        if ($firstReleaseYear !== '') $postFields['first_release_year'] = $firstReleaseYear;
        if ($imdbId !== '') $postFields['imdb_id'] = $imdbId;
        if ($tmdbId !== '') $postFields['tmdb_id'] = $tmdbId;
        if ($tvdbId !== '') $postFields['tvdb_id'] = $tvdbId;
        if ($season !== '') $postFields['season'] = $season;

        [$rawResponse, $curlError, $httpCode] = apiRequest($listItemsEndpoint, $postFields);
        $forwardResult($rawResponse, $curlError, $httpCode);
    }

    if ($ajaxAction === 'update_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $listItemId = trim((string) ($_POST['list_item_id'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));
        $originalTitle = trim((string) ($_POST['original_title'] ?? ''));
        $firstReleaseYear = trim((string) ($_POST['first_release_year'] ?? ''));
        $imdbId = trim((string) ($_POST['imdb_id'] ?? ''));
        $tmdbId = trim((string) ($_POST['tmdb_id'] ?? ''));
        $tvdbId = trim((string) ($_POST['tvdb_id'] ?? ''));
        $season = trim((string) ($_POST['season'] ?? ''));
        $coverImage = $_FILES['cover_image'] ?? null;

        if ($listItemId === '') {
            $respond(400, ['error' => t('clm.messages.item_id_required')]);
        }
        if ($title === '') {
            $respond(400, ['error' => t('clm.messages.title_required')]);
        }
        if (is_array($coverImage) && ($coverImage['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK && ($coverImage['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $respond(400, ['error' => uploadErrorMessage((int) $coverImage['error'])]);
        }

        $hasCoverImage = is_array($coverImage) && ($coverImage['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

        $postFields = ['title' => $title];
        if ($hasCoverImage) {
            $postFields['cover_image'] = new CURLFile($coverImage['tmp_name'], $coverImage['type'] ?: 'application/octet-stream', $coverImage['name']);
        }
        // Alle andre felt sendes alltid med (selv tomme) - dette er en full
        // erstatning av verdiene, ikke en delvis oppdatering (se
        // update_list_item() i backend), slik at man kan tømme et felt.
        $postFields['original_title'] = $originalTitle;
        $postFields['first_release_year'] = $firstReleaseYear;
        $postFields['imdb_id'] = $imdbId;
        $postFields['tmdb_id'] = $tmdbId;
        $postFields['tvdb_id'] = $tvdbId;
        $postFields['season'] = $season;

        [$rawResponse, $curlError, $httpCode] = apiCall('PATCH', "$listsBaseEndpoint/items/" . rawurlencode($listItemId), $postFields);
        $forwardResult($rawResponse, $curlError, $httpCode);
    }

    if ($ajaxAction === 'delete_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $listId = trim((string) ($_POST['list_id'] ?? ''));
        $listItemId = trim((string) ($_POST['list_item_id'] ?? ''));

        if ($listId === '' || $listItemId === '') {
            $respond(400, ['error' => t('clm.messages.item_id_required')]);
        }

        [$rawResponse, $curlError, $httpCode] = apiCall('DELETE', "$listsBaseEndpoint/" . rawurlencode($listId) . '/items/' . rawurlencode($listItemId));
        $forwardResult($rawResponse, $curlError, $httpCode);
    }

    $respond(400, ['error' => 'Ugyldig ajax-action.']);
}

// Handle "create list" form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'create_list') {
    $activeTab = 'create';
    $listName = trim((string) ($_POST['list_name'] ?? ''));

    if ($listName === '') {
        $createMessage = t('clm.messages.list_name_required');
        $createMessageType = 'error';
    } else {
        [$rawResponse, $curlError, $httpCode] = apiRequest($listsEndpoint, ['list_name' => $listName]);

        if ($rawResponse === false) {
            $createMessage = t('clm.messages.api_unreachable', $curlError);
            $createMessageType = 'error';
        } else {
            $decoded = json_decode($rawResponse, true);

            if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded)) {
                $createdList = $decoded;
                $createMessage = t('clm.messages.list_created', (string) ($decoded['list_name'] ?? $listName));
                $createMessageType = 'success';
            } else {
                $createMessage = is_array($decoded)
                    ? (string) ($decoded['detail'] ?? t('clm.messages.api_unknown_error'))
                    : t('clm.messages.api_invalid_response');
                $createMessageType = 'error';
            }
        }
    }
}

// Fetch existing lists for the dropdown (always excludes "Wishlist" — handled separately).
//
// Backend (FastAPI/uvicorn) is occasionally restarted (e.g. after a deploy),
// and the very first request against it right after a restart can hit a
// short window where it isn't listening yet ("connection refused") or is
// still warming up its DB connection pool. Without a retry, that transient
// failure showed up to the user as "list data didn't load" until they
// manually refreshed the page. Retry a couple of times with a short delay
// before giving up and showing the error notice.
function fetchListsWithRetry(string $url, int $maxAttempts = 3, int $delayMs = 400): array
{
    // GET /lists er et lese-endepunkt og krever X-API-Key, ikke JWT (se
    // app/api_key.py). Lastes fra frontend/.env, samme mønster som
    // website_template_example/v18/api.php.
    require_once __DIR__ . '/../../../vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
    $dotenv->load();
    $internalApiKey = $_ENV['INTERNAL_API_KEY'] ?? '';

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['X-API-Key: ' . $internalApiKey],
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Success, or a definitive (non-transient) HTTP error from the API
        // itself - no point retrying either of those.
        $isTransportFailure = $response === false || $curlError !== '';
        $isServerError = $response !== false && $httpCode >= 500;

        if (!$isTransportFailure && !$isServerError) {
            return [$response, $curlError, $httpCode];
        }

        if ($attempt < $maxAttempts) {
            usleep($delayMs * 1000);
        }
    }

    return [$response, $curlError, $httpCode];
}

$availableLists = [];
$listsFetchError = null;

[$listsResponse, $listsCurlError, $listsHttpCode] = fetchListsWithRetry($listsEndpoint);

if ($listsResponse === false || $listsCurlError) {
    $listsFetchError = t('clm.messages.lists_fetch_failed', $listsCurlError);
} elseif ($listsHttpCode !== 200) {
    $listsFetchError = t('clm.messages.lists_fetch_http_err', $listsHttpCode);
} else {
    $decodedLists = json_decode($listsResponse, true);
    if (is_array($decodedLists)) {
        $availableLists = $decodedLists;
    }
}
?>
<!doctype html>
<html lang="<?= h($GLOBALS['__clm_lang']) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= h(t('clm.meta_title')) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{
      --bg:#0b1020;
      --panel:#121a33;
      --panel2:#0f1730;
      --text:#e8ecff;
      --muted:#a8b2d8;
      --line:#253057;
      --accent:#7aa2ff;
      --accent-dark:#5c85e6;
      --good:#3ddc97;
      --bad:#ff6b81;
    }
    *{ box-sizing:border-box; }
    body{
      margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
      background: radial-gradient(1200px 500px at 20% -10%, #1b2a66, transparent),
                  radial-gradient(900px 400px at 80% 0%, #35215c, transparent),
                  var(--bg);
      color:var(--text);
    }
    header{
      position:sticky; top:0; z-index:10;
      backdrop-filter: blur(10px);
      background: rgba(11,16,32,.75);
      border-bottom: 1px solid rgba(37,48,87,.7);
      padding:16px 18px;
      display:flex; gap:12px; align-items:center; justify-content:space-between;
    }
    header h1{ margin:0; font-size:16px; letter-spacing:.2px; color:var(--muted); font-weight:600; }
    header .tag{ color:var(--muted); font-size:12px; }

    .lang-switch{ display:flex; gap:6px; }
    .lang-switch a{
      display:inline-block;
      padding:6px 10px;
      border-radius:8px;
      font-size:12px;
      font-weight:700;
      text-decoration:none;
      color: var(--muted);
      border: 1px solid rgba(37,48,87,.8);
    }
    .lang-switch a.active{
      color: #0b1020;
      background: var(--accent);
      border-color: var(--accent);
    }

    main{
      padding:24px 18px 40px;
      display:grid;
      place-items:start center;
    }

    .card{
      width:min(100%, 640px);
      background: rgba(18,26,51,.65);
      border:1px solid rgba(37,48,87,.8);
      border-radius: 16px;
      padding:20px;
      /* Bootstrap's own .card rule sets color:var(--bs-body-color) (dark
         text), which otherwise wins since it's the only rule touching the
         `color` property on this selector - explicitly override it here so
         all plain text nested inside (headings, table cells, etc.) stays
         readable against our dark background. */
      color: var(--text);
    }
    /* The "edit list" tab shows a multi-column table, so it needs more
       horizontal room than the create-list form (unlike .card above,
       which stays narrow for the create tab's simple layout). */
    .card:has(#tab-edit.active){ width:min(100%, 980px); }

    .card h2{ margin:0 0 6px; font-size:20px; }
    .card p.lead{ margin:0 0 20px; color:var(--muted); font-size:13px; line-height:1.5; }

    /* --- Tabs --- */
    .tabs{
      display:flex;
      gap:6px;
      margin-bottom:20px;
      border-bottom:1px solid rgba(37,48,87,.8);
    }
    .tab-btn{
      appearance:none; border:0;
      background:transparent;
      color: var(--muted);
      font: inherit; font-weight:700; font-size:14px;
      padding:12px 16px;
      cursor:pointer;
      border-bottom:2px solid transparent;
      margin-bottom:-1px;
      transition: color .15s, border-color .15s;
    }
    .tab-btn:hover{ color: var(--text); }
    .tab-btn.active{ color: var(--accent); border-bottom-color: var(--accent); }

    .tab-panel{ display:none; }
    .tab-panel.active{ display:block; }

    .notice{
      margin-bottom:16px;
      padding:12px 14px;
      border-radius:12px;
      font-size:13px;
      border:1px solid transparent;
    }
    .notice.success{ background: rgba(61,220,151,.12); border-color: rgba(61,220,151,.4); color: var(--good); }
    .notice.error{ background: rgba(255,107,129,.12); border-color: rgba(255,107,129,.4); color: var(--bad); }

    form{ display:grid; gap:14px; }

    label{ display:grid; gap:6px; font-size:13px; font-weight:600; color:var(--muted); }

    input, select{
      width:100%;
      background: rgba(15,23,48,.7);
      border:1px solid rgba(37,48,87,.9);
      border-radius: 12px;
      padding:12px 13px;
      color: var(--text);
      font: inherit;
    }
    input[type="file"]{ padding:10px; }
    input:focus, select:focus{
      outline: 3px solid rgba(122,162,255,.18);
      border-color: var(--accent);
    }
    input::placeholder{ color: #5c6690; }

    select{ appearance:none; }
    select option{ background: var(--panel2); color: var(--text); }

    fieldset{
      border:1px solid rgba(37,48,87,.8);
      border-radius: 14px;
      padding:12px 14px 14px;
      display:grid; gap:12px;
    }
    legend{
      font-size:12px; font-weight:700;
      color:var(--muted);
      text-transform:uppercase; letter-spacing:.08em;
      padding:0 6px;
    }

    .hint{ color: var(--muted); font-size:12px; margin-top:-6px; }

    button[type="submit"]{
      appearance:none; border:0;
      background: var(--accent);
      color: #0b1020;
      font: inherit; font-weight:700;
      border-radius:12px;
      padding:14px 16px;
      cursor:pointer;
    }
    button[type="submit"]:active{ background: var(--accent-dark); }
    button[type="submit"]:disabled{ opacity:.5; cursor:not-allowed; }

    .preview{
      display:none;
      width:100%;
      max-height:260px;
      object-fit:cover;
      border-radius:14px;
      border:1px solid rgba(37,48,87,.8);
    }

    .panel{
      margin-top:18px;
      background: rgba(15,23,48,.6);
      border:1px solid rgba(37,48,87,.8);
      border-radius: 14px;
      padding:14px;
    }
    .panel h4{
      margin:0 0 10px; color:var(--muted); font-size:12px;
      text-transform:uppercase; letter-spacing:.08em;
    }
    .kv{ display:grid; grid-template-columns: 140px 1fr; gap:8px 12px; }
    .k{ color:var(--muted); font-size:12px; }
    .v{ font-size:13px; word-break:break-word; }
    .v a{ color: var(--accent); }

    .empty-hint{
      color: var(--muted);
      font-size: 13px;
      background: rgba(15,23,48,.6);
      border: 1px solid rgba(37,48,87,.8);
      border-radius: 12px;
      padding: 12px 14px;
    }

    /* --- Inline items table (edit tab, v4) --- */
    #editItemsTable{ overflow-x:auto; margin: 10px 0 18px; }
    table.items-table{
      width:100%;
      border-collapse: collapse;
      font-size:13px;
      table-layout: fixed;
    }
    table.items-table th, table.items-table td{
      border-bottom: 1px solid rgba(37,48,87,.6);
      padding: 6px 8px;
      text-align:left;
      vertical-align: top;
    }
    table.items-table th{ color: var(--muted); font-weight:600; white-space:nowrap; }
    /* Read-mode text: wrap long titles instead of clipping/hiding them. */
    table.items-table .row-text{
      display:block;
      word-break: break-word;
      overflow-wrap: anywhere;
      white-space: normal;
      color: var(--text);
    }
    table.items-table tr.row-editing{ background: rgba(122,162,255,.06); }
    table.items-table input[type="text"], table.items-table input[type="number"]{
      width: 100%;
      min-width: 60px;
      padding:6px 8px;
      border-radius:8px;
      border:1px solid rgba(37,48,87,.8);
      background: rgba(11,16,32,.6);
      color: var(--text);
      font-size:13px;
      box-sizing: border-box;
    }
    table.items-table .cell-cover{ width:64px; }
    table.items-table .cell-title, table.items-table .cell-original-title{ max-width: 220px; }
    table.items-table .row-cover-preview{
      display:block; width:44px; height:auto; border-radius:6px; margin-bottom:4px;
    }
    table.items-table .row-cover-input{ font-size:11px; max-width:64px; }
    table.items-table .cell-actions{ white-space:nowrap; }
    table.items-table .cell-actions button{ font-size:12px; padding:6px 8px; margin-right:4px; margin-bottom:4px; }
    table.items-table .row-status{ display:block; font-size:11px; margin-top:4px; }
    table.items-table .row-status.success{ color: var(--good); }
    table.items-table .row-status.error{ color: var(--bad); }
    table.items-table .row-status.info{ color: var(--muted); }

    @media (max-width: 480px){
      .kv{ grid-template-columns: 1fr; }
      .k{ margin-top:6px; }
      main{ padding:16px 8px 32px; }
      .card{ padding:16px 14px; border-radius:12px; }
    }

    /* On narrow screens, reflow the items table into a stacked list of
       "cards" (one per item) instead of a horizontally-scrolling table -
       this keeps long titles fully readable without truncation. */
    @media (max-width: 720px){
      table.items-table{ table-layout: auto; }
      table.items-table thead{ display:none; }
      table.items-table, table.items-table tbody, table.items-table tr, table.items-table td{
        display:block;
        width:100%;
      }
      table.items-table tr{
        border:1px solid rgba(37,48,87,.8);
        border-radius:12px;
        padding:8px 10px;
        margin-bottom:12px;
      }
      table.items-table td{
        border-bottom:none;
        padding:4px 0;
        display:flex;
        gap:10px;
        align-items:flex-start;
      }
      table.items-table td[data-label]::before{
        content: attr(data-label);
        flex:0 0 110px;
        color: var(--muted);
        font-weight:600;
      }
      table.items-table .cell-cover{ width:auto; }
      table.items-table .cell-title, table.items-table .cell-original-title{ max-width:none; }
    }

    /* --- TMDB search: dark-theme Bootstrap modal overrides --- */
    .title-row{ display:flex; gap:10px; align-items:flex-end; flex-wrap: wrap; }
    .title-row label{ flex:1 1 200px; min-width:0; }
    .btn-tmdb{
      appearance:none;
      border:1px solid rgba(122,162,255,.5);
      background: rgba(122,162,255,.12);
      color: var(--accent);
      font: inherit; font-weight:700; font-size:13px;
      border-radius:12px;
      padding:12px 14px;
      cursor:pointer;
      white-space:nowrap;
    }
    .btn-tmdb:hover{ background: rgba(122,162,255,.2); }

    .btn-tvdb{
      appearance:none;
      border:1px solid rgba(255,193,102,.5);
      background: rgba(255,193,102,.12);
      color: #ffc166;
      font: inherit; font-weight:700; font-size:13px;
      border-radius:12px;
      padding:12px 14px;
      cursor:pointer;
      white-space:nowrap;
    }
    .btn-tvdb:hover{ background: rgba(255,193,102,.2); }

    #searchModal .modal-content,
    #tvdbSearchModal .modal-content{
      background: var(--panel);
      color: var(--text);
      border:1px solid rgba(37,48,87,.9);
      border-radius:16px;
    }
    #searchModal .modal-header,
    #tvdbSearchModal .modal-header{
      background: var(--panel2) !important;
      border-bottom:1px solid rgba(37,48,87,.8);
    }
    #searchModal .modal-title,
    #tvdbSearchModal .modal-title{ color: var(--text); }
    #searchModal .form-control,
    #tvdbSearchModal .form-control{
      background: rgba(15,23,48,.7);
      border:1px solid rgba(37,48,87,.9);
      color: var(--text);
    }
    #searchModal .form-control:focus,
    #tvdbSearchModal .form-control:focus{
      background: rgba(15,23,48,.7);
      color: var(--text);
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(122,162,255,.18);
    }
    #searchModal .input-group-text,
    #tvdbSearchModal .input-group-text{
      background: rgba(15,23,48,.7);
      border:1px solid rgba(37,48,87,.9);
      color: var(--muted);
    }
    #searchModal .form-text,
    #tvdbSearchModal .form-text{ color: var(--muted); }

    .dropdown-results{
      border:1px solid rgba(37,48,87,.8);
      border-radius:12px;
      overflow:hidden;
      display:none;
      max-height:320px;
      overflow-y:auto;
      background: rgba(15,23,48,.9);
    }
    .dropdown-results.show{ display:block; }
    .dropdown-item-custom{
      display:flex; gap:12px; padding:10px;
      cursor:pointer;
      border-bottom:1px solid rgba(37,48,87,.6);
    }
    .dropdown-item-custom:hover{ background: rgba(122,162,255,.08); }
    .dropdown-poster{
      width:48px; height:72px; object-fit:cover; border-radius:6px; flex:0 0 auto;
    }
    .dropdown-no-poster{
      width:48px; height:72px; border-radius:6px;
      background: rgba(37,48,87,.6);
      display:grid; place-items:center;
      font-size:11px; color: var(--muted);
      flex:0 0 auto; text-align:center; padding:4px;
    }
    .dropdown-info{ flex:1; min-width:0; color: var(--text); }
    .dropdown-title{ font-weight:600; display:flex; gap:8px; align-items:center; }
    .dropdown-meta{ color: var(--muted); font-size:13px; margin-top:2px; }
    .media-badge{ font-size:11px; padding:.25em .5em; border-radius:999px; }
    .badge-tv{ background:#6f42c1; color:#fff; }
    .badge-movie{ background: var(--accent); color:#0b1020; }
    .bg-purple{ background:#6f42c1 !important; color:#fff !important; }
    .selected-item{ color: var(--text); }
    .selected-item .small{ color: var(--muted); }
    .imdb-link{ color: var(--accent); }
  </style>
</head>
<body>
<header>
  <h1><?= h(t('clm.header.title')) ?></h1>
  <div class="tag"><?= t('clm.header.tag') ?></div>
  <div class="lang-switch">
    <?php foreach (CLM_AVAILABLE_LANGS as $langCode): ?>
      <a href="?lang=<?= h($langCode) ?>" class="<?= $GLOBALS['__clm_lang'] === $langCode ? 'active' : '' ?>"><?= h(strtoupper($langCode)) ?></a>
    <?php endforeach; ?>
  </div>
</header>

<main>
  <section class="card">
    <div class="tabs">
      <button type="button" class="tab-btn <?= $activeTab === 'create' ? 'active' : '' ?>" data-tab="create"><?= h(t('clm.tabs.create')) ?></button>
      <button type="button" class="tab-btn <?= $activeTab === 'edit' ? 'active' : '' ?>" data-tab="edit"><?= h(t('clm.tabs.edit')) ?></button>
    </div>

    <!-- Tab 1: create a new list -->
    <div class="tab-panel <?= $activeTab === 'create' ? 'active' : '' ?>" id="tab-create">
      <h2><?= h(t('clm.create_tab.heading')) ?></h2>
      <p class="lead"><?= h(t('clm.create_tab.lead')) ?></p>

      <?php if ($createMessage !== null): ?>
        <div class="notice <?= h($createMessageType) ?>"><?= h($createMessage) ?></div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="form" value="create_list">
        <label>
          <?= h(t('clm.create_tab.list_name_label')) ?>
          <input type="text" name="list_name" placeholder="<?= h(t('clm.create_tab.list_name_placeholder')) ?>" required>
        </label>
        <button type="submit"><?= h(t('clm.create_tab.submit')) ?></button>
      </form>
    </div>

    <!-- Tab 2: view/edit items in an existing list, inline (v4) - replaces
         v3's separate "legg til element" form: items are now listed and
         editable directly, and adding a new item happens in the same view
         instead of a dedicated tab. -->
    <div class="tab-panel <?= $activeTab === 'edit' ? 'active' : '' ?>" id="tab-edit">
      <h2><?= h(t('clm.edit_tab.heading')) ?></h2>
      <p class="lead"><?= h(t('clm.edit_tab.lead')) ?></p>

      <?php if ($listsFetchError !== null): ?>
        <div class="notice error"><?= h($listsFetchError) ?></div>
      <?php elseif (empty($availableLists)): ?>
        <div class="empty-hint"><?= h(t('clm.edit_tab.empty_hint')) ?></div>
      <?php else: ?>
        <label>
          <?= h(t('clm.edit_tab.list_label')) ?>
          <select id="editListSelect">
            <option value=""><?= h(t('clm.add_tab.list_placeholder')) ?></option>
            <?php foreach ($availableLists as $list): ?>
              <option value="<?= h((string) ($list['list_id'] ?? '')) ?>">
                <?= h((string) ($list['list_name'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <div id="editItemsStatus"></div>
        <div id="editItemsTable"></div>

        <hr>

        <h3><?= h(t('clm.edit_tab.add_new_heading')) ?></h3>

        <div id="addItemStatus"></div>

        <div class="title-row">
          <label>
            <?= h(t('clm.add_tab.title_label')) ?>
            <input type="text" id="title" placeholder="<?= h(t('clm.add_tab.title_placeholder')) ?>" required>
          </label>
          <button type="button" class="btn-tmdb" data-bs-toggle="modal" data-bs-target="#searchModal"><?= h(t('clm.add_tab.btn_tmdb')) ?></button>
          <button type="button" class="btn-tvdb" data-bs-toggle="modal" data-bs-target="#tvdbSearchModal"><?= h(t('clm.add_tab.btn_tvdb')) ?></button>
        </div>

        <label>
          <?= h(t('clm.add_tab.original_title_label')) ?>
          <input type="text" id="original_title" placeholder="<?= h(t('clm.add_tab.title_placeholder')) ?>">
        </label>

        <label>
          <?= h(t('clm.add_tab.year_label')) ?>
          <input type="number" id="first_release_year" inputmode="numeric" min="1888" max="2100" placeholder="<?= h(t('clm.add_tab.year_placeholder')) ?>">
        </label>

        <fieldset>
          <legend><?= h(t('clm.add_tab.external_ids_legend')) ?></legend>

          <label>
            <?= h(t('clm.add_tab.imdb_label')) ?>
            <input type="text" id="imdb_id" placeholder="tt1160419">
          </label>

          <label>
            <?= h(t('clm.add_tab.tmdb_label')) ?>
            <input type="text" id="tmdb_id" placeholder="438631">
          </label>

          <label>
            <?= h(t('clm.add_tab.tvdb_label')) ?>
            <input type="text" id="tvdb_id" placeholder="81189">
          </label>
        </fieldset>

        <label>
          <?= h(t('clm.add_tab.season_label')) ?>
          <input type="text" id="season" placeholder="<?= h(t('clm.add_tab.season_placeholder')) ?>">
        </label>
        <div class="hint"><?= h(t('clm.add_tab.season_hint')) ?></div>

        <label>
          <?= h(t('clm.add_tab.cover_label')) ?>
          <input type="file" id="cover_image" accept="image/*" capture="environment">
        </label>
        <div class="hint"><?= h(t('clm.add_tab.cover_hint')) ?></div>

        <img id="preview" class="preview" alt="<?= h(t('clm.add_tab.title_label')) ?>">

        <button type="button" id="btnAddItem"><?= h(t('clm.add_tab.submit')) ?></button>
      <?php endif; ?>
    </div>
  </section>

</main>

<!-- TMDB search modal -->
<div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="searchModalLabel"><?= h(t('clm.modal_tmdb.title')) ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= h(t('clm.modal_tmdb.close_label')) ?>"></button>
      </div>

      <div class="modal-body">
        <div class="search-wrapper">
          <div class="input-group input-group-lg">
            <span class="input-group-text">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
              </svg>
            </span>

            <input type="text" id="searchInput" class="form-control" placeholder="<?= h(t('clm.modal_tmdb.search_placeholder')) ?>" autocomplete="off">

            <span class="input-group-text" id="loadingSpinner" style="display: none;">
              <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden"><?= h(t('clm.js.common.searching')) ?></span>
              </div>
            </span>
          </div>

          <div class="form-text mt-1">
            <?= h(t('clm.modal_tmdb.tip')) ?>
          </div>

          <div id="dropdownResults" class="dropdown-results mt-2"></div>
        </div>

        <div id="searchStatus" class="text-center mt-2 text-muted" style="min-height: 24px;"></div>
        <div id="selectedItem" class="mt-3"></div>
      </div>
    </div>
  </div>
</div>

<!-- TVDB search modal -->
<div class="modal fade" id="tvdbSearchModal" tabindex="-1" aria-labelledby="tvdbSearchModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tvdbSearchModalLabel"><?= h(t('clm.modal_tvdb.title')) ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= h(t('clm.modal_tvdb.close_label')) ?>"></button>
      </div>

      <div class="modal-body">
        <div class="mb-3 d-flex flex-wrap gap-3">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="tvdbTypeRadios" id="tvdbTypeSeries" value="series" checked>
            <label class="form-check-label" for="tvdbTypeSeries"><?= h(t('clm.modal_tvdb.type_series')) ?></label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="tvdbTypeRadios" id="tvdbTypeMovies" value="movie">
            <label class="form-check-label" for="tvdbTypeMovies"><?= h(t('clm.modal_tvdb.type_movies')) ?></label>
          </div>
        </div>

        <div class="search-wrapper">
          <div class="input-group input-group-lg">
            <span class="input-group-text">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
              </svg>
            </span>

            <input type="text" id="tvdbSearchInput" class="form-control" placeholder="<?= h(t('clm.modal_tvdb.search_placeholder')) ?>" autocomplete="off">

            <span class="input-group-text" id="tvdbLoadingSpinner" style="display: none;">
              <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden"><?= h(t('clm.js.common.searching')) ?></span>
              </div>
            </span>
          </div>

          <div class="form-text mt-1">
            <?= h(t('clm.modal_tvdb.tip')) ?>
          </div>

          <div id="tvdbDropdownResults" class="dropdown-results mt-2"></div>
        </div>

        <div id="tvdbSearchStatus" class="text-center mt-2 text-muted" style="min-height: 24px;"></div>
        <div id="tvdbSelectedItem" class="mt-3"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Eksponerer gjeldende språkoversettelser til JS-filene (script.js / tvdb-search.js),
  // slik at de aldri trenger hardkodet norsk/engelsk tekst.
  window.CLM_I18N = <?= json_encode(clm_translations_branch('clm.js'), JSON_UNESCAPED_UNICODE) ?>;
  // Eksponerer teksten items-editor.js trenger (samme mønster som CLM_I18N over).
  window.CLM_EDIT_I18N = <?= json_encode(clm_translations_branch('clm.edit_tab'), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script>
  // --- Tabs ---
  const tabButtons = document.querySelectorAll('.tab-btn');
  const tabPanels = {
    create: document.getElementById('tab-create'),
    edit: document.getElementById('tab-edit'),
  };

  tabButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.tab;

      tabButtons.forEach((b) => b.classList.toggle('active', b === btn));
      Object.entries(tabPanels).forEach(([key, panel]) => {
        panel?.classList.toggle('active', key === target);
      });

      if (target === 'edit') {
        window.clmInitEditTab?.();
      }
    });
  });

  // If the "edit" tab is already active on load (e.g. after a page reload),
  // make sure its items-editor is initialised too.
  if (tabPanels.edit?.classList.contains('active')) {
    document.addEventListener('DOMContentLoaded', () => window.clmInitEditTab?.());
  }

  // --- Cover image preview (add item panel) ---
  const fileInput = document.getElementById('cover_image');
  const preview = document.getElementById('preview');

  fileInput?.addEventListener('change', (event) => {
    const file = event.target.files?.[0];
    if (!file) {
      preview.style.display = 'none';
      preview.removeAttribute('src');
      return;
    }

    preview.src = URL.createObjectURL(file);
    preview.style.display = 'block';
  });
</script>
<script src="script.js"></script>
<script src="tvdb-search.js"></script>
<script src="items-editor.js"></script>
</body>
</html>
