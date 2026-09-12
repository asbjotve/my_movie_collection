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
  .btn{
    background:var(--bg); border:1px solid var(--border); color:var(--text);
    border-radius:6px; padding:8px 14px; font-size:13px; cursor:pointer;
  }
  .btn:hover{ border-color:var(--accent); }
  .btn:disabled{ opacity:.5; cursor:default; }
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
    <button type="button" id="btnBulkRefreshTmdb" class="btn" style="margin-bottom:14px;">Oppdater flaggede fra TMDB</button>
    <p id="bulkRefreshStatus" class="loadingText" style="display:none;"></p>
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

  async function loadHealthCheck() {
    try {
      const res = await fetch('api.php?action=list_health_check');
      if (!res.ok) {
        throw new Error('HTTP ' + res.status);
      }
      const data = await res.json();

      loadingMsg.style.display = 'none';
      errorMsg.style.display = 'none';

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
      activeFilter = null;

      const filterRow = document.getElementById('filterRow');
      filterRow.innerHTML = '';
      const listCard = document.getElementById('listCard');

      if (allItems.length) {
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

        listCard.style.display = '';
        renderList();
      } else {
        listCard.style.display = 'none';
      }
    } catch (err) {
      loadingMsg.style.display = 'none';
      errorMsg.style.display = '';
      errorMsg.textContent = 'Kunne ikke hente health check-data: ' + err.message;
    }
  }

  // "Oppdater flaggede fra TMDB" - kjører "hent fra kilde" + "flett inn
  // i content" i bulk for alle content-rader som har en TMDB-relevant
  // flagg og en TMDB-kobling (se
  // bulk_refresh_tmdb_for_flagged_content() i media_catalog.py). Kan ta
  // et halvt minutt eller mer (rate-limitert mot TMDB), derfor
  // deaktiveres knappen og en statustekst vises mens den kjører.
  const btnBulkRefreshTmdb = document.getElementById('btnBulkRefreshTmdb');
  const bulkRefreshStatus = document.getElementById('bulkRefreshStatus');
  // Poller status-endepunktet hvert 2. sekund til jobben er ferdig
  // (running=false) - se GET .../bulk-refresh-tmdb/status i
  // backend/app/routes/media_catalog_route.py. Selve jobben kjører i
  // en bakgrunnstråd på backend og kan ta 20-30+ minutter siden
  // TMDBs reelle svartid per kall (ikke rate-limiten) dominerer.
  async function pollBulkRefreshStatus() {
    try {
      const res = await fetch('api.php?action=bulk_refresh_tmdb_status');
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.error || data.detail || ('HTTP ' + res.status));
      }

      if (data.running) {
        const total = data.total_candidates || 0;
        const processed = data.processed || 0;
        bulkRefreshStatus.textContent = total
          ? `Oppdaterer fra TMDB… ${processed} av ${total} behandlet (${data.refreshed || 0} oppdatert).`
          : 'Oppdaterer fra TMDB, forbereder liste over kandidater…';
        setTimeout(pollBulkRefreshStatus, 2000);
        return;
      }

      if (data.fatal_error) {
        bulkRefreshStatus.textContent = 'Feilet: ' + data.fatal_error;
      } else {
        bulkRefreshStatus.textContent =
          `Ferdig: ${data.refreshed || 0} av ${data.total_candidates || 0} oppdatert` +
          (data.errors && data.errors.length ? `, ${data.errors.length} feilet.` : '.');
      }
      btnBulkRefreshTmdb.disabled = false;
      await loadHealthCheck();
    } catch (err) {
      bulkRefreshStatus.textContent = 'Kunne ikke hente status: ' + err.message;
      btnBulkRefreshTmdb.disabled = false;
    }
  }

  btnBulkRefreshTmdb.addEventListener('click', async () => {
    if (!confirm('Dette henter fersk TMDB-data for alle flaggede filmer med TMDB-kobling og fletter dem inn - kan ta 20-30 minutter eller mer i bakgrunnen. Fortsette?')) {
      return;
    }
    btnBulkRefreshTmdb.disabled = true;
    bulkRefreshStatus.style.display = '';
    bulkRefreshStatus.textContent = 'Starter jobb…';
    try {
      const res = await fetch('api.php?action=bulk_refresh_tmdb', { method: 'POST' });
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.error || data.detail || ('HTTP ' + res.status));
      }
      if (data.already_running) {
        bulkRefreshStatus.textContent = 'En jobb kjører allerede - viser fremdrift…';
      }
      setTimeout(pollBulkRefreshStatus, 1000);
    } catch (err) {
      bulkRefreshStatus.textContent = 'Feilet: ' + err.message;
      btnBulkRefreshTmdb.disabled = false;
    }
  });

  await loadHealthCheck();

  // Hvis en jobb allerede kjører (f.eks. brukeren lastet siden på nytt
  // mens den gikk) - gjenoppta polling automatisk i stedet for at
  // fremdriften bare forsvinner fra skjermen.
  try {
    const statusRes = await fetch('api.php?action=bulk_refresh_tmdb_status');
    if (statusRes.ok) {
      const statusData = await statusRes.json();
      if (statusData.running) {
        btnBulkRefreshTmdb.disabled = true;
        bulkRefreshStatus.style.display = '';
        pollBulkRefreshStatus();
      }
    }
  } catch (err) {
    // Stille ignorert - dette er bare en bekvemmelighetssjekk ved
    // sidelasting, ikke kritisk for at siden ellers skal fungere.
  }
})();
</script>
</body>
</html>
