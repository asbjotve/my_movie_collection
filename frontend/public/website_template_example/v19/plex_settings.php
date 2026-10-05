<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';
require_once __DIR__ . '/lang.php';

// Hard gate: hele siden krever innlogging - samme mønster som
// admin_tilganger.php/2fa_setup.php. Selve API-kallene er i tillegg
// beskyttet av require_role("admin") i backend (se plex_settings_route.py),
// så selv om denne PHP-siden en dag åpnes for flere roller enn admin,
// vil lagring/test fortsatt kreve admin-rollen server-side.
$username = require_login();

// Enkel sprintf-wrapper for norsk/engelsk strenger med %s-plassholdere i
// PHP-delen av siden (JS-siden har sin egen wteFormat() i andre filer -
// denne gjelder kun her siden plex_settings.php ikke har noe annet JS).
function wteFormatServer(string $template, string ...$args): string
{
    return vsprintf($template, $args);
}

$error = null;
$saved = false;
$testResult = null; // ['ok' => bool, 'message' => string] eller null (ikke testet ennå denne requesten)

$csrfOk = $_SERVER['REQUEST_METHOD'] !== 'POST' || csrf_verify($_POST['csrf_token'] ?? null);
if (!$csrfOk) {
    $error = t('wte.login.csrf_error');
}

if ($csrfOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['base_url'])) {
    $requestedBaseUrl = trim((string)$_POST['base_url']);
    $requestedToken = trim((string)($_POST['token'] ?? ''));

    $requestedServerIdentifier = trim((string)($_POST['server_identifier'] ?? ''));
    $requestedVerifySsl = isset($_POST['verify_ssl']);

    [$httpCode, $data] = update_plex_settings(
        $requestedBaseUrl,
        $requestedToken !== '' ? $requestedToken : null,
        $requestedServerIdentifier !== '' ? $requestedServerIdentifier : null,
        $requestedVerifySsl
    );
    if ($httpCode === 200) {
        $saved = true;
    } else {
        $error = $data['detail'] ?? $data['error'] ?? t('wte.plex_settings.save_failed');
    }
}

if ($csrfOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_connection'])) {
    [$httpCode, $data] = test_plex_connection_setting();
    if ($httpCode === 200) {
        $testResult = ['ok' => true, 'message' => wteFormatServer(t('wte.plex_settings.test_success'), (string)($data['version'] ?? '?'))];
    } else {
        $testResult = ['ok' => false, 'message' => t('wte.plex_settings.test_failed_prefix') . ($data['detail'] ?? $data['error'] ?? '?')];
    }
}

$librariesSaved = false;

if ($csrfOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_libraries'])) {
    $requestedLibraryKeys = array_map('strval', (array)($_POST['library_keys'] ?? []));
    [$httpCode, $data] = update_plex_library_selection($requestedLibraryKeys);
    if ($httpCode === 200) {
        $librariesSaved = true;
    } else {
        $error = $data['detail'] ?? $data['error'] ?? t('wte.plex_settings.libraries_save_failed');
    }
}

if ($csrfOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_import'])) {
    [$httpCode, $data] = run_plex_import();
    if ($httpCode === 409) {
        $error = t('wte.plex_settings.import_already_running');
    } elseif ($httpCode !== 200) {
        $error = $data['detail'] ?? $data['error'] ?? t('wte.plex_settings.import_failed');
    }
    // 200 ("status": "started") trenger ingen egen melding her -
    // statusboksen under henter fersk status rett etterpå og viser
    // "running" automatisk.
}

// Status for pågående/sist kjørte import - hentes på hver sideoppfrisking
// (inkludert selve auto-refreshen mens den kjører, se <meta refresh> i
// <head>) slik at importResult alltid viser ferskest mulig tilstand.
[, $importStatus] = fetch_plex_import_status();
$importStatus = $importStatus ?? ['status' => 'idle', 'progress' => null, 'summary' => null, 'error' => null];


// Gjeldende lagrede Plex-innstillinger, hentet på nytt etter en
// eventuell lagring over, slik at siden alltid viser ferskest mulig
// tilstand (samme mønster som admin_tilganger.php).
$currentPlexSettings = fetch_plex_settings() ?? ['base_url' => null, 'token_set' => false, 'token_last4' => null, 'server_identifier' => null, 'verify_ssl' => true];

// Bibliotek-listen hentes kun hvis tilkoblingen faktisk er konfigurert
// (ellers vil GET /settings/plex/libraries bare feile med 400) - unngår
// et unødvendig, garantert-mislykket kall ved første gangs besøk.
$plexLibraries = [];
if (!empty($currentPlexSettings['base_url']) && !empty($currentPlexSettings['token_set'])) {
    [$librariesHttpCode, $librariesData] = fetch_plex_libraries();
    if ($librariesHttpCode === 200) {
        $plexLibraries = $librariesData['libraries'] ?? [];
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars($GLOBALS['__wte_lang']) ?>">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars(t('wte.plex_settings.meta_title')) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($importStatus['status'] === 'running'): ?>
<!-- Importen pågår i en bakgrunnstråd server-side (se plex_import_route.py) -
     enkel meta-refresh her er den letteste måten å vise fremdrift på uten
     å innføre JS/AJAX-polling i en ellers ren server-rendret PHP-side. -->
<meta http-equiv="refresh" content="4">
<?php endif; ?>
<?= csrf_meta_tag() ?>
<style>
  :root{
    --bg:#0f1115; --panel:#161922; --border:#262b38; --text:#e7e9ee;
    --muted:#9399ab; --accent:#5b8def; --danger:#e5484d; --success:#3ecf8e;
  }
  *{ box-sizing:border-box; }
  body{
    margin:0; min-height:100vh; background:var(--bg); color:var(--text);
    font-family:system-ui, sans-serif; padding:40px 20px;
  }
  .wrap{ max-width:480px; margin:0 auto; }
  .card{
    background:var(--panel); border:1px solid var(--border); border-radius:12px;
    padding:28px; margin-bottom:20px;
  }
  h1{ font-size:20px; margin:0 0 6px; }
  .backLink{ color:var(--muted); font-size:13px; text-decoration:none; }
  .subtitle{ color:var(--muted); font-size:13px; margin:4px 0 18px; }
  label{ display:block; font-size:13px; color:var(--muted); margin:14px 0 6px; }
  input[type="text"], input[type="password"]{
    width:100%; padding:10px; border-radius:7px; background:var(--bg);
    color:var(--text); border:1px solid var(--border); font-size:14px;
  }
  .fieldHint{ font-size:12px; color:var(--muted); margin-top:4px; }
  .checkboxRow{ display:flex; align-items:flex-start; gap:8px; margin:16px 0 6px; }
  .checkboxRow input[type="checkbox"]{ margin-top:3px; }
  .checkboxRow label{ margin:0; font-size:13px; color:var(--text); }
  button{
    padding:10px 16px; border-radius:7px; border:none; font-size:14px; font-weight:600;
    cursor:pointer;
  }
  .btnPrimary{ background:var(--accent); color:#fff; width:100%; margin-top:20px; }
  .btnSecondary{ background:#2a2f3d; color:var(--text); width:100%; margin-top:12px; }
  .errorBox{
    background:rgba(229,72,77,.12); border:1px solid rgba(229,72,77,.4); color:#ff9a9d;
    padding:10px 12px; border-radius:7px; font-size:13px; margin-top:14px;
  }
  .successBox{
    background:rgba(62,207,142,.12); border:1px solid rgba(62,207,142,.4); color:var(--success);
    padding:10px 12px; border-radius:7px; font-size:13px; margin-top:14px;
  }
  .libraryRow{ display:flex; align-items:center; gap:8px; padding:8px 0; border-bottom:1px solid var(--border); }
  .libraryRow:last-child{ border-bottom:none; }
  .libraryRow label{ margin:0; font-size:14px; color:var(--text); }
  .libraryType{ color:var(--muted); font-size:12px; }
  .importSummary{ font-size:13px; margin-top:14px; }
  .importSummary ul{ margin:6px 0 0; padding-left:20px; }
</style>
</head>
<body>
<div class="wrap">
  <p><a href="index.php" class="backLink"><?= htmlspecialchars(t('wte.plex_settings.back_link')) ?></a></p>

  <div class="card">
    <h1><?= htmlspecialchars(t('wte.plex_settings.heading')) ?></h1>
    <p class="subtitle"><?= htmlspecialchars(t('wte.plex_settings.subtitle')) ?></p>

    <?php if ($error): ?>
      <div class="errorBox"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($saved): ?>
      <div class="successBox"><?= htmlspecialchars(t('wte.plex_settings.saved_notice')) ?></div>
    <?php endif; ?>
    <?php if ($testResult !== null): ?>
      <div class="<?= $testResult['ok'] ? 'successBox' : 'errorBox' ?>"><?= htmlspecialchars($testResult['message']) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>

      <label for="base_url"><?= htmlspecialchars(t('wte.plex_settings.base_url_label')) ?></label>
      <input
        type="text"
        id="base_url"
        name="base_url"
        value="<?= htmlspecialchars($currentPlexSettings['base_url'] ?? '') ?>"
        placeholder="<?= htmlspecialchars(t('wte.plex_settings.base_url_hint')) ?>"
      >

      <label for="token"><?= htmlspecialchars(t('wte.plex_settings.token_label')) ?></label>
      <input
        type="password"
        id="token"
        name="token"
        autocomplete="off"
        placeholder="<?= $currentPlexSettings['token_set'] ? '••••••••' : '' ?>"
      >
      <div class="fieldHint">
        <?php if ($currentPlexSettings['token_set']): ?>
          <?= htmlspecialchars(wteFormatServer(t('wte.plex_settings.token_hint_set'), (string)$currentPlexSettings['token_last4'])) ?>
        <?php else: ?>
          <?= htmlspecialchars(t('wte.plex_settings.token_hint_unset')) ?>
        <?php endif; ?>
      </div>

      <label for="server_identifier"><?= htmlspecialchars(t('wte.plex_settings.server_identifier_label')) ?></label>
      <input
        type="text"
        id="server_identifier"
        name="server_identifier"
        value="<?= htmlspecialchars($currentPlexSettings['server_identifier'] ?? '') ?>"
        placeholder="<?= htmlspecialchars(t('wte.plex_settings.server_identifier_hint')) ?>"
      >
      <div class="fieldHint"><?= htmlspecialchars(t('wte.plex_settings.server_identifier_desc')) ?></div>

      <div class="checkboxRow">
        <input
          type="checkbox"
          id="verify_ssl"
          name="verify_ssl"
          value="1"
          <?= ($currentPlexSettings['verify_ssl'] ?? true) ? 'checked' : '' ?>
        >
        <label for="verify_ssl"><?= htmlspecialchars(t('wte.plex_settings.verify_ssl_label')) ?></label>
      </div>
      <div class="fieldHint"><?= htmlspecialchars(t('wte.plex_settings.verify_ssl_desc')) ?></div>

      <button type="submit" class="btnPrimary"><?= htmlspecialchars(t('wte.plex_settings.save_btn')) ?></button>
    </form>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="test_connection" value="1">
      <button type="submit" class="btnSecondary"><?= htmlspecialchars(t('wte.plex_settings.test_btn')) ?></button>
    </form>
  </div>

  <?php if (!empty($currentPlexSettings['base_url']) && !empty($currentPlexSettings['token_set'])): ?>
  <div class="card">
    <h1><?= htmlspecialchars(t('wte.plex_settings.libraries_heading')) ?></h1>
    <p class="subtitle"><?= htmlspecialchars(t('wte.plex_settings.libraries_subtitle')) ?></p>

    <?php if ($librariesSaved): ?>
      <div class="successBox"><?= htmlspecialchars(t('wte.plex_settings.libraries_saved_notice')) ?></div>
    <?php endif; ?>

    <?php if (empty($plexLibraries)): ?>
      <div class="fieldHint"><?= htmlspecialchars(t('wte.plex_settings.libraries_empty')) ?></div>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="save_libraries" value="1">
        <?php foreach ($plexLibraries as $library): ?>
          <div class="libraryRow">
            <input
              type="checkbox"
              id="lib_<?= htmlspecialchars((string)$library['key']) ?>"
              name="library_keys[]"
              value="<?= htmlspecialchars((string)$library['key']) ?>"
              <?= !empty($library['selected']) ? 'checked' : '' ?>
            >
            <label for="lib_<?= htmlspecialchars((string)$library['key']) ?>">
              <?= htmlspecialchars((string)($library['title'] ?? '?')) ?>
              <span class="libraryType">(<?= htmlspecialchars((string)($library['type'] ?? '?')) ?>)</span>
            </label>
          </div>
        <?php endforeach; ?>
        <button type="submit" class="btnPrimary"><?= htmlspecialchars(t('wte.plex_settings.libraries_save_btn')) ?></button>
      </form>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="run_import" value="1">
      <button type="submit" class="btnSecondary" <?= $importStatus['status'] === 'running' ? 'disabled' : '' ?>><?= htmlspecialchars(t('wte.plex_settings.import_btn')) ?></button>
    </form>

    <?php if ($importStatus['status'] === 'running'): ?>
      <div class="successBox importSummary">
        <div><?= htmlspecialchars(t('wte.plex_settings.import_running')) ?></div>
        <?php if (!empty($importStatus['progress'])): ?>
          <div><?= htmlspecialchars(wteFormatServer(
            t('wte.plex_settings.import_progress'),
            (string)($importStatus['progress']['library'] ?? '?'),
            (string)($importStatus['progress']['processed'] ?? 0),
            (string)($importStatus['progress']['total'] ?? 0)
          )) ?></div>
        <?php endif; ?>
      </div>
    <?php elseif ($importStatus['status'] === 'failed'): ?>
      <div class="errorBox importSummary">
        <?= htmlspecialchars(t('wte.plex_settings.import_failed_prefix')) ?> <?= htmlspecialchars((string)($importStatus['error'] ?? '?')) ?>
      </div>
    <?php elseif ($importStatus['status'] === 'completed' && !empty($importStatus['summary'])): ?>
      <?php $importSummary = $importStatus['summary']; ?>
      <div class="successBox importSummary">
        <div><?= htmlspecialchars(wteFormatServer(
          t('wte.plex_settings.import_summary'),
          (string)$importSummary['movies_created'],
          (string)$importSummary['movies_matched']
        )) ?></div>
        <?php if (!empty($importSummary['movies_pruned'])): ?>
          <div><?= htmlspecialchars(wteFormatServer(
            t('wte.plex_settings.import_pruned'),
            (string)$importSummary['movies_pruned']
          )) ?></div>
        <?php endif; ?>
        <?php if (!empty($importSummary['skipped_non_movie_libraries'])): ?>
          <div><?= htmlspecialchars(t('wte.plex_settings.import_skipped_libraries')) ?> <?= htmlspecialchars(implode(', ', $importSummary['skipped_non_movie_libraries'])) ?></div>
        <?php endif; ?>
        <?php if (!empty($importSummary['errors'])): ?>
          <div><?= htmlspecialchars(t('wte.plex_settings.import_errors_heading')) ?></div>
          <ul>
            <?php foreach ($importSummary['errors'] as $importError): ?>
              <li><?= htmlspecialchars((string)$importError) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
