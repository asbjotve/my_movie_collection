<?php
declare(strict_types=1);

require_once __DIR__ . '/lang.php';

$listsEndpoint = 'http://172.19.0.1:9500/lists';
$listItemsEndpoint = 'http://172.19.0.1:9500/lists/items';

$createMessage = null;
$createMessageType = 'info';
$createdList = null;

$itemMessage = null;
$itemMessageType = 'info';
$itemResponseData = null;

// Which tab should be active on load — defaults to "create" unless we just
// submitted the "add item" form (so the user stays on that tab after submit).
$activeTab = 'create';

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
        CURLOPT_TIMEOUT => 30,
    ]);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$rawResponse, $curlError, $httpCode];
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

// Handle "add item to list" form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add_item') {
    $activeTab = 'add';
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
        $itemMessage = t('clm.messages.list_required');
        $itemMessageType = 'error';
    } elseif ($title === '') {
        $itemMessage = t('clm.messages.title_required');
        $itemMessageType = 'error';
    } elseif (is_array($coverImage) && ($coverImage['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK && ($coverImage['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $itemMessage = uploadErrorMessage((int) $coverImage['error']);
        $itemMessageType = 'error';
    } else {
        $hasCoverImage = is_array($coverImage) && ($coverImage['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

        $postFields = [
            'list_id' => $listId,
            'title' => $title,
        ];

        if ($hasCoverImage) {
            $postFields['cover_image'] = new CURLFile(
                $coverImage['tmp_name'],
                $coverImage['type'] ?: 'application/octet-stream',
                $coverImage['name']
            );
        }

        if ($originalTitle !== '') {
            $postFields['original_title'] = $originalTitle;
        }
        if ($firstReleaseYear !== '') {
            $postFields['first_release_year'] = $firstReleaseYear;
        }
        if ($imdbId !== '') {
            $postFields['imdb_id'] = $imdbId;
        }
        if ($tmdbId !== '') {
            $postFields['tmdb_id'] = $tmdbId;
        }
        if ($tvdbId !== '') {
            $postFields['tvdb_id'] = $tvdbId;
        }
        if ($season !== '') {
            $postFields['season'] = $season;
        }

        [$rawResponse, $curlError, $httpCode] = apiRequest($listItemsEndpoint, $postFields);

        if ($rawResponse === false) {
            $itemMessage = t('clm.messages.api_unreachable', $curlError);
            $itemMessageType = 'error';
        } else {
            $decoded = json_decode($rawResponse, true);

            if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded)) {
                $itemResponseData = $decoded;
                $itemMessage = t('clm.messages.item_added');
                $itemMessageType = 'success';
            } else {
                $itemMessage = is_array($decoded)
                    ? (string) ($decoded['detail'] ?? t('clm.messages.api_unknown_error'))
                    : t('clm.messages.api_invalid_response');
                $itemMessageType = 'error';
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
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
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
    }

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

    @media (max-width: 480px){
      .kv{ grid-template-columns: 1fr; }
      .k{ margin-top:6px; }
    }

    /* --- TMDB search: dark-theme Bootstrap modal overrides --- */
    .title-row{ display:flex; gap:10px; align-items:flex-end; }
    .title-row label{ flex:1; }
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
      <button type="button" class="tab-btn <?= $activeTab === 'add' ? 'active' : '' ?>" data-tab="add"><?= h(t('clm.tabs.add')) ?></button>
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

    <!-- Tab 2: add an item to an existing list -->
    <div class="tab-panel <?= $activeTab === 'add' ? 'active' : '' ?>" id="tab-add">
      <h2><?= h(t('clm.add_tab.heading')) ?></h2>
      <p class="lead"><?= h(t('clm.add_tab.lead')) ?></p>

      <?php if ($itemMessage !== null): ?>
        <div class="notice <?= h($itemMessageType) ?>"><?= h($itemMessage) ?></div>
      <?php endif; ?>

      <?php if ($listsFetchError !== null): ?>
        <div class="notice error"><?= h($listsFetchError) ?></div>
      <?php elseif (empty($availableLists)): ?>
        <div class="empty-hint"><?= h(t('clm.add_tab.empty_hint')) ?></div>
      <?php else: ?>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="form" value="add_item">

          <label>
            <?= h(t('clm.add_tab.list_label')) ?>
            <select name="list_id" required>
              <option value="" disabled <?= empty($_POST['list_id']) ? 'selected' : '' ?>><?= h(t('clm.add_tab.list_placeholder')) ?></option>
              <?php foreach ($availableLists as $list): ?>
                <option value="<?= h((string) ($list['list_id'] ?? '')) ?>"
                  <?= (($_POST['list_id'] ?? '') === ($list['list_id'] ?? '')) ? 'selected' : '' ?>>
                  <?= h((string) ($list['list_name'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>

          <div class="title-row">
            <label>
              <?= h(t('clm.add_tab.title_label')) ?>
              <input type="text" id="title" name="title" placeholder="<?= h(t('clm.add_tab.title_placeholder')) ?>" value="<?= h($_POST['title'] ?? '') ?>" required>
            </label>
            <button type="button" class="btn-tmdb" data-bs-toggle="modal" data-bs-target="#searchModal"><?= h(t('clm.add_tab.btn_tmdb')) ?></button>
            <button type="button" class="btn-tvdb" data-bs-toggle="modal" data-bs-target="#tvdbSearchModal"><?= h(t('clm.add_tab.btn_tvdb')) ?></button>
          </div>

          <label>
            <?= h(t('clm.add_tab.original_title_label')) ?>
            <input type="text" id="original_title" name="original_title" placeholder="<?= h(t('clm.add_tab.title_placeholder')) ?>" value="<?= h($_POST['original_title'] ?? '') ?>">
          </label>

          <label>
            <?= h(t('clm.add_tab.year_label')) ?>
            <input
              type="number"
              id="first_release_year"
              name="first_release_year"
              inputmode="numeric"
              min="1888"
              max="2100"
              placeholder="<?= h(t('clm.add_tab.year_placeholder')) ?>"
              value="<?= h($_POST['first_release_year'] ?? '') ?>"
            >
          </label>

          <fieldset>
            <legend><?= h(t('clm.add_tab.external_ids_legend')) ?></legend>

            <label>
              <?= h(t('clm.add_tab.imdb_label')) ?>
              <input type="text" id="imdb_id" name="imdb_id" placeholder="tt1160419" value="<?= h($_POST['imdb_id'] ?? '') ?>">
            </label>

            <label>
              <?= h(t('clm.add_tab.tmdb_label')) ?>
              <input type="text" id="tmdb_id" name="tmdb_id" placeholder="438631" value="<?= h($_POST['tmdb_id'] ?? '') ?>">
            </label>

            <label>
              <?= h(t('clm.add_tab.tvdb_label')) ?>
              <input type="text" id="tvdb_id" name="tvdb_id" placeholder="81189" value="<?= h($_POST['tvdb_id'] ?? '') ?>">
            </label>
          </fieldset>

          <label>
            <?= h(t('clm.add_tab.season_label')) ?>
            <input type="text" name="season" placeholder="<?= h(t('clm.add_tab.season_placeholder')) ?>" value="<?= h($_POST['season'] ?? '') ?>">
          </label>
          <div class="hint"><?= h(t('clm.add_tab.season_hint')) ?></div>

          <label>
            <?= h(t('clm.add_tab.cover_label')) ?>
            <input type="file" name="cover_image" accept="image/*" capture="environment">
          </label>
          <div class="hint"><?= h(t('clm.add_tab.cover_hint')) ?></div>

          <img id="preview" class="preview" alt="<?= h(t('clm.add_tab.title_label')) ?>">

          <button type="submit"><?= h(t('clm.add_tab.submit')) ?></button>
        </form>
      <?php endif; ?>

      <?php if (is_array($itemResponseData)): ?>
        <div class="panel">
          <h4><?= h(t('clm.add_tab.result_heading')) ?></h4>
          <div class="kv">
            <div class="k"><?= h(t('clm.add_tab.result_list')) ?></div>
            <div class="v"><?= h((string) ($itemResponseData['stored_in'] ?? '')) ?></div>

            <div class="k"><?= h(t('clm.add_tab.result_title')) ?></div>
            <div class="v"><?= h((string) ($itemResponseData['title'] ?? '')) ?></div>

            <div class="k"><?= h(t('clm.add_tab.result_item_id')) ?></div>
            <div class="v"><?= h((string) ($itemResponseData['list_item_id'] ?? '')) ?></div>

            <div class="k"><?= h(t('clm.add_tab.result_original_title')) ?></div>
            <div class="v"><?= h((string) ($itemResponseData['original_title'] ?? '')) ?></div>

            <div class="k"><?= h(t('clm.add_tab.result_year')) ?></div>
            <div class="v"><?= h((string) ($itemResponseData['first_release_year'] ?? '')) ?></div>

            <div class="k"><?= h(t('clm.add_tab.result_imdb')) ?></div>
            <div class="v"><?= h((string) ($itemResponseData['imdb_id'] ?? '')) ?></div>

            <div class="k"><?= h(t('clm.add_tab.result_tmdb')) ?></div>
            <div class="v"><?= h((string) ($itemResponseData['tmdb_id'] ?? '')) ?></div>

            <div class="k"><?= h(t('clm.add_tab.result_tvdb')) ?></div>
            <div class="v"><?= h((string) ($itemResponseData['tvdb_id'] ?? '')) ?></div>

            <div class="k"><?= h(t('clm.add_tab.result_season')) ?></div>
            <div class="v"><?= h((string) ($itemResponseData['season'] ?? '')) ?></div>

            <div class="k"><?= h(t('clm.add_tab.result_cover')) ?></div>
            <div class="v">
              <?php if (!empty($itemResponseData['cover_image'])): ?>
                <a href="<?= h((string) $itemResponseData['cover_image']) ?>" target="_blank" rel="noopener noreferrer">
                  <?= h((string) $itemResponseData['cover_image']) ?>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
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
</script>
<script>
  // --- Tabs ---
  const tabButtons = document.querySelectorAll('.tab-btn');
  const tabPanels = {
    create: document.getElementById('tab-create'),
    add: document.getElementById('tab-add'),
  };

  tabButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = btn.dataset.tab;

      tabButtons.forEach((b) => b.classList.toggle('active', b === btn));
      Object.entries(tabPanels).forEach(([key, panel]) => {
        panel?.classList.toggle('active', key === target);
      });
    });
  });

  // --- Cover image preview (add item tab) ---
  const fileInput = document.querySelector('input[name="cover_image"]');
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
</body>
</html>
