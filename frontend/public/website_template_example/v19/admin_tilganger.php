<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';
require_once __DIR__ . '/lang.php';

// Hard gate: hele siden krever innlogging - samme mønster som
// 2fa_setup.php. En dag med flere roller kan denne strammes ytterligere
// inn til f.eks. require_role("admin") på samme måte som backend
// allerede gjør (se require_role() i backend/app/security.py og bruken
// i PUT /settings/section-access) - i dag er alle innloggede brukere
// admin, så require_login() er tilstrekkelig.
$username = require_login();

$labels = [
    'mine_filmer'    => t('wte.nav.mine_filmer'),
    'onskeliste'     => t('wte.nav.onskeliste'),
    'andre_lister'   => t('wte.nav.andre_lister'),
    'administrering' => t('wte.nav.administrering'),
];

$error = null;
$saved = false;
$langError = null;
$langSaved = false;

$csrfOk = $_SERVER['REQUEST_METHOD'] !== 'POST' || csrf_verify($_POST['csrf_token'] ?? null);
if (!$csrfOk) {
    $error = t('wte.login.csrf_error');
}

if ($csrfOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['section'])) {
    $sections = [];
    foreach (array_keys($labels) as $key) {
        $sections[$key] = isset($_POST['section'][$key]);
    }

    [$httpCode, $data] = update_section_access($sections);
    if ($httpCode === 200) {
        $saved = true;
    } else {
        $error = $data['detail'] ?? $data['error'] ?? t('wte.admin_tilganger.save_failed');
    }
}

if ($csrfOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['default_language'])) {
    $requestedLang = (string)$_POST['default_language'];
    if (in_array($requestedLang, WTE_AVAILABLE_LANGS, true)) {
        [$httpCode, $data] = update_default_language_setting($requestedLang);
        if ($httpCode === 200) {
            $langSaved = true;
        } else {
            $langError = $data['detail'] ?? $data['error'] ?? t('wte.admin_tilganger.lang_save_failed');
        }
    } else {
        $langError = t('wte.admin_tilganger.lang_save_failed');
    }
}

$currencyError = null;
$currencySaved = false;

if ($csrfOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['default_currency'])) {
    $requestedCurrency = strtoupper(trim((string)$_POST['default_currency']));
    if (preg_match('/^[A-Z]{3}$/', $requestedCurrency)) {
        [$httpCode, $data] = update_default_currency_setting($requestedCurrency);
        if ($httpCode === 200) {
            $currencySaved = true;
        } else {
            $currencyError = $data['detail'] ?? $data['error'] ?? t('wte.admin_tilganger.currency_save_failed');
        }
    } else {
        $currencyError = t('wte.admin_tilganger.currency_save_failed');
    }
}

$tmdbLangError = null;
$tmdbLangSaved = false;

if ($csrfOk && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tmdb_data_language'])) {
    $requestedTmdbLang = (string)$_POST['tmdb_data_language'];
    if (in_array($requestedTmdbLang, WTE_AVAILABLE_LANGS, true)) {
        [$httpCode, $data] = update_tmdb_data_language_setting($requestedTmdbLang);
        if ($httpCode === 200) {
            $tmdbLangSaved = true;
        } else {
            $tmdbLangError = $data['detail'] ?? $data['error'] ?? t('wte.admin_tilganger.tmdb_lang_save_failed');
        }
    } else {
        $tmdbLangError = t('wte.admin_tilganger.tmdb_lang_save_failed');
    }
}

// Hent gjeldende status etter en eventuell lagring over, slik at siden
// alltid viser ferskest mulig tilstand (samme mønster som 2fa_setup.php).
$currentAccess = fetch_section_access([
    'mine_filmer'    => false,
    'onskeliste'     => true,
    'andre_lister'   => true,
    'administrering' => true,
]);

// Gjeldende admin-overstyrte default-språk - null betyr "ingen
// overstyring lagret ennå" (da brukes WTE_DEFAULT_LANG i .env, se
// wte_default_lang() i lang.php). Faller tilbake til den samme
// resolveringen som besøkende faktisk får, så select-boksen viser
// riktig forhåndsvalgt verdi selv når ingen overstyring er lagret.
$currentDefaultLang = fetch_default_language_setting() ?? wte_default_lang();

// Gjeldende admin-overstyrte default-valuta - null betyr "ingen
// overstyring lagret ennå" (backend faller da tilbake til NOK, se
// _load_physical_copies() i media_catalog.py).
$currentDefaultCurrency = fetch_default_currency_setting() ?? 'NOK';

// Gjeldende "foretrukket språk ved henting av data fra TMDB" - null
// betyr "ingen overstyring lagret ennå" (backend faller da tilbake til
// "no", se get_tmdb_data_language_preference() i media_catalog.py).
$currentTmdbDataLang = fetch_tmdb_data_language_setting() ?? 'no';
?>
<!doctype html>
<html lang="<?= htmlspecialchars($GLOBALS['__wte_lang']) ?>">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars(t('wte.admin_tilganger.meta_title')) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
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
  .rowItem{
    display:flex; align-items:center; justify-content:space-between;
    padding:12px 0; border-bottom:1px solid var(--border);
  }
  .rowItem:last-of-type{ border-bottom:none; }
  .rowItem span{ font-size:14px; }
  .switch{ position:relative; display:inline-block; width:42px; height:24px; }
  .switch input{ opacity:0; width:0; height:0; }
  .slider{
    position:absolute; cursor:pointer; inset:0; background:#2a2f3d;
    border-radius:24px; transition:.15s;
  }
  .slider:before{
    content:""; position:absolute; height:18px; width:18px; left:3px; bottom:3px;
    background:#fff; border-radius:50%; transition:.15s;
  }
  .switch input:checked + .slider{ background:var(--accent); }
  .switch input:checked + .slider:before{ transform:translateX(18px); }
  button{
    padding:10px 16px; border-radius:7px; border:none; font-size:14px; font-weight:600;
    cursor:pointer;
  }
  .btnPrimary{ background:var(--accent); color:#fff; width:100%; margin-top:20px; }
  .errorBox{
    background:rgba(229,72,77,.12); border:1px solid rgba(229,72,77,.4); color:#ff9a9d;
    padding:10px 12px; border-radius:7px; font-size:13px; margin-top:14px;
  }
  .successBox{
    background:rgba(62,207,142,.12); border:1px solid rgba(62,207,142,.4); color:var(--success);
    padding:10px 12px; border-radius:7px; font-size:13px; margin-top:14px;
  }
  .dangerCard{ border-color:rgba(229,72,77,.4); }
  .btnDanger{ background:var(--danger); color:#fff; width:100%; margin-top:20px; }
  .btnDanger:disabled{ opacity:.5; cursor:not-allowed; }
  .modalOverlay{
    display:none; position:fixed; inset:0; background:rgba(0,0,0,.6);
    align-items:center; justify-content:center; padding:20px; z-index:50;
  }
  .modalOverlay.open{ display:flex; }
  .modalBox{
    background:var(--panel); border:1px solid rgba(229,72,77,.5); border-radius:12px;
    padding:26px; max-width:440px; width:100%;
  }
  .modalBox h2{ margin:0 0 10px; font-size:17px; color:var(--danger); }
  .modalBox ul{ margin:10px 0; padding-left:20px; font-size:13px; color:var(--muted); }
  .modalBox input[type=text]{
    width:100%; padding:10px; border-radius:7px; background:var(--bg); color:var(--text);
    border:1px solid var(--border); font-size:14px; margin-top:10px;
  }
  .modalActions{ display:flex; gap:10px; margin-top:18px; }
  .modalActions button{ flex:1; }
  .btnSecondary{ background:#2a2f3d; color:var(--text); }
  .spinner{ font-size:13px; color:var(--muted); margin-top:10px; }
</style>
</head>
<body>
<div class="wrap">
  <p><a href="index.php" class="backLink"><?= htmlspecialchars(t('wte.admin_tilganger.back_link')) ?></a></p>

  <div class="card">
    <h1><?= htmlspecialchars(t('wte.admin_tilganger.heading')) ?></h1>
    <p class="subtitle">
      <?= sprintf(htmlspecialchars(t('wte.admin_tilganger.subtitle')), '<strong>' . htmlspecialchars($username) . '</strong>') ?>
    </p>

    <?php if ($error): ?>
      <div class="errorBox"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($saved): ?>
      <div class="successBox"><?= htmlspecialchars(t('wte.admin_tilganger.saved_notice')) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <?php foreach ($labels as $key => $label): ?>
        <div class="rowItem">
          <span><?= htmlspecialchars($label) ?></span>
          <label class="switch">
            <input type="checkbox" name="section[<?= htmlspecialchars($key) ?>]" <?= $currentAccess[$key] ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
        </div>
      <?php endforeach; ?>
      <button type="submit" class="btnPrimary"><?= htmlspecialchars(t('wte.admin_tilganger.save_btn')) ?></button>
    </form>
  </div>

  <div class="card">
    <h1><?= htmlspecialchars(t('wte.admin_tilganger.lang_heading')) ?></h1>
    <p class="subtitle"><?= htmlspecialchars(t('wte.admin_tilganger.lang_subtitle')) ?></p>

    <?php if ($langError): ?>
      <div class="errorBox"><?= htmlspecialchars($langError) ?></div>
    <?php endif; ?>
    <?php if ($langSaved): ?>
      <div class="successBox"><?= htmlspecialchars(t('wte.admin_tilganger.lang_saved_notice')) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <select name="default_language" style="width:100%; padding:10px; border-radius:7px; background:var(--bg); color:var(--text); border:1px solid var(--border); font-size:14px;">
        <?php foreach (WTE_AVAILABLE_LANGS as $langCode): ?>
          <option value="<?= htmlspecialchars($langCode) ?>" <?= $currentDefaultLang === $langCode ? 'selected' : '' ?>>
            <?= htmlspecialchars(t('wte.admin_tilganger.lang_' . $langCode)) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btnPrimary"><?= htmlspecialchars(t('wte.admin_tilganger.lang_save_btn')) ?></button>
    </form>
  </div>

  <div class="card">
    <h1><?= htmlspecialchars(t('wte.admin_tilganger.currency_heading')) ?></h1>
    <p class="subtitle"><?= htmlspecialchars(t('wte.admin_tilganger.currency_subtitle')) ?></p>

    <?php if ($currencyError): ?>
      <div class="errorBox"><?= htmlspecialchars($currencyError) ?></div>
    <?php endif; ?>
    <?php if ($currencySaved): ?>
      <div class="successBox"><?= htmlspecialchars(t('wte.admin_tilganger.currency_saved_notice')) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <input
        type="text"
        name="default_currency"
        value="<?= htmlspecialchars($currentDefaultCurrency) ?>"
        maxlength="3"
        pattern="[A-Za-z]{3}"
        style="width:100%; padding:10px; border-radius:7px; background:var(--bg); color:var(--text); border:1px solid var(--border); font-size:14px; text-transform:uppercase;"
      >
      <button type="submit" class="btnPrimary"><?= htmlspecialchars(t('wte.admin_tilganger.currency_save_btn')) ?></button>
    </form>
  </div>

  <div class="card">
    <h1><?= htmlspecialchars(t('wte.admin_tilganger.tmdb_lang_heading')) ?></h1>
    <p class="subtitle"><?= htmlspecialchars(t('wte.admin_tilganger.tmdb_lang_subtitle')) ?></p>

    <?php if ($tmdbLangError): ?>
      <div class="errorBox"><?= htmlspecialchars($tmdbLangError) ?></div>
    <?php endif; ?>
    <?php if ($tmdbLangSaved): ?>
      <div class="successBox"><?= htmlspecialchars(t('wte.admin_tilganger.tmdb_lang_saved_notice')) ?></div>
    <?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <select name="tmdb_data_language" style="width:100%; padding:10px; border-radius:7px; background:var(--bg); color:var(--text); border:1px solid var(--border); font-size:14px;">
        <?php foreach (WTE_AVAILABLE_LANGS as $langCode): ?>
          <option value="<?= htmlspecialchars($langCode) ?>" <?= $currentTmdbDataLang === $langCode ? 'selected' : '' ?>>
            <?= htmlspecialchars(t('wte.admin_tilganger.lang_' . $langCode)) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btnPrimary"><?= htmlspecialchars(t('wte.admin_tilganger.tmdb_lang_save_btn')) ?></button>
    </form>
  </div>

  <div class="card">
    <h1><?= htmlspecialchars(t('wte.admin_tilganger.backup_heading')) ?></h1>
    <p class="subtitle"><?= htmlspecialchars(t('wte.admin_tilganger.backup_subtitle')) ?></p>
    <button type="button" id="backupDownloadBtn" class="btnPrimary"><?= htmlspecialchars(t('wte.admin_tilganger.backup_download_btn')) ?></button>
    <div id="backupDownloadFeedback"></div>
  </div>

  <div class="card dangerCard">
    <h1><?= htmlspecialchars(t('wte.admin_tilganger.restore_heading')) ?></h1>
    <p class="subtitle"><?= htmlspecialchars(t('wte.admin_tilganger.restore_subtitle')) ?></p>
    <input type="file" id="restoreFileInput" accept=".sql" style="width:100%; padding:10px; border-radius:7px; background:var(--bg); color:var(--text); border:1px solid var(--border); font-size:13px;">
    <button type="button" id="restoreOpenBtn" class="btnDanger" disabled><?= htmlspecialchars(t('wte.admin_tilganger.restore_btn')) ?></button>
    <div id="restoreFeedback"></div>
  </div>

  <div class="card dangerCard">
    <h1><?= htmlspecialchars(t('wte.admin_tilganger.reset_heading')) ?></h1>
    <p class="subtitle"><?= htmlspecialchars(t('wte.admin_tilganger.reset_subtitle')) ?></p>
    <button type="button" id="resetOpenBtn" class="btnDanger"><?= htmlspecialchars(t('wte.admin_tilganger.reset_btn')) ?></button>
    <div id="resetFeedback"></div>
  </div>
</div>

<div class="modalOverlay" id="restoreModal">
  <div class="modalBox">
    <h2><?= htmlspecialchars(t('wte.admin_tilganger.restore_modal_heading')) ?></h2>
    <p id="restoreModalBody"><?= sprintf(htmlspecialchars(t('wte.admin_tilganger.restore_modal_body_template')), '<strong id="restoreFileName"></strong>') ?></p>
    <label for="restoreConfirmInput" style="font-size:13px; color:var(--muted); display:block; margin-top:14px;">
      <?= sprintf(htmlspecialchars(t('wte.admin_tilganger.restore_confirm_label')), '<strong>GJENOPPRETT DATABASE</strong>') ?>
    </label>
    <input type="text" id="restoreConfirmInput" autocomplete="off">
    <div class="modalActions">
      <button type="button" id="restoreCancelBtn" class="btnSecondary"><?= htmlspecialchars(t('wte.admin_tilganger.restore_cancel_btn')) ?></button>
      <button type="button" id="restoreConfirmBtn" class="btnDanger" disabled><?= htmlspecialchars(t('wte.admin_tilganger.restore_confirm_btn')) ?></button>
    </div>
  </div>
</div>

<div class="modalOverlay" id="resetModal">
  <div class="modalBox">
    <h2><?= htmlspecialchars(t('wte.admin_tilganger.reset_modal_heading')) ?></h2>
    <p id="resetModalBody"><?= htmlspecialchars(t('wte.admin_tilganger.reset_loading')) ?></p>
    <ul id="resetPreserved" style="display:none"></ul>
    <label for="resetConfirmInput" style="font-size:13px; color:var(--muted); display:block; margin-top:14px;">
      <?= sprintf(htmlspecialchars(t('wte.admin_tilganger.reset_confirm_label')), '<strong>NULLSTILL ALT</strong>') ?>
    </label>
    <input type="text" id="resetConfirmInput" autocomplete="off" disabled>
    <div class="modalActions">
      <button type="button" id="resetCancelBtn" class="btnSecondary"><?= htmlspecialchars(t('wte.admin_tilganger.reset_cancel_btn')) ?></button>
      <button type="button" id="resetConfirmBtn" class="btnDanger" disabled><?= htmlspecialchars(t('wte.admin_tilganger.reset_confirm_btn')) ?></button>
    </div>
  </div>
</div>

<script>
(function () {
  // Nedlasting: rett GET mot api.php?action=backup_download, som
  // strømmer en ren mysqldump av db_mediearkiv (se tilsvarende
  // backend-endepunkt GET /admin/backup/download). Bruker et skjult
  // <a download>-triggerpunkt heller enn window.location, slik at
  // nettleserens filnavn-forslag (Content-Disposition) respekteres og
  // vi kan vise en feilmelding hvis kallet feiler i stedet for å bare
  // navigere til en feilside.
  var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  var downloadBtn = document.getElementById('backupDownloadBtn');
  var downloadFeedback = document.getElementById('backupDownloadFeedback');

  function escapeHtmlShared(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  downloadBtn.addEventListener('click', function () {
    downloadFeedback.innerHTML = '';
    downloadBtn.disabled = true;
    var originalText = downloadBtn.textContent;
    downloadBtn.textContent = <?= json_encode(t('wte.admin_tilganger.backup_download_in_progress')) ?>;

    fetch('api.php?action=backup_download', { credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) {
          return res.json().then(function (data) {
            throw new Error(data.error || data.detail || <?= json_encode(t('wte.admin_tilganger.backup_download_failed')) ?>);
          });
        }
        var disposition = res.headers.get('Content-Disposition') || '';
        var match = disposition.match(/filename="?([^"]+)"?/);
        var filename = match ? match[1] : 'db_mediearkiv_backup.sql';
        return res.blob().then(function (blob) { return [blob, filename]; });
      })
      .then(function (result) {
        var blob = result[0];
        var filename = result[1];
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
      })
      .catch(function (err) {
        downloadFeedback.innerHTML = '<div class="errorBox">' + escapeHtmlShared(err.message) + '</div>';
      })
      .finally(function () {
        downloadBtn.disabled = false;
        downloadBtn.textContent = originalText;
      });
  });

  // Restore: velg fil -> åpne bekreftelsesmodal (samme mønster som
  // reset-modalen) -> POST multipart/form-data mot
  // api.php?action=backup_restore, som igjen proxyer videre til
  // POST /admin/backup/restore i backend.
  var RESTORE_PHRASE = 'GJENOPPRETT DATABASE';
  var fileInput = document.getElementById('restoreFileInput');
  var restoreOpenBtn = document.getElementById('restoreOpenBtn');
  var restoreCancelBtn = document.getElementById('restoreCancelBtn');
  var restoreConfirmBtn = document.getElementById('restoreConfirmBtn');
  var restoreModal = document.getElementById('restoreModal');
  var restoreFileName = document.getElementById('restoreFileName');
  var restoreConfirmInput = document.getElementById('restoreConfirmInput');
  var restoreFeedback = document.getElementById('restoreFeedback');

  fileInput.addEventListener('change', function () {
    restoreOpenBtn.disabled = !fileInput.files.length;
  });

  function closeRestoreModal() {
    restoreModal.classList.remove('open');
    restoreConfirmInput.value = '';
    restoreConfirmBtn.disabled = true;
  }

  restoreOpenBtn.addEventListener('click', function () {
    if (!fileInput.files.length) {
      return;
    }
    restoreFeedback.innerHTML = '';
    restoreFileName.textContent = fileInput.files[0].name;
    restoreConfirmInput.value = '';
    restoreConfirmBtn.disabled = true;
    restoreModal.classList.add('open');
  });

  restoreCancelBtn.addEventListener('click', closeRestoreModal);
  restoreModal.addEventListener('click', function (ev) {
    if (ev.target === restoreModal) {
      closeRestoreModal();
    }
  });

  restoreConfirmInput.addEventListener('input', function () {
    restoreConfirmBtn.disabled = restoreConfirmInput.value !== RESTORE_PHRASE;
  });

  restoreConfirmBtn.addEventListener('click', function () {
    if (restoreConfirmInput.value !== RESTORE_PHRASE || !fileInput.files.length) {
      return;
    }
    restoreConfirmBtn.disabled = true;
    restoreConfirmInput.disabled = true;
    var restoreModalBody = document.getElementById('restoreModalBody');
    var originalModalBody = restoreModalBody.innerHTML;
    restoreModalBody.textContent = <?= json_encode(t('wte.admin_tilganger.restore_in_progress')) ?>;

    var formData = new FormData();
    formData.append('confirm', restoreConfirmInput.value);
    formData.append('file', fileInput.files[0]);

    fetch('api.php?action=backup_restore', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrfToken },
      body: formData
    })
      .then(function (res) { return res.json().then(function (data) { return [res.ok, data]; }); })
      .then(function (result) {
        var ok = result[0];
        var data = result[1];
        closeRestoreModal();
        restoreModalBody.innerHTML = originalModalBody;
        restoreConfirmInput.disabled = false;
        if (ok) {
          restoreFeedback.innerHTML = '<div class="successBox">'
            + escapeHtmlShared(<?= json_encode(t('wte.admin_tilganger.restore_success')) ?>) + '</div>';
          fileInput.value = '';
          restoreOpenBtn.disabled = true;
        } else {
          restoreFeedback.innerHTML = '<div class="errorBox">'
            + escapeHtmlShared(data.error || data.detail || <?= json_encode(t('wte.admin_tilganger.restore_failed')) ?>) + '</div>';
        }
      })
      .catch(function () {
        closeRestoreModal();
        restoreModalBody.innerHTML = originalModalBody;
        restoreConfirmInput.disabled = false;
        restoreFeedback.innerHTML = '<div class="errorBox">'
          + escapeHtmlShared(<?= json_encode(t('wte.admin_tilganger.restore_failed')) ?>) + '</div>';
      });
  });
})();

(function () {
  var CONFIRM_PHRASE = 'NULLSTILL ALT';
  var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  var openBtn = document.getElementById('resetOpenBtn');
  var cancelBtn = document.getElementById('resetCancelBtn');
  var confirmBtn = document.getElementById('resetConfirmBtn');
  var modal = document.getElementById('resetModal');
  var modalBody = document.getElementById('resetModalBody');
  var preservedList = document.getElementById('resetPreserved');
  var confirmInput = document.getElementById('resetConfirmInput');
  var feedback = document.getElementById('resetFeedback');

  function closeModal() {
    modal.classList.remove('open');
    confirmInput.value = '';
    confirmInput.disabled = true;
    confirmBtn.disabled = true;
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  openBtn.addEventListener('click', function () {
    feedback.innerHTML = '';
    modalBody.textContent = <?= json_encode(t('wte.admin_tilganger.reset_loading')) ?>;
    preservedList.style.display = 'none';
    preservedList.innerHTML = '';
    confirmInput.disabled = true;
    confirmBtn.disabled = true;
    modal.classList.add('open');

    fetch('api.php?action=reset_data_preview', { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.error || data.detail) {
          modalBody.textContent = <?= json_encode(t('wte.admin_tilganger.reset_preview_failed')) ?>;
          return;
        }
        modalBody.innerHTML = <?= json_encode(t('wte.admin_tilganger.reset_modal_body_template')) ?>
          .replace('%d', data.total);
        preservedList.innerHTML = (data.preserved_tables || [])
          .map(function (name) { return '<li>' + escapeHtml(name) + '</li>'; })
          .join('');
        preservedList.style.display = '';
        confirmInput.disabled = false;
      })
      .catch(function () {
        modalBody.textContent = <?= json_encode(t('wte.admin_tilganger.reset_preview_failed')) ?>;
      });
  });

  cancelBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', function (ev) {
    if (ev.target === modal) {
      closeModal();
    }
  });

  confirmInput.addEventListener('input', function () {
    confirmBtn.disabled = confirmInput.value !== CONFIRM_PHRASE;
  });

  confirmBtn.addEventListener('click', function () {
    if (confirmInput.value !== CONFIRM_PHRASE) {
      return;
    }
    confirmBtn.disabled = true;
    confirmInput.disabled = true;
    modalBody.textContent = <?= json_encode(t('wte.admin_tilganger.reset_in_progress')) ?>;

    fetch('api.php?action=reset_data_confirm', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify({ confirm: confirmInput.value })
    })
      .then(function (res) { return res.json().then(function (data) { return [res.ok, data]; }); })
      .then(function (result) {
        var ok = result[0];
        var data = result[1];
        closeModal();
        if (ok) {
          feedback.innerHTML = '<div class="successBox">'
            + escapeHtml(<?= json_encode(t('wte.admin_tilganger.reset_success')) ?>) + '</div>';
        } else {
          feedback.innerHTML = '<div class="errorBox">'
            + escapeHtml(data.error || data.detail || <?= json_encode(t('wte.admin_tilganger.reset_failed')) ?>) + '</div>';
        }
      })
      .catch(function () {
        closeModal();
        feedback.innerHTML = '<div class="errorBox">'
          + escapeHtml(<?= json_encode(t('wte.admin_tilganger.reset_failed')) ?>) + '</div>';
      });
  });
})();
</script>
</body>
</html>
