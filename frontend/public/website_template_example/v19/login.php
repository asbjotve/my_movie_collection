<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';
require_once __DIR__ . '/lang.php';

auth_start_session();

$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? current_script_dir() . '/index.php';
// Enkel sikring mot open-redirect: tillat kun relative stier innenfor denne appen.
if (!is_string($redirect) || $redirect === '' || $redirect[0] !== '/' || str_starts_with($redirect, '//')) {
    $redirect = current_script_dir() . '/index.php';
}

$error = null;
// 'step' styrer om vi viser brukernavn/passord-skjemaet eller
// 2FA-kode-skjemaet. Ligger i $_SESSION (ikke $_POST) mellom steg 1 og
// 2, slik at pre_auth_token ikke må rundtures via en skjult input.
$step = $_SESSION['login_pending_username'] ?? null ? '2fa' : 'password';

if (is_logged_in()) {
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'password') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = t('wte.login.fill_both_fields');
        } else {
            [$status, $value] = auth_login($username, $password);

            if ($status === 'ok') {
                header('Location: ' . $redirect);
                exit;
            } elseif ($status === 'requires_2fa') {
                $_SESSION['login_pending_username'] = $username;
                $_SESSION['login_pending_preauth'] = $value;
                $step = '2fa';
            } else {
                $error = $value;
            }
        }
    } elseif (($_POST['action'] ?? '') === '2fa') {
        $code = trim((string)($_POST['code'] ?? ''));
        $pendingUsername = $_SESSION['login_pending_username'] ?? null;
        $pendingPreauth = $_SESSION['login_pending_preauth'] ?? null;

        if (!$pendingUsername || !$pendingPreauth) {
            // Sesjonen har utløpt eller vi kom hit uten å ha gått
            // gjennom steg 1 - be om å starte på nytt.
            unset($_SESSION['login_pending_username'], $_SESSION['login_pending_preauth']);
            $error = t('wte.login.session_expired');
            $step = 'password';
        } elseif ($code === '') {
            $error = t('wte.login.enter_2fa_code');
            $step = '2fa';
        } else {
            [$status, $value] = auth_login_2fa($pendingPreauth, $code, $pendingUsername);

            if ($status === 'ok') {
                unset($_SESSION['login_pending_username'], $_SESSION['login_pending_preauth']);
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = $value;
                $step = '2fa';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'restart') {
        unset($_SESSION['login_pending_username'], $_SESSION['login_pending_preauth']);
        $step = 'password';
    }
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars($GLOBALS['__wte_lang']) ?>">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars(t('wte.login.meta_title')) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{
    --bg:#0f1115; --panel:#161922; --border:#262b38; --text:#e7e9ee;
    --muted:#9399ab; --accent:#5b8def; --danger:#e5484d;
  }
  *{ box-sizing:border-box; }
  body{
    margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
    background:var(--bg); color:var(--text); font-family:system-ui, sans-serif;
  }
  .loginBox{
    background:var(--panel); border:1px solid var(--border); border-radius:12px;
    padding:32px; width:340px;
  }
  h1{ font-size:19px; margin:0 0 4px; }
  .subtitle{ color:var(--muted); font-size:13px; margin:0 0 20px; }
  label{ display:block; font-size:12.5px; color:var(--muted); margin:14px 0 4px; }
  input{
    width:100%; padding:9px 10px; border-radius:7px; border:1px solid var(--border);
    background:#0d0f14; color:var(--text); font-size:14px;
  }
  input:focus{ outline:none; border-color:var(--accent); }
  button.submitBtn{
    width:100%; margin-top:20px; padding:10px; border-radius:7px; border:none;
    background:var(--accent); color:#fff; font-size:14px; font-weight:600; cursor:pointer;
  }
  button.submitBtn:hover{ opacity:.92; }
  button.linkBtn{
    background:none; border:none; color:var(--muted); font-size:12.5px; margin-top:12px;
    cursor:pointer; text-decoration:underline; padding:0;
  }
  .errorBox{
    background:rgba(229,72,77,.12); border:1px solid rgba(229,72,77,.4); color:#ff9a9d;
    padding:10px 12px; border-radius:7px; font-size:13px; margin-top:16px;
  }
  .hint{ color:var(--muted); font-size:12px; margin-top:14px; line-height:1.5; }
</style>
</head>
<body>
<div class="loginBox">
  <h1><?= htmlspecialchars(t('wte.login.brand')) ?></h1>

  <?php if ($step === '2fa'): ?>
    <p class="subtitle"><?= t('wte.login.2fa_prompt', '<strong>' . htmlspecialchars($_SESSION['login_pending_username']) . '</strong>') ?></p>
    <form method="post">
      <input type="hidden" name="action" value="2fa">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
      <label for="code"><?= htmlspecialchars(t('wte.login.2fa_code_label')) ?></label>
      <input type="text" id="code" name="code" autocomplete="one-time-code" autofocus required>
      <button type="submit" class="submitBtn"><?= htmlspecialchars(t('wte.login.confirm_btn')) ?></button>
    </form>
    <form method="post">
      <input type="hidden" name="action" value="restart">
      <button type="submit" class="linkBtn"><?= htmlspecialchars(t('wte.login.cancel_2fa_btn')) ?></button>
    </form>
  <?php else: ?>
    <p class="subtitle"><?= htmlspecialchars(t('wte.login.subtitle')) ?></p>
    <form method="post">
      <input type="hidden" name="action" value="password">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
      <label for="username"><?= htmlspecialchars(t('wte.login.username_label')) ?></label>
      <input type="text" id="username" name="username" autocomplete="username" autofocus required>
      <label for="password"><?= htmlspecialchars(t('wte.login.password_label')) ?></label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>
      <button type="submit" class="submitBtn"><?= htmlspecialchars(t('wte.login.login_btn')) ?></button>
    </form>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="errorBox"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <p class="hint">
    <a href="index.php" style="color:var(--muted);"><?= htmlspecialchars(t('wte.login.back_without_login')) ?></a>
  </p>
</div>
</body>
</html>
