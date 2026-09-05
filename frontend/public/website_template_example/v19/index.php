<?php
declare(strict_types=1);

/**
 * website_template_example v19 – som v18, men konvertert til å bruke
 * Bootstrap 5 (CSS+JS fra CDN) for navbar/knapper/kort/tabell/badges,
 * i stedet for kun håndrullet CSS. Samme mørke fargetema som før -
 * Bootstraps egne CSS-variabler (--bs-body-bg, --bs-primary osv.) er
 * satt til å matche eksisterende farger, slik at komponenter som
 * legges til senere (f.eks. Bootstrap-modaler fra custom_list_manager)
 * arver samme utseende uten å kollidere med egendefinert CSS.
 *
 * "Mine filmer" hentes via FastAPI-backend i stedet for at PHP snakker
 * direkte til MySQL.
 *
 * ============================================================
 *  ARKITEKTUR-ENDRING FRA v15/v17
 *  v15/v17 har en api.php som selv kjører SQL mot db_mediearkiv
 *  (via PDO, config.php). Her i v18 er all den databaselogikken
 *  flyttet til backend:
 *      backend/app/services/media_catalog.py   (spørringer)
 *      backend/app/routes/media_catalog_route.py  (GET /media/content)
 *  v18 sin egen api.php gjør ingen SQL selv - den er kun en tynn
 *  server-side proxy som henter JSON fra FastAPI og sender det videre
 *  til nettleseren (se api.php for detaljer/begrunnelse).
 *
 *  Dette er bevisst starten på en større overgang: første steg er å
 *  flytte "Mine filmer" (content-tabellen) over til API-et. Flere
 *  deler av media-katalogen (samlinger, kilder, roller m.m.) kan
 *  legges til i det samme API-et etter hvert, uten at frontend må
 *  endres mye - bare api.php sitt endepunkt-navn/felter.
 * ============================================================
 *
 *  INNLOGGING – NÅ IMPLEMENTERT (for "Administrering")
 *  auth.php gir is_logged_in()/current_username(), backet av en ekte
 *  JWT+2FA-innlogging mot backend (se
 *  backend/app/routes/auth_route.py) og en vanlig PHP-sesjon
 *  (login.php/logout.php). "Administrering" bruker nå
 *  $sectionAccess['administrering'] = !$isLoggedIn - altså faktisk
 *  ulåst når noen er innlogget, ikke bare et visuelt hengelås-ikon.
 *
 *  "Andre lister" og "Ønskeliste" er fortsatt kun visuelt låst
 *  (plassholder) - kan kobles til samme is_logged_in()-sjekk senere på
 *  samme måte som Administrering, hvis/når de får ekte innhold.
 * ============================================================
 *
 *  KONFIGURERBAR TILGANG PER MENYPUNKT
 *  $sectionAccess styrer om et menypunkt vises med hengelås-ikon og
 *  merknadsboks (dvs. "krever innlogging") eller om det er helt åpent.
 *  Verdiene hentes nå fra backend (GET /settings/section-access, lagret
 *  i tabellen section_access i mmc_userdb) i stedet for å være
 *  hardkodet - og kan endres via den nye admin-siden
 *  admin_tilganger.php (krever innlogging som admin). Se
 *  fetch_section_access() i auth.php.
 * ============================================================
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';
require_once __DIR__ . '/lang.php';

$isLoggedIn = is_logged_in();

// Innstillinger for hvilke seksjoner som krever innlogging hentes nå
// fra backend (GET /settings/section-access) i stedet for å være
// hardkodet her - se fetch_section_access() i auth.php. Kan endres via
// den nye admin-siden admin_tilganger.php (krever innlogging).
// $fallback brukes kun hvis API-kallet feiler (f.eks. backend nede),
// og gjenspeiler de tidligere hardkodede standardverdiene.
$sectionRequiresLogin = fetch_section_access([
    'mine_filmer'    => false,
    'onskeliste'     => true,
    'andre_lister'   => true,
    'administrering' => true,
]);

$sectionAccess = [
    'mine_filmer'    => $sectionRequiresLogin['mine_filmer'] && !$isLoggedIn,
    'onskeliste'     => $sectionRequiresLogin['onskeliste'] && !$isLoggedIn,
    'andre_lister'   => $sectionRequiresLogin['andre_lister'] && !$isLoggedIn,
    'administrering' => $sectionRequiresLogin['administrering'] && !$isLoggedIn,
];
?>
<!doctype html>
<html lang="<?= htmlspecialchars($GLOBALS['__wte_lang']) ?>" data-bs-theme="dark">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars(t('wte.index.meta_title')) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    :root{
      --bg:#0c1024;
      --panel:#141a33;
      --panel2:#0f1428;
      --line:#26305a;
      --text:#eef1ff;
      --muted:#98a2ce;
      --accent:#6f8dff;
      --accent2:#3ddc97;
      --danger:#ff8a8a;

      /* Map our existing dark theme onto Bootstrap's own CSS variables,
         so any native Bootstrap component (navbar, modal, buttons added
         later, e.g. by custom_list_manager) automatically matches this
         look without needing extra overrides per component. */
      --bs-body-bg: var(--bg);
      --bs-body-color: var(--text);
      --bs-border-color: var(--line);
      --bs-primary: var(--accent);
      --bs-primary-rgb: 111,141,255;
      --bs-success: var(--accent2);
      --bs-danger: var(--danger);
      --bs-secondary-color: var(--muted);
      --bs-tertiary-bg: var(--panel2);
      --bs-emphasis-color: var(--text);
    }
    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
      background: var(--bg);
      color: var(--text);
    }

    /* ---- Toppmeny (Bootstrap navbar, egendefinert farge/spacing) ---- */
    .topbar{
      position: sticky; top:0; z-index:20;
      display:flex; align-items:center; gap:20px;
      padding: 0 20px;
      height: 60px;
      background: rgba(12,16,36,.9);
      backdrop-filter: blur(8px);
      border-bottom: 1px solid var(--line);
    }
    .brand{
      font-weight:700; letter-spacing:.02em;
      white-space: nowrap;
      margin-right: 10px;
    }
    .mainnav{
      display:flex; gap:6px;
      flex:1;
    }
    .mainnav button.btn{
      appearance:none; border:1px solid transparent; background:transparent;
      color: var(--muted);
      font-size:14px; font-weight:600;
      padding:8px 14px;
      border-radius: 10px;
      cursor:pointer;
    }
    .mainnav button.btn:hover{ color: var(--text); background: rgba(111,141,255,.08); }
    .mainnav button.btn.active{
      color: var(--text);
      background: rgba(111,141,255,.15);
      border-color: rgba(111,141,255,.5);
    }
    .mainnav button .lockIcon{ margin-left:6px; opacity:.7; font-size:12px; }

    .authState{
      font-size:12px;
      color: var(--muted);
      display:flex; align-items:center; gap:8px;
      padding:6px 12px;
      border:1px solid var(--line);
      border-radius: 999px;
      white-space: nowrap;
    }
    .authState .dot{
      width:8px; height:8px; border-radius:50%;
      background: var(--danger);
      display:inline-block;
    }

    /* ---- Language switcher (top nav) ---- */
    .lang-switch{ display:flex; gap:4px; }
    .lang-switch a{
      display:inline-block;
      padding:4px 9px;
      border-radius:8px;
      font-size:11px;
      font-weight:700;
      text-decoration:none;
      color: var(--muted);
      border: 1px solid var(--line);
    }
    .lang-switch a.active{
      color: #0c1024;
      background: var(--accent);
      border-color: var(--accent);
    }

    main{ padding: 14px 22px; max-width: 1800px; margin: 0 auto; }
    .panel{ display:none; }
    .panel.active{ display:block; }

    h2.pageTitle{ margin: 0 0 2px; font-size:15px; }
    p.pageHint{ margin: 0 0 10px; color: var(--muted); font-size:11px; }

    .loginCta{
      display:inline-block; margin-top:6px; padding:8px 16px; border-radius:7px;
      background: var(--accent); color:#fff; font-size:13px; font-weight:600;
      text-decoration:none;
    }
    .loginCta:hover{ opacity:.9; }

    .grid{
      display:grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap:14px;
    }
    .card{
      background: var(--panel);
      border:1px solid var(--line);
      border-radius: 14px;
      overflow:hidden;
      transition: transform .12s ease, border-color .12s ease;
    }
    .card:hover{ transform: translateY(-2px); border-color: rgba(111,141,255,.7); }
    .card .cover{
      height:260px;
      background: linear-gradient(135deg, rgba(111,141,255,.35), rgba(61,220,151,.18));
      display:flex; align-items:flex-end; justify-content:flex-start;
      padding:10px;
    }
    .card .coverBadge{
      font-size:11px; padding:5px 9px; border-radius:999px;
      background: rgba(12,16,36,.65);
      border: 1px solid rgba(238,241,255,.18);
      color: var(--text);
    }
    .card .meta{ padding:14px; }
    .card .title{ font-weight:700; margin-bottom:6px; }
    .card .sub{ color: var(--muted); font-size:12px; display:flex; gap:8px; flex-wrap:wrap; }
    .tag{
      font-size:11px; padding:3px 8px; border-radius:999px;
      background: rgba(111,141,255,.14);
      border:1px solid rgba(111,141,255,.4);
      color: var(--text);
    }
    .tag.good{ background: rgba(61,220,151,.14); border-color: rgba(61,220,151,.5); }

    /* ---- Filter/søk (Mine filmer) – samme mønster som v15 ---- */
    .filterBar{
      display:flex; flex-wrap:wrap; align-items:center; gap:10px;
      margin-bottom:10px;
    }
    .search{
      flex:1; min-width:180px; max-width:360px;
      display:flex; gap:8px; align-items:center;
      background: var(--panel2);
      border:1px solid var(--line);
      border-radius: 10px;
      padding:6px 10px;
    }
    .search input{
      width:100%;
      background: transparent; border:0; outline:0; color: var(--text);
      font-size:13px;
    }
    .chiprow{ display:flex; flex-wrap:wrap; gap:6px; }
    .chip{
      padding:5px 10px; border-radius:999px;
      border:1px solid var(--line);
      background: var(--panel2);
      color: var(--muted);
      cursor:pointer;
      font-size:11px;
      user-select:none;
    }
    .chip.active{ border-color: rgba(111,141,255,.85); color: var(--text); }
    .unwatchedToggle{
      display:flex; gap:6px; align-items:center;
      color: var(--muted); font-size:12px;
      white-space: nowrap;
    }

    /* ---- Visningsbytte: rutenett / liste-tabell (Mine filmer) ---- */
    .viewToggle{
      display:flex; gap:6px; margin-left:auto;
    }
    .viewToggle button{
      appearance:none; border:1px solid var(--line); background: var(--panel2);
      color: var(--muted);
      font-size:12px; font-weight:600;
      padding:5px 12px;
      border-radius: 8px;
      cursor:pointer;
    }
    .viewToggle button.active{
      color: var(--text);
      background: rgba(111,141,255,.15);
      border-color: rgba(111,141,255,.5);
    }
    table.dataTable{
      width:100%;
      border-collapse: collapse;
      background: var(--panel);
      border:1px solid var(--line);
      border-radius: 14px;
      overflow:hidden;
    }
    table.dataTable th, table.dataTable td{
      text-align:left;
      padding:10px 14px;
      border-bottom:1px solid var(--line);
      font-size:13px;
    }
    table.dataTable th{
      color: var(--muted);
      font-size:11px;
      text-transform:uppercase;
      letter-spacing:.06em;
      background: var(--panel2);
    }
    table.dataTable tbody tr:last-child td{ border-bottom:none; }
    table.dataTable tbody tr:hover{ background: rgba(111,141,255,.06); }
    table.dataTable a{ color: var(--accent); text-decoration:none; }
    table.dataTable a:hover{ text-decoration:underline; }

    .list{ display:grid; gap:10px; }
    .row{
      display:flex; justify-content:space-between; align-items:center; gap:12px;
      background: var(--panel);
      border:1px solid var(--line);
      border-radius: 12px;
      padding:12px 14px;
    }
    .row strong{ font-size:14px; }
    .row small{ color: var(--muted); }

    .adminGrid{
      display:grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap:14px;
    }
    .adminCard{
      background: var(--panel2);
      border:1px dashed var(--line);
      border-radius: 14px;
      padding:16px;
      opacity:.6;
      position:relative;
    }
    .adminCard h3{ margin:0 0 6px; font-size:14px; }
    .adminCard p{ margin:0; color: var(--muted); font-size:12px; }
    .adminCard .lockedBadge{
      position:absolute; top:12px; right:12px;
      font-size:11px; color: var(--danger);
      border:1px solid rgba(255,138,138,.5);
      border-radius:999px;
      padding:2px 8px;
    }

    .noteBox{
      margin-top:18px;
      background: rgba(111,141,255,.08);
      border:1px solid rgba(111,141,255,.35);
      border-radius: 12px;
      padding:12px 14px;
      font-size:12.5px;
      color: var(--muted);
    }
    .noteBox strong{ color: var(--text); }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg topbar" data-bs-theme="dark">
  <div class="brand navbar-brand mb-0">🎬 Media-katalog</div>
  <div class="mainnav" id="mainnav">
    <button type="button" class="btn active" data-panel="mine_filmer"><?= htmlspecialchars(t('wte.nav.mine_filmer')) ?><?= $sectionAccess['mine_filmer'] ? '<span class="lockIcon">🔒</span>' : '' ?></button>
    <button type="button" class="btn" data-panel="onskeliste"><?= htmlspecialchars(t('wte.nav.onskeliste')) ?><?= $sectionAccess['onskeliste'] ? '<span class="lockIcon">🔒</span>' : '' ?></button>
    <button type="button" class="btn" data-panel="andre_lister"><?= htmlspecialchars(t('wte.nav.andre_lister')) ?><?= $sectionAccess['andre_lister'] ? '<span class="lockIcon">🔒</span>' : '' ?></button>
    <button type="button" class="btn" data-panel="administrering"><?= htmlspecialchars(t('wte.nav.administrering')) ?><?= $sectionAccess['administrering'] ? '<span class="lockIcon">🔒</span>' : '' ?></button>
  </div>
  <div class="lang-switch">
    <?php foreach (WTE_AVAILABLE_LANGS as $langCode): ?>
      <a href="?lang=<?= htmlspecialchars($langCode) ?>" class="<?= $GLOBALS['__wte_lang'] === $langCode ? 'active' : '' ?>"><?= htmlspecialchars(strtoupper($langCode)) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="authState badge rounded-pill">
    <span class="dot"></span>
    <?php if ($isLoggedIn): ?>
      <?= htmlspecialchars(t('wte.nav.logged_in_as')) ?> <strong><?= htmlspecialchars(current_username()) ?></strong>
      · <a href="logout.php" style="color:inherit;"><?= htmlspecialchars(t('wte.nav.logout')) ?></a>
    <?php else: ?>
      <?= htmlspecialchars(t('wte.nav.not_logged_in')) ?> · <a href="login.php" style="color:inherit;"><?= htmlspecialchars(t('wte.nav.login')) ?></a>
    <?php endif; ?>
  </div>
</nav>

<main>

  <!-- ============ MINE FILMER ============ -->
    <section class="panel active" id="panel-mine_filmer">
      <h2 class="pageTitle"><?= htmlspecialchars(t('wte.index.mine_filmer.title')) ?></h2>
      <?php if ($sectionAccess['mine_filmer']): ?>
      <p class="pageHint"><?= htmlspecialchars(t('wte.locked_hint')) ?></p>
      <a href="login.php" class="loginCta btn"><?= htmlspecialchars(t('wte.nav.login_cta')) ?></a>
      <?php else: ?>

    <div class="filterBar">
      <div class="search">
        <input id="mineFilmerSearch" placeholder="<?= htmlspecialchars(t('wte.index.mine_filmer.search_placeholder')) ?>" />
      </div>
      <div class="chiprow" id="mineFilmerTypeChips"></div>
      <label class="unwatchedToggle">
        <input id="mineFilmerOnlyUnwatched" type="checkbox" />
        <?= htmlspecialchars(t('wte.index.mine_filmer.only_unwatched')) ?>
      </label>
      <div class="viewToggle" id="mineFilmerViewToggle">
        <button type="button" data-view="grid" class="btn active"><?= htmlspecialchars(t('wte.index.mine_filmer.view_grid')) ?></button>
        <button type="button" data-view="list" class="btn"><?= htmlspecialchars(t('wte.index.mine_filmer.view_list')) ?></button>
      </div>
    </div>

    <div id="mineFilmerStatus" style="color:var(--muted); font-size:13px;"><?= htmlspecialchars(t('wte.index.mine_filmer.loading')) ?></div>
    <div class="grid" id="mineFilmerGrid"></div>
    <table class="dataTable table table-dark table-hover" id="mineFilmerTable" style="display:none;">
      <thead>
        <tr>
          <th><?= htmlspecialchars(t('wte.index.mine_filmer.col_title')) ?></th>
          <th><?= htmlspecialchars(t('wte.index.mine_filmer.col_original_title')) ?></th>
          <th><?= htmlspecialchars(t('wte.index.mine_filmer.col_year')) ?></th>
          <th><?= htmlspecialchars(t('wte.index.mine_filmer.col_imdb_id')) ?></th>
        </tr>
      </thead>
      <tbody id="mineFilmerTableBody"></tbody>
    </table>
    <?php endif; ?>
  </section>

  <!-- ============ ØNSKELISTE ============ -->
<section class="panel" id="panel-onskeliste">
  <h2 class="pageTitle"><?= htmlspecialchars(t('wte.index.onskeliste.title')) ?></h2>
  <?php if ($sectionAccess['onskeliste']): ?>
    <p class="pageHint"><?= htmlspecialchars(t('wte.locked_hint')) ?></p>
    <a href="login.php" class="loginCta btn"><?= htmlspecialchars(t('wte.nav.login_cta')) ?></a>
  <?php else: ?>
    <p class="pageHint"><?= htmlspecialchars(t('wte.index.onskeliste.placeholder')) ?></p>
    <div class="list" id="onskelisteList"></div>
  <?php endif; ?>
</section>

  <!-- ============ ANDRE LISTER ============ -->
<section class="panel" id="panel-andre_lister">
  <h2 class="pageTitle"><?= htmlspecialchars(t('wte.index.andre_lister.title')) ?></h2>
  <?php if ($sectionAccess['andre_lister']): ?>
    <p class="pageHint"><?= htmlspecialchars(t('wte.locked_hint')) ?></p>
    <a href="login.php" class="loginCta btn"><?= htmlspecialchars(t('wte.nav.login_cta')) ?></a>
  <?php else: ?>
    <p class="pageHint"><?= htmlspecialchars(t('wte.index.andre_lister.placeholder')) ?></p>
    <div class="grid" id="andreListerGrid"></div>
  <?php endif; ?>
</section>

  <!-- ============ ADMINISTRERING ============ -->
  <section class="panel" id="panel-administrering">
    <h2 class="pageTitle"><?= htmlspecialchars(t('wte.index.administrering.title')) ?></h2>
    <?php if ($isLoggedIn): ?>
      <p class="pageHint"><?= t('wte.index.administrering.logged_in_hint', '<strong>' . htmlspecialchars(current_username()) . '</strong>') ?></p>
    <?php else: ?>
      <p class="pageHint"><?= htmlspecialchars(t('wte.locked_hint')) ?></p>
      <a href="login.php" class="loginCta btn"><?= htmlspecialchars(t('wte.nav.login_cta')) ?></a>
    <?php endif; ?>
    <div class="adminGrid">
      <div class="adminCard card">
        <?php if (!$isLoggedIn): ?><span class="lockedBadge badge"><?= htmlspecialchars(t('wte.index.administrering.locked_badge')) ?></span><?php endif; ?>
        <h3><?= htmlspecialchars(t('wte.index.administrering.card_add_movie_title')) ?></h3>
        <p><?= htmlspecialchars(t('wte.index.administrering.card_add_movie_desc')) ?></p>
      </div>
      <?php if ($isLoggedIn): ?>
      <div class="adminCard card">
        <a href="/custom_list_manager/v3/index.php" style="color:inherit; text-decoration:none; display:block;">
          <h3><?= htmlspecialchars(t('wte.index.administrering.card_edit_lists_title')) ?></h3>
          <p><?= htmlspecialchars(t('wte.index.administrering.card_edit_lists_desc')) ?></p>
        </a>
      </div>
      <div class="adminCard card">
        <a href="2fa_setup.php" style="color:inherit; text-decoration:none; display:block;">
          <h3><?= htmlspecialchars(t('wte.index.administrering.card_2fa_title')) ?></h3>
          <p><?= htmlspecialchars(t('wte.index.administrering.card_2fa_desc')) ?></p>
        </a>
      </div>
      <div class="adminCard card">
        <a href="admin_tilganger.php" style="color:inherit; text-decoration:none; display:block;">
          <h3><?= htmlspecialchars(t('wte.index.administrering.card_access_title')) ?></h3>
          <p><?= htmlspecialchars(t('wte.index.administrering.card_access_desc')) ?></p>
        </a>
      </div>
      <?php endif; ?>
      <div class="adminCard card">
        <?php if (!$isLoggedIn): ?><span class="lockedBadge badge"><?= htmlspecialchars(t('wte.index.administrering.locked_badge')) ?></span><?php endif; ?>
        <h3><?= htmlspecialchars(t('wte.index.administrering.card_system_status_title')) ?></h3>
        <p><?= htmlspecialchars(t('wte.index.administrering.card_system_status_desc')) ?></p>
      </div>
    </div>
    <?php if (!$isLoggedIn): ?>
    <div class="noteBox">
      <?= t('wte.index.administrering.note') ?>
    </div>
    <?php endif; ?>
  </section>

</main>

<script>
  // JS-side texts (fetch error prefix, "all" chip, watched/unwatched
  // badges, demo item names, etc.), exposed from the same lang/*.php
  // files as the server-rendered strings above - see lang.php and
  // wte_translations_branch(). Same pattern as CLM_I18N in
  // custom_list_manager/v3/script.js.
  const WTE_I18N = <?= json_encode([
      'mine_filmer' => wte_translations_branch('wte.index.mine_filmer'),
      'onskeliste'  => wte_translations_branch('wte.index.onskeliste'),
      'andre_lister' => wte_translations_branch('wte.index.andre_lister'),
  ], JSON_UNESCAPED_UNICODE) ?>;

  // Simple sprintf-style formatter, supports %s and %d placeholders
  // (same helper as clmFormat() in custom_list_manager/v3/script.js).
  function wteFormat(template, ...args) {
    if (!template) return '';
    let i = 0;
    return template.replace(/%[sd]/g, () => (i < args.length ? args[i++] : ''));
  }

  // ---- Demo-data (kun plassholder, ingen database) ----
  const demoWishlist = [
    { title: WTE_I18N.onskeliste.demo_items?.[0] ?? "Oppenheimer", addedAt:"2026-05-01" },
    { title: WTE_I18N.onskeliste.demo_items?.[1] ?? "Poor Things", addedAt:"2026-04-18" },
  ];

  const demoLists = [
    { name: WTE_I18N.andre_lister.demo_items?.[0] ?? "Julefilmer", itemCount:7 },
    { name: WTE_I18N.andre_lister.demo_items?.[1] ?? "Barnefilmer", itemCount:12 },
    { name: WTE_I18N.andre_lister.demo_items?.[2] ?? "Skal ses med kompiser", itemCount:3 },
  ];

  // ---- Mine filmer: live data fra databasen (api.php), som v15 ----
  const mineFilmerLocked = <?= $sectionAccess['mine_filmer'] ? 'true' : 'false' ?>;
  let mineFilmerData = [];
  let mineFilmerView = "grid"; // "grid" eller "list"

  if (!mineFilmerLocked) {
  const mineFilmerGrid = document.getElementById("mineFilmerGrid");
  const mineFilmerTable = document.getElementById("mineFilmerTable");
  const mineFilmerTableBody = document.getElementById("mineFilmerTableBody");
  const mineFilmerStatus = document.getElementById("mineFilmerStatus");

  function escapeHtml(s){
    return String(s ?? "").replace(/[&<>"']/g, c => ({
      "&":"&amp;", "<":"&lt;", ">":"&gt;", '"':"&quot;", "'":"&#39;"
    }[c]));
  }

  function renderMineFilmerGrid(items){
    mineFilmerGrid.innerHTML = items.map(item => `
      <div class="card" data-id="${escapeHtml(item.content_id)}" style="cursor:pointer;">
        <div class="cover" ${item.cover_image ? `style="background-image:url('${escapeHtml(item.cover_image)}');background-size:cover;background-position:center;"` : ""}>
          <div class="coverBadge badge">${escapeHtml((item.content_type || "").toUpperCase())}</div>
        </div>
        <div class="meta">
          <div class="title">${escapeHtml(item.title)}</div>
          <div class="sub">
            <span>${escapeHtml(item.first_release || "-")}</span>
            <span class="tag badge ${item.watched_flag ? "good" : ""}">${item.watched_flag ? escapeHtml(WTE_I18N.mine_filmer.watched) : escapeHtml(WTE_I18N.mine_filmer.unwatched)}</span>
          </div>
        </div>
      </div>
    `).join("");
    mineFilmerGrid.querySelectorAll(".card").forEach(el => {
      el.addEventListener("click", () => {
        window.location.href = "detail.php?id=" + encodeURIComponent(el.dataset.id);
      });
    });
  }

  function renderMineFilmerTable(items){
    mineFilmerTableBody.innerHTML = items.map(item => {
      const year = (item.first_release || "").slice(0, 4) || "-";
      const imdbCell = item.imdb_id
        ? `<a href="https://www.imdb.com/title/${escapeHtml(item.imdb_id)}/" target="_blank" rel="noopener" class="imdbLink">${escapeHtml(item.imdb_id)}</a>`
        : "-";
      return `
        <tr data-id="${escapeHtml(item.content_id)}" style="cursor:pointer;">
          <td>${escapeHtml(item.title)}</td>
          <td>${escapeHtml(item.original_title || "-")}</td>
          <td>${year}</td>
          <td>${imdbCell}</td>
        </tr>
      `;
    }).join("");
    mineFilmerTableBody.querySelectorAll("tr").forEach(el => {
      el.addEventListener("click", (e) => {
        if (e.target.closest(".imdbLink")) return; // ikke naviger hvis man klikket IMDb-lenken
        window.location.href = "detail.php?id=" + encodeURIComponent(el.dataset.id);
      });
    });
  }

  // ---- Filter/søk – samme logikk som v15 (tekstsøk + type-chip + kun ikke-sett) ----
  let mineFilmerActiveType = null;
  const mineFilmerSearch = document.getElementById("mineFilmerSearch");
  const mineFilmerTypeChips = document.getElementById("mineFilmerTypeChips");
  const mineFilmerOnlyUnwatched = document.getElementById("mineFilmerOnlyUnwatched");

  function getFilteredMineFilmer(){
    const term = mineFilmerSearch.value.trim().toLowerCase();
    const showUnwatched = mineFilmerOnlyUnwatched.checked;

    return mineFilmerData.filter(item => {
      if (mineFilmerActiveType && item.content_type !== mineFilmerActiveType) return false;
      if (showUnwatched && item.watched_flag) return false;
      if (!term) return true;
      return (item.title || "").toLowerCase().includes(term) ||
             (item.original_title || "").toLowerCase().includes(term);
    });
  }

  function renderMineFilmerTypeChips(){
    const types = [...new Set(mineFilmerData.map(i => i.content_type).filter(Boolean))].sort();
    mineFilmerTypeChips.innerHTML = "";

    const all = document.createElement("div");
    all.className = "chip btn" + (mineFilmerActiveType === null ? " active" : "");
    all.textContent = WTE_I18N.mine_filmer.chip_all;
    all.onclick = () => { mineFilmerActiveType = null; renderMineFilmerTypeChips(); renderMineFilmer(); };
    mineFilmerTypeChips.appendChild(all);

    for (const t of types){
      const el = document.createElement("div");
      el.className = "chip btn" + (mineFilmerActiveType === t ? " active" : "");
      el.textContent = t;
      el.onclick = () => { mineFilmerActiveType = t; renderMineFilmerTypeChips(); renderMineFilmer(); };
      mineFilmerTypeChips.appendChild(el);
    }
  }

  mineFilmerSearch.addEventListener("input", renderMineFilmer);
  mineFilmerOnlyUnwatched.addEventListener("change", renderMineFilmer);

  function renderMineFilmer(){
    const filtered = getFilteredMineFilmer();
    if (mineFilmerView === "grid"){
      mineFilmerGrid.style.display = "";
      mineFilmerTable.style.display = "none";
      renderMineFilmerGrid(filtered);
    } else {
      mineFilmerGrid.style.display = "none";
      mineFilmerTable.style.display = "";
      renderMineFilmerTable(filtered);
    }
  }

  // ---- Rutenett/liste-bytte for Mine filmer ----
  const mineFilmerViewToggle = document.getElementById("mineFilmerViewToggle");
  mineFilmerViewToggle.querySelectorAll("button").forEach(btn => {
    btn.addEventListener("click", () => {
      mineFilmerViewToggle.querySelectorAll("button").forEach(b => b.classList.remove("active"));
      btn.classList.add("active");
      mineFilmerView = btn.dataset.view;
      renderMineFilmer();
    });
  });

  async function loadMineFilmer(){
    try {
      const res = await fetch("api.php");
      const json = await res.json();
      if (json && json.error) throw new Error(json.error);
      mineFilmerData = Array.isArray(json) ? json : [];
      mineFilmerStatus.style.display = "none";
    } catch (err) {
      mineFilmerStatus.textContent = WTE_I18N.mine_filmer.fetch_error_prefix + err.message;
      mineFilmerStatus.style.color = "var(--danger)";
      return;
    }
    renderMineFilmerTypeChips();
    renderMineFilmer();
  }

  loadMineFilmer();
  } // end if (!mineFilmerLocked)


  // ---- Render: Ønskeliste ----
  const onskelisteLocked = <?= $sectionAccess['onskeliste'] ? 'true' : 'false' ?>;
  if (!onskelisteLocked) {
    const onskelisteList = document.getElementById("onskelisteList");
    onskelisteList.innerHTML = demoWishlist.map(w => `
      <div class="row">
        <strong>${w.title}</strong>
        <small>${wteFormat(WTE_I18N.onskeliste.added_at, w.addedAt)}</small>
      </div>
    `).join("");
  }

  // ---- Render: Andre lister ----
  const andreListerLocked = <?= $sectionAccess['andre_lister'] ? 'true' : 'false' ?>;
  if (!andreListerLocked) {
    const andreListerGrid = document.getElementById("andreListerGrid");
    andreListerGrid.innerHTML = demoLists.map(l => `
      <div class="card">
        <div class="meta">
          <div class="title">${l.name}</div>
          <div class="sub"><span class="tag badge">${wteFormat(WTE_I18N.andre_lister.item_count, l.itemCount)}</span></div>
        </div>
      </div>
    `).join("");
  }

  // ---- Toppmeny: bytt synlig panel ----
  const navButtons = document.querySelectorAll("#mainnav button");

  function activatePanel(panelName){
    const targetBtn = document.querySelector('#mainnav button[data-panel="' + panelName + '"]');
    const targetPanel = document.getElementById("panel-" + panelName);
    if (!targetBtn || !targetPanel) return;

    navButtons.forEach(b => b.classList.remove("active"));
    document.querySelectorAll(".panel").forEach(p => p.classList.remove("active"));

    targetBtn.classList.add("active");
    targetPanel.classList.add("active");
  }

  navButtons.forEach(btn => {
    btn.addEventListener("click", () => activatePanel(btn.dataset.panel));
  });

  // Støtte for å lenke direkte til et bestemt panel (f.eks.
  // index.php?panel=onskeliste fra detail.php sin toppmeny) - uten
  // dette endte man alltid opp på "Mine filmer" (standardpanelet i
  // HTML-en), uansett hvilken meny-lenke man faktisk klikket på.
  const requestedPanel = new URLSearchParams(window.location.search).get("panel");
  if (requestedPanel) {
    activatePanel(requestedPanel);
  }
</script>
</body>
</html>
