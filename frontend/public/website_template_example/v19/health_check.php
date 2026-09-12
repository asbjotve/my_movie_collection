<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';
require_once __DIR__ . '/lang.php';

// Hard gate: hele siden krever innlogging - samme mønster som
// stats.php/admin_tilganger.php.
$username = require_login();
?>
<!doctype html>
<html lang="<?= htmlspecialchars($GLOBALS['__wte_lang']) ?>">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars(t('wte.index.administrering.card_health_check_title')) ?></title>
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
  .wrap{ max-width:900px; margin:0 auto; }
  .card{
    background:var(--panel); border:1px solid var(--border); border-radius:12px;
    padding:28px; margin-bottom:20px;
  }
  h1{ font-size:20px; margin:0 0 6px; }
  h2{ font-size:16px; margin:0 0 16px; }
  .backLink{ color:var(--muted); font-size:13px; text-decoration:none; }
  .subtitle{ color:var(--muted); font-size:13px; margin:4px 0 18px; }
  .summaryGrid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:14px; }
  .summaryItem{ background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:12px; text-align:center; }
  .summaryCount{ font-size:26px; font-weight:700; color:var(--accent); }
  .summaryCount.zero{ color:var(--success); }
  .summaryLabel{ color:var(--muted); font-size:12px; margin-top:4px; }
  table{ width:100%; border-collapse:collapse; font-size:13px; }
  th, td{ text-align:left; padding:8px 6px; border-bottom:1px solid var(--border); }
  th{ color:var(--muted); font-weight:600; }
  a.titleLink{ color:var(--text); text-decoration:none; }
  a.titleLink:hover{ text-decoration:underline; }
  .badge{
    display:inline-block; background:rgba(229,72,77,.15); border:1px solid rgba(229,72,77,.4);
    color:#ff9a9d; border-radius:5px; padding:2px 6px; font-size:11px; margin:1px 3px 1px 0;
  }
  .errorBox{
    background:rgba(229,72,77,.12); border:1px solid rgba(229,72,77,.4); color:#ff9a9d;
    padding:10px 12px; border-radius:7px; font-size:13px;
  }
  .loadingText{ color:var(--muted); font-size:13px; }
  .filterRow{ margin-bottom:14px; display:flex; gap:8px; flex-wrap:wrap; }
  .filterRow button{
    background:var(--bg); border:1px solid var(--border); color:var(--text);
    border-radius:6px; padding:6px 10px; font-size:12px; cursor:pointer;
  }
  .filterRow button.active{ border-color:var(--accent); color:var(--accent); }
</style>
</head>
<body>
<div class="wrap">
  <p><a href="index.php" class="backLink">&larr; <?= htmlspecialchars(t('wte.nav.mine_filmer')) ?></a></p>

  <div class="card">
    <h1><?= htmlspecialchars(t('wte.index.administrering.card_health_check_title')) ?></h1>
    <p class="subtitle"><?= htmlspecialchars(t('wte.index.administrering.card_health_check_desc')) ?></p>
    <p id="loadingMsg" class="loadingText">Laster inn…</p>
    <div id="errorMsg" style="display:none;" class="errorBox"></div>
    <div id="summaryGrid" class="summaryGrid" style="display:none;"></div>
  </div>

  <div class="card" id="listCard" style="display:none;">
    <h2 id="listTitle"></h2>
    <div class="filterRow" id="filterRow"></div>
    <table>
      <thead><tr><th>Tittel</th><th>Problemer</th></tr></thead>
      <tbody id="listBody"></tbody>
    </table>
  </div>
</div>

<script>
(async function () {
  const loadingMsg = document.getElementById('loadingMsg');
  const errorMsg = document.getElementById('errorMsg');

  // Menneskelesbare etiketter for issue-kodene backend returnerer (se
  // get_data_health_issues() i media_catalog.py) - holdes i JS
  // fremfor i18n-filene siden denne siden foreløpig kun er norsk.
  const ISSUE_LABELS = {
    missing_cover: 'Mangler cover',
    missing_overview: 'Mangler beskrivelse',
    missing_runtime: 'Mangler spilletid',
    missing_imdb_id: 'Mangler IMDb-ID',
    missing_external_source: 'Mangler TMDB/TVDB-kilde',
  };

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
  }

  let activeFilter = null;
  let allItems = [];

  function renderList() {
    const items = activeFilter
      ? allItems.filter(i => i.issues.includes(activeFilter))
      : allItems;
    document.getElementById('listTitle').textContent =
      (activeFilter ? ISSUE_LABELS[activeFilter] : 'Alle flaggede filmer') + ' (' + items.length + ')';
    document.getElementById('listBody').innerHTML = items.map(item => `
      <tr>
        <td><a class="titleLink" href="detail.php?id=${encodeURIComponent(item.content_id)}">${escapeHtml(item.title || '(uten tittel)')}</a></td>
        <td>${item.issues.map(code => `<span class="badge">${escapeHtml(ISSUE_LABELS[code] || code)}</span>`).join('')}</td>
      </tr>
    `).join('');
  }

  try {
    const res = await fetch('api.php?action=list_health_check');
    if (!res.ok) {
      throw new Error('HTTP ' + res.status);
    }
    const data = await res.json();

    loadingMsg.style.display = 'none';

    const summaryGrid = document.getElementById('summaryGrid');
    const counts = data.issue_counts || {};
    summaryGrid.innerHTML = Object.keys(ISSUE_LABELS).map(code => `
      <div class="summaryItem">
        <div class="summaryCount ${counts[code] ? '' : 'zero'}">${counts[code] || 0}</div>
        <div class="summaryLabel">${escapeHtml(ISSUE_LABELS[code])}</div>
      </div>
    `).join('');
    summaryGrid.style.display = '';

    allItems = data.items || [];
    if (allItems.length) {
      const filterRow = document.getElementById('filterRow');
      const allBtn = document.createElement('button');
      allBtn.textContent = 'Alle (' + allItems.length + ')';
      allBtn.className = 'active';
      allBtn.onclick = () => {
        activeFilter = null;
        filterRow.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        allBtn.classList.add('active');
        renderList();
      };
      filterRow.appendChild(allBtn);

      for (const code of Object.keys(ISSUE_LABELS)) {
        if (!counts[code]) continue;
        const btn = document.createElement('button');
        btn.textContent = ISSUE_LABELS[code] + ' (' + counts[code] + ')';
        btn.onclick = () => {
          activeFilter = code;
          filterRow.querySelectorAll('button').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          renderList();
        };
        filterRow.appendChild(btn);
      }

      document.getElementById('listCard').style.display = '';
      renderList();
    }
  } catch (err) {
    loadingMsg.style.display = 'none';
    errorMsg.style.display = '';
    errorMsg.textContent = 'Kunne ikke hente health check-data: ' + err.message;
  }
})();
</script>
</body>
</html>
