<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';
require_once __DIR__ . '/lang.php';

// Hard gate: hele siden krever innlogging - samme mønster som
// stats.php/admin_tilganger.php. Selve endepunktene er i tillegg
// beskyttet av JWT-innlogging server-side (require_login_or_json_401()
// i api.php -> get_current_user() i backend), så dette er et ekstra
// lag, ikke den eneste beskyttelsen.
$username = require_login();
?>
<!doctype html>
<html lang="<?= htmlspecialchars($GLOBALS['__wte_lang']) ?>">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars(t('wte.index.administrering.card_duplicate_review_title')) ?></title>
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
  .wrap{ max-width:900px; margin:0 auto; }
  .card{
    background:var(--panel); border:1px solid var(--border); border-radius:12px;
    padding:28px; margin-bottom:20px;
  }
  h1{ font-size:20px; margin:0 0 6px; }
  .backLink{ color:var(--muted); font-size:13px; text-decoration:none; }
  .subtitle{ color:var(--muted); font-size:13px; margin:4px 0 18px; }
  .errorBox{
    background:rgba(229,72,77,.12); border:1px solid rgba(229,72,77,.4); color:#ff9a9d;
    padding:10px 12px; border-radius:7px; font-size:13px; margin-bottom:14px;
  }
  .loadingText, .emptyText{ color:var(--muted); font-size:13px; }
  table{ width:100%; border-collapse:collapse; font-size:14px; }
  th, td{ text-align:left; padding:10px 8px; border-bottom:1px solid var(--border); vertical-align:top; }
  th{ color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; }
  .muted{ color:var(--muted); font-size:12px; }
  .actions{ display:flex; gap:8px; flex-wrap:wrap; }
  button{
    font:inherit; border-radius:7px; border:1px solid var(--border);
    background:var(--bg); color:var(--text); padding:7px 12px; cursor:pointer;
  }
  button:hover{ border-color:var(--accent); }
  button.primary{ background:var(--accent); border-color:var(--accent); color:#fff; }
  button.danger:hover{ border-color:var(--danger); }
  button:disabled{ opacity:.5; cursor:default; }
  .rowError{ color:#ff9a9d; font-size:12px; margin-top:6px; }
</style>
</head>
<body>
<div class="wrap">
  <p><a href="index.php" class="backLink">&larr; <?= htmlspecialchars(t('wte.nav.mine_filmer')) ?></a></p>

  <div class="card">
    <h1><?= htmlspecialchars(t('wte.index.administrering.card_duplicate_review_title')) ?></h1>
    <p class="subtitle"><?= htmlspecialchars(t('wte.index.administrering.card_duplicate_review_desc')) ?></p>
    <p id="loadingMsg" class="loadingText">Laster inn…</p>
    <div id="errorMsg" style="display:none;" class="errorBox"></div>
    <p id="emptyMsg" style="display:none;" class="emptyText">Ingen ventende duplikater akkurat nå.</p>
    <table id="pendingTable" style="display:none;">
      <thead>
        <tr>
          <th>Tittel (nytt skann)</th>
          <th>Strekkode</th>
          <th>Finnes fra før som</th>
          <th>Disker</th>
          <th>Lagt i kø</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="pendingTableBody"></tbody>
    </table>
  </div>
</div>

<script>
(function () {
  const loadingMsg = document.getElementById('loadingMsg');
  const errorMsg = document.getElementById('errorMsg');
  const emptyMsg = document.getElementById('emptyMsg');
  const table = document.getElementById('pendingTable');
  const tbody = document.getElementById('pendingTableBody');

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = String(str ?? '');
    return div.innerHTML;
  }

  // CSRF-token lest fra <meta name="csrf-token"> (satt server-side av
  // csrf_meta_tag() i _shared/auth.php) - samme mønster som
  // csrfHeader() i index.php/detail.php.
  function csrfHeader() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    return { 'X-CSRF-Token': token };
  }

  function formatDate(iso) {
    if (!iso) return '-';
    try {
      return new Date(iso).toLocaleString();
    } catch {
      return iso;
    }
  }

  function renderRows(items) {
    tbody.innerHTML = items.map(item => `
      <tr data-id="${item.id}">
        <td>${escapeHtml(item.title)}</td>
        <td>${escapeHtml(item.barcode)}</td>
        <td>${escapeHtml(item.matched_titles || '?')}<br><span class="muted">${escapeHtml(item.matched_format || '')}</span></td>
        <td>${item.disc_count}</td>
        <td class="muted">${escapeHtml(formatDate(item.created_at))}</td>
        <td>
          <div class="actions">
            <button type="button" class="primary" data-action="add_copy">Legg til som kopi</button>
            <button type="button" class="danger" data-action="discard">Forkast</button>
          </div>
          <div class="rowError" style="display:none;"></div>
        </td>
      </tr>
    `).join('');
  }

  async function loadPending() {
    loadingMsg.style.display = '';
    errorMsg.style.display = 'none';
    emptyMsg.style.display = 'none';
    table.style.display = 'none';

    try {
      const res = await fetch('api.php?action=list_pending_duplicates');
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      const items = data.items || [];

      loadingMsg.style.display = 'none';

      if (items.length === 0) {
        emptyMsg.style.display = '';
        return;
      }

      renderRows(items);
      table.style.display = '';
    } catch (err) {
      loadingMsg.style.display = 'none';
      errorMsg.style.display = '';
      errorMsg.textContent = 'Kunne ikke hente ventelisten: ' + err.message;
    }
  }

  tbody.addEventListener('click', async (ev) => {
    const button = ev.target.closest('button[data-action]');
    if (!button) return;

    const row = button.closest('tr');
    const id = row.dataset.id;
    const action = button.dataset.action;
    const rowErrorEl = row.querySelector('.rowError');
    rowErrorEl.style.display = 'none';

    const buttons = row.querySelectorAll('button');
    buttons.forEach(b => b.disabled = true);

    try {
      const res = await fetch(`api.php?action=resolve_pending_duplicate&id=${encodeURIComponent(id)}`, {
        method: 'POST',
        headers: Object.assign({ 'Content-Type': 'application/json' }, csrfHeader()),
        body: JSON.stringify({ action }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        throw new Error(data.detail || data.error || ('HTTP ' + res.status));
      }
      row.remove();
      if (!tbody.children.length) {
        table.style.display = 'none';
        emptyMsg.style.display = '';
      }
    } catch (err) {
      buttons.forEach(b => b.disabled = false);
      rowErrorEl.style.display = '';
      rowErrorEl.textContent = 'Feil: ' + err.message;
    }
  });

  loadPending();
})();
</script>
</body>
</html>
