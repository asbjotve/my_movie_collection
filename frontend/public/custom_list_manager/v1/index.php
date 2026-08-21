<?php
declare(strict_types=1);

$listsEndpoint = 'http://172.19.0.1:9500/lists';
$listItemsEndpoint = 'http://172.19.0.1:9500/lists/items';

$createMessage = null;
$createMessageType = 'info';
$createdList = null;

$itemMessage = null;
$itemMessageType = 'info';
$itemResponseData = null;

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function uploadErrorMessage(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Bildefilen er for stor.',
        UPLOAD_ERR_PARTIAL => 'Opplastingen ble avbrutt før bildet var ferdig sendt.',
        UPLOAD_ERR_NO_FILE => 'Du må velge et bilde før du sender inn.',
        UPLOAD_ERR_NO_TMP_DIR => 'Serveren mangler midlertidig mappe for opplasting.',
        UPLOAD_ERR_CANT_WRITE => 'Serveren klarte ikke å skrive den opplastede filen.',
        UPLOAD_ERR_EXTENSION => 'Opplastingen ble stoppet av en serverutvidelse.',
        default => 'Ukjent feil under bildeopplasting.',
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
    $listName = trim((string) ($_POST['list_name'] ?? ''));

    if ($listName === '') {
        $createMessage = 'Du må skrive inn et listenavn.';
        $createMessageType = 'error';
    } else {
        [$rawResponse, $curlError, $httpCode] = apiRequest($listsEndpoint, ['list_name' => $listName]);

        if ($rawResponse === false) {
            $createMessage = 'Kunne ikke kontakte API-et: ' . $curlError;
            $createMessageType = 'error';
        } else {
            $decoded = json_decode($rawResponse, true);

            if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded)) {
                $createdList = $decoded;
                $createMessage = 'Listen "' . ($decoded['list_name'] ?? $listName) . '" ble opprettet.';
                $createMessageType = 'success';
            } else {
                $createMessage = is_array($decoded)
                    ? (string) ($decoded['detail'] ?? 'Ukjent feil fra API-et.')
                    : 'API-et svarte med en ugyldig respons.';
                $createMessageType = 'error';
            }
        }
    }
}

// Handle "add item to list" form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'add_item') {
    $listId = trim((string) ($_POST['list_id'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $originalTitle = trim((string) ($_POST['original_title'] ?? ''));
    $firstReleaseYear = trim((string) ($_POST['first_release_year'] ?? ''));
    $imdbId = trim((string) ($_POST['imdb_id'] ?? ''));
    $tmdbId = trim((string) ($_POST['tmdb_id'] ?? ''));
    $tvdbId = trim((string) ($_POST['tvdb_id'] ?? ''));
    $coverImage = $_FILES['cover_image'] ?? null;

    if ($listId === '') {
        $itemMessage = 'Du må velge en liste.';
        $itemMessageType = 'error';
    } elseif ($title === '') {
        $itemMessage = 'Du må skrive inn en tittel.';
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

        [$rawResponse, $curlError, $httpCode] = apiRequest($listItemsEndpoint, $postFields);

        if ($rawResponse === false) {
            $itemMessage = 'Kunne ikke kontakte API-et: ' . $curlError;
            $itemMessageType = 'error';
        } else {
            $decoded = json_decode($rawResponse, true);

            if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded)) {
                $itemResponseData = $decoded;
                $itemMessage = 'Elementet ble lagt til i listen.';
                $itemMessageType = 'success';
            } else {
                $itemMessage = is_array($decoded)
                    ? (string) ($decoded['detail'] ?? 'Ukjent feil fra API-et.')
                    : 'API-et svarte med en ugyldig respons.';
                $itemMessageType = 'error';
            }
        }
    }
}

// Fetch existing lists for the dropdown (always excludes "Wishlist" — handled separately).
$availableLists = [];
$listsFetchError = null;

$ch = curl_init($listsEndpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$listsResponse = curl_exec($ch);
$listsHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$listsCurlError = curl_error($ch);
curl_close($ch);

if ($listsResponse === false || $listsCurlError) {
    $listsFetchError = 'Kunne ikke hente listene: ' . $listsCurlError;
} elseif ($listsHttpCode !== 200) {
    $listsFetchError = 'API-et returnerte HTTP ' . $listsHttpCode . ' ved henting av lister.';
} else {
    $decodedLists = json_decode($listsResponse, true);
    if (is_array($decodedLists)) {
        $availableLists = $decodedLists;
    }
}
?>
<!doctype html>
<html lang="no">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Egendefinerte lister</title>
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

    main{
      padding:24px 18px 40px;
      display:grid;
      gap:20px;
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

    button{
      appearance:none; border:0;
      background: var(--accent);
      color: #0b1020;
      font: inherit; font-weight:700;
      border-radius:12px;
      padding:14px 16px;
      cursor:pointer;
    }
    button:active{ background: var(--accent-dark); }
    button:disabled{ opacity:.5; cursor:not-allowed; }

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
  </style>
</head>
<body>
<header>
  <h1>Egendefinerte lister</h1>
  <div class="tag">Opprett liste &amp; legg til element</div>
</header>

<main>

  <!-- Part 1: create a new list -->
  <section class="card">
    <h2>Opprett ny liste</h2>
    <p class="lead">Gi listen et navn. Dette er en egen liste – ikke ønskelisten.</p>

    <?php if ($createMessage !== null): ?>
      <div class="notice <?= h($createMessageType) ?>"><?= h($createMessage) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="form" value="create_list">
      <label>
        Listenavn
        <input type="text" name="list_name" placeholder="F.eks. Julefilmer" required>
      </label>
      <button type="submit">Opprett liste</button>
    </form>
  </section>

  <!-- Part 2: add an item to an existing list -->
  <section class="card">
    <h2>Legg til element i liste</h2>
    <p class="lead">Velg hvilken liste elementet skal legges til i, fyll ut informasjon og last opp et coverbilde.</p>

    <?php if ($itemMessage !== null): ?>
      <div class="notice <?= h($itemMessageType) ?>"><?= h($itemMessageType === 'error' ? $itemMessage : $itemMessage) ?></div>
    <?php endif; ?>

    <?php if ($listsFetchError !== null): ?>
      <div class="notice error"><?= h($listsFetchError) ?></div>
    <?php elseif (empty($availableLists)): ?>
      <div class="empty-hint">Det finnes ingen lister enda. Opprett en liste over for å komme i gang.</div>
    <?php else: ?>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="form" value="add_item">

        <label>
          Liste
          <select name="list_id" required>
            <option value="" disabled selected>Velg liste…</option>
            <?php foreach ($availableLists as $list): ?>
              <option value="<?= h((string) ($list['list_id'] ?? '')) ?>"
                <?= (($_POST['list_id'] ?? '') === ($list['list_id'] ?? '')) ? 'selected' : '' ?>>
                <?= h((string) ($list['list_name'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>
          Tittel
          <input type="text" name="title" placeholder="F.eks. Dune" value="<?= h($_POST['title'] ?? '') ?>" required>
        </label>

        <label>
          Original tittel
          <input type="text" name="original_title" placeholder="F.eks. Dune" value="<?= h($_POST['original_title'] ?? '') ?>">
        </label>

        <label>
          Utgivelsesår
          <input
            type="number"
            name="first_release_year"
            inputmode="numeric"
            min="1888"
            max="2100"
            placeholder="F.eks. 2021"
            value="<?= h($_POST['first_release_year'] ?? '') ?>"
          >
        </label>

        <fieldset>
          <legend>Eksterne ID-er (valgfritt)</legend>

          <label>
            IMDb ID
            <input type="text" name="imdb_id" placeholder="tt1160419" value="<?= h($_POST['imdb_id'] ?? '') ?>">
          </label>

          <label>
            TMDB ID
            <input type="text" name="tmdb_id" placeholder="438631" value="<?= h($_POST['tmdb_id'] ?? '') ?>">
          </label>

          <label>
            TVDB ID
            <input type="text" name="tvdb_id" placeholder="81189" value="<?= h($_POST['tvdb_id'] ?? '') ?>">
          </label>
        </fieldset>

        <label>
          Coverbilde (valgfritt)
          <input type="file" name="cover_image" accept="image/*" capture="environment">
        </label>
        <div class="hint">Støtter JPEG, PNG, WEBP, HEIC og HEIF. Du kan legge til et coverbilde senere hvis du vil.</div>

        <img id="preview" class="preview" alt="Forhåndsvisning av valgt bilde">

        <button type="submit">Legg til i listen</button>
      </form>
    <?php endif; ?>

    <?php if (is_array($itemResponseData)): ?>
      <div class="panel">
        <h4>Lagt til i listen</h4>
        <div class="kv">
          <div class="k">Liste</div>
          <div class="v"><?= h((string) ($itemResponseData['stored_in'] ?? '')) ?></div>

          <div class="k">Tittel</div>
          <div class="v"><?= h((string) ($itemResponseData['title'] ?? '')) ?></div>

          <div class="k">List item ID</div>
          <div class="v"><?= h((string) ($itemResponseData['list_item_id'] ?? '')) ?></div>

          <div class="k">Original tittel</div>
          <div class="v"><?= h((string) ($itemResponseData['original_title'] ?? '')) ?></div>

          <div class="k">Utgivelsesår</div>
          <div class="v"><?= h((string) ($itemResponseData['first_release_year'] ?? '')) ?></div>

          <div class="k">IMDb ID</div>
          <div class="v"><?= h((string) ($itemResponseData['imdb_id'] ?? '')) ?></div>

          <div class="k">TMDB ID</div>
          <div class="v"><?= h((string) ($itemResponseData['tmdb_id'] ?? '')) ?></div>

          <div class="k">TVDB ID</div>
          <div class="v"><?= h((string) ($itemResponseData['tvdb_id'] ?? '')) ?></div>

          <div class="k">Cover</div>
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
  </section>

</main>

<script>
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
</body>
</html>
