<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';
require_once __DIR__ . '/lang.php';

// Hard gate: hele siden krever innlogging - samme mønster som
// admin_tilganger.php/2fa_setup.php. Statistikken i seg selv er ikke
// sensitiv, men siden ligger under "Administrering" sammen med de
// andre admin-verktøyene, holder vi den konsistent bak innlogging.
$username = require_login();
?>
<!doctype html>
<html lang="<?= htmlspecialchars($GLOBALS['__wte_lang']) ?>">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars(t('wte.index.administrering.card_stats_title')) ?></title>
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
  .grid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:20px; }
  .totalNumber{ font-size:42px; font-weight:700; color:var(--accent); }
  .totalLabel{ color:var(--muted); font-size:13px; margin-top:4px; }
  .barRow{ margin-bottom:10px; }
  .barLabel{
    display:flex; justify-content:space-between; font-size:13px; margin-bottom:4px;
  }
  .barLabel span:last-child{ color:var(--muted); }
  .barTrack{ background:var(--bg); border:1px solid var(--border); border-radius:6px; height:10px; overflow:hidden; }
  .barFill{ background:var(--accent); height:100%; border-radius:6px; }
  .errorBox{
    background:rgba(229,72,77,.12); border:1px solid rgba(229,72,77,.4); color:#ff9a9d;
    padding:10px 12px; border-radius:7px; font-size:13px;
  }
  .loadingText{ color:var(--muted); font-size:13px; }
</style>
</head>
<body>
<div class="wrap">
  <p><a href="index.php" class="backLink">&larr; <?= htmlspecialchars(t('wte.nav.mine_filmer')) ?></a></p>

  <div class="card">
    <h1><?= htmlspecialchars(t('wte.index.administrering.card_stats_title')) ?></h1>
    <p class="subtitle"><?= htmlspecialchars(t('wte.index.administrering.card_stats_desc')) ?></p>
    <p id="loadingMsg" class="loadingText">Laster inn…</p>
    <div id="errorMsg" style="display:none;" class="errorBox"></div>
  </div>

  <div class="card" id="totalCard" style="display:none;">
    <div class="totalNumber" id="totalNumber">0</div>
    <div class="totalLabel">Filmer/serier totalt i samlingen</div>
  </div>

  <div class="grid">
    <div class="card" id="decadeCard" style="display:none;">
      <h2>Per tiår</h2>
      <div id="decadeBars"></div>
    </div>

    <div class="card" id="formatCard" style="display:none;">
      <h2>Per format</h2>
      <div id="formatBars"></div>
    </div>

    <div class="card" id="genreCard" style="display:none;">
      <h2>Per sjanger</h2>
      <div id="genreBars"></div>
    </div>

    <div class="card" id="groupsCard" style="display:none;">
      <h2>Mest fylte filmgrupper</h2>
      <div id="groupBars"></div>
    </div>
  </div>
</div>

<script>
(async function () {
  const loadingMsg = document.getElementById('loadingMsg');
  const errorMsg = document.getElementById('errorMsg');

  // Bygger en enkel horisontal "bar chart" av rader med CSS-bredde i
  // prosent - unngår avhengighet til et eksternt chart-bibliotek for
  // noe så enkelt som dette.
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
  }

  function renderBars(containerId, items, labelKey, countKey) {
    const container = document.getElementById(containerId);
    const maxCount = Math.max(...items.map(i => i[countKey]), 1);
    container.innerHTML = items.map(item => `
      <div class="barRow">
        <div class="barLabel"><span>${escapeHtml(item[labelKey])}</span><span>${item[countKey]}</span></div>
        <div class="barTrack"><div class="barFill" style="width:${(item[countKey] / maxCount * 100).toFixed(1)}%"></div></div>
      </div>
    `).join('');
  }

  try {
    const res = await fetch('api.php?action=list_stats');
    if (!res.ok) {
      throw new Error('HTTP ' + res.status);
    }
    const data = await res.json();

    loadingMsg.style.display = 'none';

    document.getElementById('totalNumber').textContent = data.total_movies;
    document.getElementById('totalCard').style.display = '';

    if (data.by_decade && data.by_decade.length) {
      const decadeItems = data.by_decade.map(d => ({ label: d.decade + '-tallet', count: d.count }));
      renderBars('decadeBars', decadeItems, 'label', 'count');
      document.getElementById('decadeCard').style.display = '';
    }

    if (data.by_format && data.by_format.length) {
      renderBars('formatBars', data.by_format, 'format', 'count');
      document.getElementById('formatCard').style.display = '';
    }

    if (data.by_genre && data.by_genre.length) {
      renderBars('genreBars', data.by_genre, 'genre', 'count');
      document.getElementById('genreCard').style.display = '';
    }

    if (data.most_added_groups && data.most_added_groups.length) {
      renderBars('groupBars', data.most_added_groups, 'name', 'count');
      document.getElementById('groupsCard').style.display = '';
    }
  } catch (err) {
    loadingMsg.style.display = 'none';
    errorMsg.style.display = '';
    errorMsg.textContent = 'Kunne ikke hente statistikk: ' + err.message;
  }
})();
</script>
</body>
</html>
