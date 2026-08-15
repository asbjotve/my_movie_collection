<?php
declare(strict_types=1);

$apiEndpoint = 'http://172.19.0.1:9500/wishlist/movies';
$message = null;
$messageType = 'info';
$responseData = null;

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function uploadErrorMessage(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Bildefilen er for stor. Bruk et bilde under 10 MB.',
        UPLOAD_ERR_PARTIAL => 'Opplastingen ble avbrutt før bildet var ferdig sendt.',
        UPLOAD_ERR_NO_FILE => 'Du må velge et bilde før du sender inn.',
        UPLOAD_ERR_NO_TMP_DIR => 'Serveren mangler midlertidig mappe for opplasting.',
        UPLOAD_ERR_CANT_WRITE => 'Serveren klarte ikke å skrive den opplastede filen.',
        UPLOAD_ERR_EXTENSION => 'Opplastingen ble stoppet av en serverutvidelse.',
        default => 'Ukjent feil under bildeopplasting.',
    };
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $originalTitle = trim((string) ($_POST['original_title'] ?? ''));
    $firstReleaseYear = trim((string) ($_POST['first_release_year'] ?? ''));
    $imdbId = trim((string) ($_POST['imdb_id'] ?? ''));
    $tmdbId = trim((string) ($_POST['tmdb_id'] ?? ''));
    $tvdbId = trim((string) ($_POST['tvdb_id'] ?? ''));
    $coverImage = $_FILES['cover_image'] ?? null;

    if ($title === '') {
        $message = 'Du må skrive inn en tittel.';
        $messageType = 'error';
    } elseif (!is_array($coverImage) || ($coverImage['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $message = uploadErrorMessage((int) ($coverImage['error'] ?? UPLOAD_ERR_NO_FILE));
        $messageType = 'error';
    } else {
        $postFields = [
            'title' => $title,
            'cover_image' => new CURLFile(
                $coverImage['tmp_name'],
                $coverImage['type'] ?: 'application/octet-stream',
                $coverImage['name']
            ),
        ];

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

        $ch = curl_init($apiEndpoint);
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

        if ($rawResponse === false) {
            $message = 'Kunne ikke kontakte API-et: ' . $curlError;
            $messageType = 'error';
        } else {
            $decodedResponse = json_decode($rawResponse, true);

            if ($httpCode >= 200 && $httpCode < 300 && is_array($decodedResponse)) {
                $responseData = $decodedResponse;
                $message = 'Filmen ble lagret med coverbilde.';
                $messageType = 'success';
            } else {
                $message = is_array($decodedResponse)
                    ? (string) ($decodedResponse['detail'] ?? $decodedResponse['error'] ?? 'Ukjent feil fra API-et.')
                    : 'API-et svarte med en ugyldig respons.';
                $messageType = 'error';
            }
        }
    }
}
?>
<!doctype html>
<html lang="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Last opp filmcover</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f3f5fb;
            --card: #ffffff;
            --text: #182033;
            --muted: #667089;
            --line: #d7def0;
            --accent: #4254ff;
            --accent-dark: #2f40de;
            --success-bg: #e8f8ee;
            --success-text: #17653c;
            --error-bg: #fdecec;
            --error-text: #932b2b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(180deg, #eef2ff 0%, var(--bg) 100%);
            color: var(--text);
        }

        main {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px 14px 40px;
        }

        .card {
            width: min(100%, 460px);
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 22px;
            padding: 22px 18px;
            box-shadow: 0 18px 40px rgba(34, 49, 86, 0.08);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 1.6rem;
            line-height: 1.2;
        }

        p.lead {
            margin: 0 0 20px;
            color: var(--muted);
            line-height: 1.45;
        }

        form {
            display: grid;
            gap: 14px;
        }

        fieldset {
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 12px 14px 14px;
            display: grid;
            gap: 12px;
        }

        legend {
            font-weight: 700;
            font-size: 0.9rem;
            color: var(--muted);
            padding: 0 6px;
        }

        label {
            display: grid;
            gap: 6px;
            font-weight: 600;
            font-size: 0.95rem;
        }

        input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px 15px;
            font: inherit;
            color: var(--text);
            background: #fff;
        }

        input[type="file"] {
            padding: 12px;
        }

        input:focus {
            outline: 3px solid rgba(66, 84, 255, 0.16);
            border-color: var(--accent);
        }

        button {
            appearance: none;
            border: 0;
            border-radius: 14px;
            background: var(--accent);
            color: #fff;
            font: inherit;
            font-weight: 700;
            padding: 15px 18px;
            cursor: pointer;
        }

        button:active {
            background: var(--accent-dark);
        }

        .hint,
        .result dt {
            color: var(--muted);
        }

        .hint {
            font-size: 0.9rem;
            margin-top: -6px;
        }

        .notice {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 14px;
            font-size: 0.95rem;
        }

        .notice.success {
            background: var(--success-bg);
            color: var(--success-text);
        }

        .notice.error {
            background: var(--error-bg);
            color: var(--error-text);
        }

        .preview {
            display: none;
            width: 100%;
            border-radius: 18px;
            border: 1px solid var(--line);
            object-fit: cover;
            max-height: 260px;
        }

        .result {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
        }

        .result dl {
            margin: 0;
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 10px 12px;
        }

        .result dd,
        .result dt {
            margin: 0;
            word-break: break-word;
        }

        .result a {
            color: var(--accent-dark);
        }

        @media (min-width: 640px) {
            .card {
                padding: 26px 24px;
            }
        }
    </style>
</head>
<body>
<main>
    <section class="card">
        <h1>Last opp filmcover</h1>
        <p class="lead">Bruk skjemaet til å legge en film i ønskelisten med bilde. På mobil kan du velge kamera direkte i bildefeltet.</p>

        <?php if ($message !== null): ?>
            <div class="notice <?= h($messageType) ?>"><?= h($message) ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data">
            <label>
                Filmtittel
                <input type="text" name="title" value="<?= h($_POST['title'] ?? '') ?>" required>
            </label>

            <label>
                Original tittel
                <input type="text" name="original_title" value="<?= h($_POST['original_title'] ?? '') ?>">
            </label>

            <label>
                Utgivelsesår
                <input
                    type="number"
                    name="first_release_year"
                    inputmode="numeric"
                    min="1888"
                    max="2100"
                    value="<?= h($_POST['first_release_year'] ?? '') ?>"
                >
            </label>

            <fieldset>
                <legend>Eksterne ID-er (valgfritt)</legend>

                <label>
                    IMDb ID
                    <input type="text" name="imdb_id" placeholder="tt1375666" value="<?= h($_POST['imdb_id'] ?? '') ?>">
                </label>

                <label>
                    TMDB ID
                    <input type="text" name="tmdb_id" placeholder="27205" value="<?= h($_POST['tmdb_id'] ?? '') ?>">
                </label>

                <label>
                    TVDB ID
                    <input type="text" name="tvdb_id" placeholder="81189" value="<?= h($_POST['tvdb_id'] ?? '') ?>">
                </label>
            </fieldset>

            <label>
                Coverbilde
                <input type="file" name="cover_image" accept="image/*" capture="environment" required>
            </label>
            <div class="hint">Støtter JPEG, PNG, WEBP, HEIC og HEIF. Maks 10 MB.</div>

            <img id="preview" class="preview" alt="Forhåndsvisning av valgt bilde">

            <button type="submit">Lagre film med cover</button>
        </form>

        <?php if (is_array($responseData)): ?>
            <div class="result">
                <dl>
                    <dt>Tittel</dt>
                    <dd><?= h((string) ($responseData['title'] ?? '')) ?></dd>

                    <dt>List item ID</dt>
                    <dd><?= h((string) ($responseData['list_item_id'] ?? '')) ?></dd>

                    <dt>IMDb ID</dt>
                    <dd><?= h((string) ($responseData['imdb_id'] ?? '')) ?></dd>

                    <dt>TMDB ID</dt>
                    <dd><?= h((string) ($responseData['tmdb_id'] ?? '')) ?></dd>

                    <dt>TVDB ID</dt>
                    <dd><?= h((string) ($responseData['tvdb_id'] ?? '')) ?></dd>

                    <dt>Cover</dt>
                    <dd>
                        <?php if (!empty($responseData['cover_image'])): ?>
                            <a href="<?= h((string) $responseData['cover_image']) ?>" target="_blank" rel="noopener noreferrer">
                                <?= h((string) $responseData['cover_image']) ?>
                            </a>
                        <?php endif; ?>
                    </dd>
                </dl>
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
