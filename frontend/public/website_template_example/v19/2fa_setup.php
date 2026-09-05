<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';
require_once __DIR__ . '/lang.php';

// Hard gate: hele siden krever innlogging - ikke bare et skjult
// menypunkt. Prøver noen å åpne denne URL-en direkte uten å være
// innlogget, sendes de til login.php (som sender dem tilbake hit
// etter vellykket innlogging).
$username = require_login();

$error = null;
$recoveryCodes = null; // vises kun rett etter enable - ikke lagret i sesjonen i klartekst

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'start_setup') {
        [$httpCode, $data] = auth_api_authenticated('POST', '/auth/2fa/setup');
        if ($httpCode === 200 && $data) {
            $_SESSION['twofa_setup_pending'] = $data;
        } else {
            $error = $data['detail'] ?? t('wte.twofa.setup_start_error');
        }
    } elseif ($action === 'cancel_setup') {
        unset($_SESSION['twofa_setup_pending']);
    } elseif ($action === 'confirm_setup') {
        $code = trim((string)($_POST['code'] ?? ''));
        if ($code === '') {
            $error = t('wte.twofa.enter_code');
        } else {
            [$httpCode, $data] = auth_api_authenticated('POST', '/auth/2fa/enable', ['code' => $code]);
            if ($httpCode === 200 && $data) {
                unset($_SESSION['twofa_setup_pending']);
                $recoveryCodes = $data['recovery_codes'];
            } else {
                $error = $data['detail'] ?? t('wte.twofa.invalid_code');
            }
        }
    } elseif ($action === 'disable') {
        $password = (string)($_POST['password'] ?? '');
        if ($password === '') {
            $error = t('wte.twofa.enter_password_confirm');
        } else {
            [$httpCode, $data] = auth_api_authenticated('POST', '/auth/2fa/disable', ['password' => $password]);
            if ($httpCode !== 200) {
                $error = $data['detail'] ?? t('wte.twofa.disable_failed');
            }
        }
    }
}

// Hent gjeldende status etter en eventuell handling over, slik at
// siden alltid viser ferskest mulig tilstand.
[$meHttpCode, $me] = auth_api_authenticated('GET', '/auth/me');
$totpEnabled = $meHttpCode === 200 && !empty($me['totp_enabled']);
$setupPending = $_SESSION['twofa_setup_pending'] ?? null;
?>
<!doctype html>
<html lang="<?= htmlspecialchars($GLOBALS['__wte_lang']) ?>">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars(t('wte.twofa.meta_title')) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
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
  label{ display:block; font-size:12.5px; color:var(--muted); margin:14px 0 4px; }
  input{
    width:100%; padding:9px 10px; border-radius:7px; border:1px solid var(--border);
    background:#0d0f14; color:var(--text); font-size:14px;
  }
  input:focus{ outline:none; border-color:var(--accent); }
  button{
    padding:10px 16px; border-radius:7px; border:none; font-size:14px; font-weight:600;
    cursor:pointer;
  }
  .btnPrimary{ background:var(--accent); color:#fff; width:100%; margin-top:16px; }
  .btnDanger{ background:transparent; color:var(--danger); border:1px solid var(--danger); width:100%; margin-top:16px; }
  .btnGhost{ background:none; color:var(--muted); text-decoration:underline; padding:0; margin-top:10px; }
  .statusBadge{
    display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600;
  }
  .statusBadge.on{ background:rgba(62,207,142,.15); color:var(--success); }
  .statusBadge.off{ background:rgba(147,153,171,.15); color:var(--muted); }
  .errorBox{
    background:rgba(229,72,77,.12); border:1px solid rgba(229,72,77,.4); color:#ff9a9d;
    padding:10px 12px; border-radius:7px; font-size:13px; margin-top:14px;
  }
  .qrBox{ text-align:center; margin:16px 0; }
  .qrBox img{ background:#fff; padding:10px; border-radius:8px; }
  .secretText{
    font-family:monospace; font-size:13px; background:#0d0f14; padding:8px 10px;
    border-radius:6px; word-break:break-all; margin-top:8px;
  }
  .recoveryList{
    font-family:monospace; font-size:14px; background:#0d0f14; padding:14px;
    border-radius:8px; margin:14px 0; line-height:2;
  }
  .warnBox{
    background:rgba(255,180,50,.1); border:1px solid rgba(255,180,50,.35); color:#ffcf80;
    padding:10px 12px; border-radius:7px; font-size:13px; margin-top:14px;
  }
</style>
</head>
<body>
<div class="wrap">
  <p><a href="index.php" class="backLink"><?= htmlspecialchars(t('wte.twofa.back_link')) ?></a></p>

  <div class="card">
    <h1><?= htmlspecialchars(t('wte.twofa.heading')) ?></h1>
    <p class="subtitle">
      <?= sprintf(htmlspecialchars(t('wte.twofa.logged_in_as')), '<strong>' . htmlspecialchars($username) . '</strong>') ?>
      <?= htmlspecialchars(t('wte.twofa.status_label')) ?> <span class="statusBadge <?= $totpEnabled ? 'on' : 'off' ?>"><?= $totpEnabled ? htmlspecialchars(t('wte.twofa.status_on')) : htmlspecialchars(t('wte.twofa.status_off')) ?></span>
    </p>

    <?php if ($error): ?>
      <div class="errorBox"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($recoveryCodes): ?>
      <div class="warnBox">
        <?= htmlspecialchars(t('wte.twofa.enabled_notice')) ?>
      </div>
      <div class="recoveryList">
        <?php foreach ($recoveryCodes as $code): ?>
          <?= htmlspecialchars($code) ?><br>
        <?php endforeach; ?>
      </div>
      <p class="subtitle"><?= htmlspecialchars(t('wte.twofa.saved_codes_hint')) ?></p>

    <?php elseif ($totpEnabled): ?>
      <p class="subtitle"><?= htmlspecialchars(t('wte.twofa.enabled_subtitle')) ?></p>
      <form method="post">
        <input type="hidden" name="action" value="disable">
        <label for="password"><?= htmlspecialchars(t('wte.twofa.password_label')) ?></label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
        <button type="submit" class="btnDanger"><?= htmlspecialchars(t('wte.twofa.disable_btn')) ?></button>
      </form>

    <?php elseif ($setupPending): ?>
      <p class="subtitle"><?= htmlspecialchars(t('wte.twofa.scan_qr_subtitle')) ?></p>
      <div class="qrBox">
        <img src="<?= htmlspecialchars($setupPending['qr_code_data_uri']) ?>" alt="<?= htmlspecialchars(t('wte.twofa.qr_alt')) ?>" width="200" height="200">
      </div>
      <p class="subtitle"><?= htmlspecialchars(t('wte.twofa.manual_secret_subtitle')) ?></p>
      <div class="secretText"><?= htmlspecialchars($setupPending['secret']) ?></div>

      <form method="post">
        <input type="hidden" name="action" value="confirm_setup">
        <label for="code"><?= htmlspecialchars(t('wte.twofa.code_from_app_label')) ?></label>
        <input type="text" id="code" name="code" autocomplete="one-time-code" autofocus required>
        <button type="submit" class="btnPrimary"><?= htmlspecialchars(t('wte.twofa.confirm_enable_btn')) ?></button>
      </form>
      <form method="post">
        <input type="hidden" name="action" value="cancel_setup">
        <button type="submit" class="btnGhost"><?= htmlspecialchars(t('wte.twofa.cancel_setup_btn')) ?></button>
      </form>

    <?php else: ?>
      <p class="subtitle"><?= htmlspecialchars(t('wte.twofa.not_enabled_subtitle')) ?></p>
      <form method="post">
        <input type="hidden" name="action" value="start_setup">
        <button type="submit" class="btnPrimary"><?= htmlspecialchars(t('wte.twofa.setup_btn')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
