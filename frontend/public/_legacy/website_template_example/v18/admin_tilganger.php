<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';

// Hard gate: hele siden krever innlogging - samme mønster som
// 2fa_setup.php. En dag med flere roller kan denne strammes ytterligere
// inn til f.eks. require_role("admin") på samme måte som backend
// allerede gjør (se require_role() i backend/app/security.py og bruken
// i PUT /settings/section-access) - i dag er alle innloggede brukere
// admin, så require_login() er tilstrekkelig.
$username = require_login();

$labels = [
    'mine_filmer'    => 'Mine filmer',
    'onskeliste'     => 'Ønskeliste',
    'andre_lister'   => 'Andre lister',
    'administrering' => 'Administrering',
];

$error = null;
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sections = [];
    foreach (array_keys($labels) as $key) {
        $sections[$key] = isset($_POST['section'][$key]);
    }

    [$httpCode, $data] = update_section_access($sections);
    if ($httpCode === 200) {
        $saved = true;
    } else {
        $error = $data['detail'] ?? $data['error'] ?? 'Kunne ikke lagre innstillingene.';
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
?>
<!doctype html>
<html lang="no">
<head>
<meta charset="utf-8">
<title>Tilgangsstyring – Media-katalog</title>
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
</style>
</head>
<body>
<div class="wrap">
  <p><a href="index.php" class="backLink">← Tilbake til Administrering</a></p>

  <div class="card">
    <h1>Tilgangsstyring</h1>
    <p class="subtitle">
      Innlogget som <strong><?= htmlspecialchars($username) ?></strong> ·
      Velg hvilke sider/seksjoner som skal kreve innlogging for besøkende.
    </p>

    <?php if ($error): ?>
      <div class="errorBox"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($saved): ?>
      <div class="successBox">✅ Innstillingene ble lagret.</div>
    <?php endif; ?>

    <form method="post">
      <?php foreach ($labels as $key => $label): ?>
        <div class="rowItem">
          <span><?= htmlspecialchars($label) ?></span>
          <label class="switch">
            <input type="checkbox" name="section[<?= htmlspecialchars($key) ?>]" <?= $currentAccess[$key] ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
        </div>
      <?php endforeach; ?>
      <button type="submit" class="btnPrimary">Lagre</button>
    </form>
  </div>
</div>
</body>
</html>
