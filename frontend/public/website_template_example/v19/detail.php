<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';
require_once __DIR__ . '/lang.php';

$isLoggedIn = is_logged_in();

// Same lock-icon/login-state logic as index.php, so the top nav here
// (a set of static links back to index.php, not JS-driven panels)
// reflects real login state instead of always showing a hardcoded
// lock icon regardless of whether the user is logged in.
$sectionRequiresLogin = fetch_section_access([
    'mine_filmer'    => false,
    'onskeliste'     => true,
    'andre_lister'   => true,
    'administrering' => true,
]);
$sectionAccess = [
    'onskeliste'     => $sectionRequiresLogin['onskeliste'] && !$isLoggedIn,
    'andre_lister'   => $sectionRequiresLogin['andre_lister'] && !$isLoggedIn,
    'administrering' => $sectionRequiresLogin['administrering'] && !$isLoggedIn,
];

/**
 * detail.php – detaljside for én film/serie (website_template_example v18).
 *
 * Åpnes ved å klikke en poster/rad i "Mine filmer" (index.php), med
 * ?id=<content_id> (32-tegns hex, samme form som API-et returnerer).
 * Toppmenyen er identisk med index.php (samme HTML/CSS), men holdes
 * som statiske lenker tilbake til index.php i stedet for JS-styrt
 * panelbytte - selve siden her er en egen side, ikke et panel.
 *
 * Data hentes via api.php?id=... (samme proxy-fil som index.php
 * bruker for listen, se api.php for arkitektur-forklaringen).
 *
 * ============================================================
 *  FORMAT-/EIERSKAPS-BADGES UNDER POSTEREN
 *  Badgene bygges dynamisk fra "collections" (fysiske utgaver, format-
 *  feltet, f.eks. "BD"/"DVD") og "sources" (eksterne kilder, f.eks.
 *  "plex" hvis/når en slik kilde finnes i databasen). Flere badges
 *  vises side ved side hvis flere formater/kilder finnes samtidig
 *  (f.eks. både DVD og Plex).
 *
 *  NB: "Produksjonsselskap" finnes ikke som eget felt i content-
 *  tabellen ennå. Skjemaet er klargjort for det (se kv-listen under),
 *  men viser "-" inntil en slik kolonne evt. legges til i databasen.
 * ============================================================
 */
?>
<!doctype html>
<html lang="<?= htmlspecialchars($GLOBALS['__wte_lang']) ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars(t('wte.detail.meta_title')) ?></title>
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
    }
    *{ box-sizing:border-box; }
    body{
      margin:0;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
      background: var(--bg);
      color: var(--text);
    }

    /* ---- Toppmeny (identisk med index.php) ---- */
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
    nav.mainnav{ display:flex; gap:6px; flex:1; }
    nav.mainnav a{
      appearance:none; border:1px solid transparent; background:transparent;
      color: var(--muted);
      font-size:14px; font-weight:600;
      padding:8px 14px;
      border-radius: 10px;
      cursor:pointer;
      text-decoration:none;
      display:inline-block;
    }
    nav.mainnav a:hover{ color: var(--text); background: rgba(111,141,255,.08); }
    nav.mainnav a.active{
      color: var(--text);
      background: rgba(111,141,255,.15);
      border-color: rgba(111,141,255,.5);
    }
    .authState{
      font-size:12px;
      color: var(--muted);
      display:flex; align-items:center; gap:8px;
      padding:6px 12px;
      border:1px solid var(--line);
      border-radius: 999px;
      white-space: nowrap;
    }
    .authState .dot{ width:8px; height:8px; border-radius:50%; background: var(--danger); display:inline-block; }

    main{ padding: 18px 22px; max-width: 1400px; margin: 0 auto; }

    .backLink{
      display:inline-flex; align-items:center; gap:6px;
      color: var(--muted); text-decoration:none;
      font-size:13px; margin-bottom:14px;
    }
    .backLink:hover{ color: var(--text); }

    .detailLayout{
      display:grid;
      grid-template-columns: 320px 1fr;
      gap:24px;
      align-items:start;
    }
    @media (max-width: 800px){
      .detailLayout{ grid-template-columns: 1fr; }
    }

    .posterCol{ display:grid; gap:14px; }
    .poster{
      height:460px;
      border-radius: 16px;
      background: linear-gradient(135deg, rgba(111,141,255,.35), rgba(61,220,151,.18));
      border:1px solid var(--line);
      display:flex; align-items:flex-end; justify-content:flex-start;
      padding:14px;
    }
    .poster .coverBadge{
      font-size:12px; padding:6px 10px; border-radius:999px;
      background: rgba(12,16,36,.65);
      border: 1px solid rgba(238,241,255,.18);
      color: var(--text);
    }

    /* ---- Eierskaps-/format-badges under posteren ---- */
    .ownershipBadges{ display:flex; flex-wrap:wrap; gap:8px; }
    .ownBadge{
      display:flex; align-items:center; gap:6px;
      padding:8px 12px;
      border-radius: 10px;
      font-size:12px; font-weight:700;
      letter-spacing:.02em;
      border:1px solid transparent;
    }
    .ownBadge .icon{ font-size:14px; line-height:1; }
    .ownBadge.bd{ background: rgba(76,130,255,.16); border-color: rgba(76,130,255,.55); color:#bcd0ff; }
    .ownBadge.dvd{ background: rgba(160,170,200,.14); border-color: rgba(160,170,200,.5); color:#dfe3f2; }
    .ownBadge.uhd{ background: rgba(255,196,76,.14); border-color: rgba(255,196,76,.5); color:#ffe2ab; }
    .ownBadge.plex{ background: rgba(229,160,13,.16); border-color: rgba(229,160,13,.55); color:#ffd98a; }
    .ownBadge.other{ background: rgba(111,141,255,.12); border-color: rgba(111,141,255,.4); color: var(--text); }
    .noOwnership{ color: var(--muted); font-size:12px; }

    .titleBlock h1{ margin:0 0 4px; font-size:24px; }
    .titleBlock .originalTitle{ color: var(--muted); font-size:14px; margin-bottom:14px; }

    /* ---- "Bytt data fra kilde"-knapper ---- */
    .refreshButtons{ display:flex; align-items:center; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
    .refreshBtn{
      appearance:none; cursor:pointer;
      font-size:12px; font-weight:700;
      padding:7px 12px;
      border-radius: 999px;
      border:1px solid var(--line);
      background: rgba(111,141,255,.10);
      color: var(--text);
    }
    .refreshBtn:hover:not(:disabled){ background: rgba(111,141,255,.22); border-color: rgba(111,141,255,.55); }
    .refreshBtn:disabled{ opacity:.4; cursor:not-allowed; }
    .refreshStatus{ font-size:12px; color: var(--muted); }
    .refreshStatus.error{ color: var(--danger); }
    .refreshStatus.success{ color: var(--accent2); }

    .factsGrid{
      display:grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap:12px;
      margin-bottom:18px;
    }
    .factCard{
      background: var(--panel);
      border:1px solid var(--line);
      border-radius: 12px;
      padding:12px 14px;
    }
    .factCard .k{ color: var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.06em; margin-bottom:4px; }
    .factCard .v{ font-size:14px; font-weight:600; }

    .sourcesBox{
      background: var(--panel);
      border:1px solid var(--line);
      border-radius: 12px;
      padding:14px;
    }
    .sourcesBox h3{ margin:0 0 10px; font-size:13px; color: var(--muted); text-transform:uppercase; letter-spacing:.06em; }

    /* ---- Faner: Rollebesetning / Samlingsopplysninger / Kjøpsinformasjon ---- */
    .tabSection{ margin-top:26px; }
    .tabBar{
      display:flex; gap:4px;
      border-bottom:1px solid var(--line);
      margin-bottom:16px;
    }
    .tabBtn{
      appearance:none; border:none; background:transparent;
      color: var(--muted);
      font-size:14px; font-weight:600;
      padding:10px 16px;
      cursor:pointer;
      border-bottom:2px solid transparent;
      margin-bottom:-1px;
    }
    .tabBtn:hover{ color: var(--text); }
    .tabBtn.active{ color: var(--text); border-bottom-color: var(--accent); }
    .tabPanel{ display:none; }
    .tabPanel.active{ display:block; }
    .emptyNote{ color: var(--muted); font-size:13px; }

    /* Samlingsopplysninger: flat liste - ett eksemplar = én rad,
       uansett om det er enkeltplate eller box-sett med flere plater. */
    .copyList{
      background: var(--panel);
      border:1px solid var(--line);
      border-radius: 12px;
      overflow:hidden;
    }
    .copyRow{
      display:flex; align-items:center; gap:12px; flex-wrap:wrap;
      padding:12px 16px;
      border-bottom:1px solid var(--line);
      font-size:13px;
    }
    .copyRow:last-child{ border-bottom:none; }
    .copyRow .fmt{
      font-size:12px; font-weight:700; letter-spacing:.02em;
      padding:4px 10px; border-radius:999px;
      background: rgba(111,141,255,.15); border:1px solid rgba(111,141,255,.5);
      white-space:nowrap;
    }
    .copyRow .boxTag{
      font-size:11px; font-weight:700; letter-spacing:.02em;
      padding:3px 8px; border-radius:999px;
      background: rgba(255,196,76,.14); border:1px solid rgba(255,196,76,.5);
      color:#ffe2ab;
      white-space:nowrap;
    }
    .copyRow .meta{ color: var(--muted); font-size:12px; }
    .copyRow.clickable{ cursor:pointer; }
    .copyRow.clickable:hover{ background: rgba(111,141,255,.08); }
    .copyRow .hint{ color: var(--accent); font-size:11px; margin-left:auto; }

    .collectionLayout{
      display:grid;
      grid-template-columns: 1fr 320px;
      gap:16px;
      align-items:start;
    }
    @media (max-width: 700px){
      .collectionLayout{ grid-template-columns: 1fr; }
    }
    .boxSetTable{
      background: var(--panel);
      border:1px solid var(--line);
      border-radius: 12px;
      padding:14px;
    }
    .boxSetTable h4{ margin:0 0 10px; font-size:12px; color: var(--muted); text-transform:uppercase; letter-spacing:.06em; }
    .boxSetTable table{ width:100%; border-collapse:collapse; font-size:12px; }
    .boxSetTable th{ text-align:left; color: var(--muted); font-weight:600; padding:4px 6px; border-bottom:1px solid var(--line); }
    .boxSetTable td{ padding:6px 6px; border-bottom:1px solid var(--line); }
    .boxSetTable tr:last-child td{ border-bottom:none; }
    .boxSetTable tr.currentItem td{ color: var(--accent2); font-weight:700; }

    #detailStatus{ color: var(--muted); font-size:13px; }
  </style>
</head>
<body>

<div class="topbar">
  <div class="brand">🎬 Media-katalog</div>
  <nav class="mainnav">
    <a href="index.php" class="active"><?= htmlspecialchars(t('wte.nav.mine_filmer')) ?></a>
    <a href="index.php?panel=onskeliste"><?= htmlspecialchars(t('wte.nav.onskeliste')) ?><?= $sectionAccess['onskeliste'] ? '<span style="margin-left:6px; opacity:.7; font-size:12px;">🔒</span>' : '' ?></a>
    <a href="index.php?panel=andre_lister"><?= htmlspecialchars(t('wte.nav.andre_lister')) ?><?= $sectionAccess['andre_lister'] ? '<span style="margin-left:6px; opacity:.7; font-size:12px;">🔒</span>' : '' ?></a>
    <a href="index.php?panel=administrering"><?= htmlspecialchars(t('wte.nav.administrering')) ?><?= $sectionAccess['administrering'] ? '<span style="margin-left:6px; opacity:.7; font-size:12px;">🔒</span>' : '' ?></a>
  </nav>
  <div class="authState">
    <span class="dot"></span>
    <?php if ($isLoggedIn): ?>
      <?= htmlspecialchars(t('wte.nav.logged_in_as')) ?> <strong><?= htmlspecialchars(current_username()) ?></strong>
      · <a href="<?= BASE_PATH ?>/logout.php" style="color:inherit;"><?= htmlspecialchars(t('wte.nav.logout')) ?></a>
    <?php else: ?>
      <?= htmlspecialchars(t('wte.nav.not_logged_in')) ?> · <a href="<?= BASE_PATH ?>/login.php" style="color:inherit;"><?= htmlspecialchars(t('wte.nav.login')) ?></a>
    <?php endif; ?>
  </div>
</div>

<main>
  <a class="backLink" href="index.php"><?= t('wte.detail.back_link') ?></a>

  <div id="detailStatus"><?= htmlspecialchars(t('wte.detail.loading')) ?></div>

  <div class="detailLayout" id="detailLayout" style="display:none;">
    <div class="posterCol">
      <div class="poster" id="posterBox">
        <div class="coverBadge" id="posterBadge"></div>
      </div>
      <button class="refreshBtn" id="btnChooseCover" type="button" style="margin-top:8px; width:100%;" disabled><?= htmlspecialchars(t('wte.detail.choose_cover_btn')) ?></button>
      <div class="ownershipBadges" id="ownershipBadges"></div>
    </div>

    <div>
      <div class="titleBlock">
        <h1 id="dTitle"></h1>
        <div class="originalTitle" id="dOriginalTitle"></div>
        <div class="refreshButtons">
          <button class="refreshBtn" id="btnRefreshTmdb" type="button" disabled><?= htmlspecialchars(t('wte.detail.refresh_tmdb_btn')) ?></button>
          <button class="refreshBtn" id="btnRefreshTvdb" type="button" disabled><?= htmlspecialchars(t('wte.detail.refresh_tvdb_btn')) ?></button>
          <span class="refreshStatus" id="refreshStatus"></span>
        </div>
        <div class="refreshStatus" id="lastMergedInfo"></div>
      </div>

      <div class="factsGrid" id="idsGrid">
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_imdb')) ?></div><div class="v" id="fImdb">-</div></div>
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_tmdb')) ?></div><div class="v" id="fTmdb">-</div></div>
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_tvdb')) ?></div><div class="v" id="fTvdb">-</div></div>
      </div>

      <div class="factsGrid">
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_release')) ?></div><div class="v" id="fRelease">-</div></div>
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_runtime')) ?></div><div class="v" id="fRuntime">-</div></div>
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_age')) ?></div><div class="v" id="fAge">-</div></div>
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_type')) ?></div><div class="v" id="fType">-</div></div>
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_prod_company')) ?></div><div class="v" id="fProdCompany">-</div></div>
      </div>

      <div class="sourcesBox">
        <h3><?= htmlspecialchars(t('wte.detail.summary_heading')) ?></h3>
        <div id="overviewText" style="color:var(--muted); font-size:13px; line-height:1.5;"><?= htmlspecialchars(t('wte.detail.no_overview')) ?></div>
      </div>
    </div>
  </div>

  <div class="tabSection" id="tabSection" style="display:none;">
    <div class="tabBar">
      <button class="tabBtn active" data-tab="cast"><?= htmlspecialchars(t('wte.detail.tab_cast')) ?></button>
      <button class="tabBtn" data-tab="collection"><?= htmlspecialchars(t('wte.detail.tab_collection')) ?></button>
      <button class="tabBtn" data-tab="purchase"><?= htmlspecialchars(t('wte.detail.tab_purchase')) ?></button>
    </div>

    <div class="tabPanel active" data-tab-panel="cast">
      <div class="emptyNote">
        <?= htmlspecialchars(t('wte.detail.cast_empty_note')) ?>
      </div>
    </div>

    <div class="tabPanel" data-tab-panel="collection" id="collectionPanel">
      <!-- fylles av renderCollectionTab() -->
    </div>

    <div class="tabPanel" data-tab-panel="purchase">
      <div class="emptyNote">
        <?= htmlspecialchars(t('wte.detail.purchase_empty_note')) ?>
      </div>
    </div>
  </div>
</main>

<!--
  "Bytt cover"-modal: NB - foreløpig uten noen tilgangssperre. Ekte
  brukerroller/innlogging finnes ikke ennå i appen (se "Administrering"
  🔒 i menyen over), så dette endepunktet/knappen er tilgjengelig for
  alle inntil videre. Flytt/lås dette bak ekte admin-tilgang når
  brukerroller er på plass.
-->
<div id="coverModalOverlay" class="modalOverlay" style="display:none;">
  <div class="modalBox">
    <div class="modalHeader">
      <h3><?= htmlspecialchars(t('wte.detail.choose_cover_title')) ?></h3>
      <button type="button" id="btnCloseCoverModal" class="modalCloseBtn">&times;</button>
    </div>
    <div id="coverModalStatus" class="refreshStatus"></div>
    <div id="coverModalGrid" class="coverGrid"></div>
  </div>
</div>

<style>
  .modalOverlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.6);
    display: flex; align-items: center; justify-content: center; z-index: 1000;
  }
  .modalBox {
    background: var(--panel, #1c1f26); border-radius: 10px; padding: 20px;
    max-width: 820px; width: 92%; max-height: 82vh; overflow-y: auto;
  }
  .modalHeader { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
  .modalCloseBtn { background: none; border: none; color: inherit; font-size: 22px; cursor: pointer; line-height: 1; }
  .coverGrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; margin-top: 10px; }
  .coverThumb {
    cursor: pointer; border-radius: 6px; overflow: hidden; border: 2px solid transparent;
    aspect-ratio: 2/3; background-size: cover; background-position: center; position: relative;
  }
  .coverThumb.current { border-color: var(--accent, #5b8def); }
  .coverThumb .currentLabel {
    position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,.7);
    color: #fff; font-size: 11px; text-align: center; padding: 2px 0;
  }
</style>

<script>
  const WTE_I18N = <?= json_encode([
      'detail' => wte_translations_branch('wte.detail'),
  ], JSON_UNESCAPED_UNICODE) ?>;

  // Simple sprintf-style formatter, supports %s and %d placeholders
  // (same helper as clmFormat() in custom_list_manager/v3/script.js).
  function wteFormat(template, ...args) {
    if (!template) return '';
    let i = 0;
    return template.replace(/%[sd]/g, () => (i < args.length ? args[i++] : ''));
  }

  function escapeHtml(s){
    return String(s ?? "").replace(/[&<>"']/g, c => ({
      "&":"&amp;", "<":"&lt;", ">":"&gt;", '"':"&quot;", "'":"&#39;"
    }[c]));
  }

  const params = new URLSearchParams(window.location.search);
  const contentId = params.get("id");

  const detailStatus = document.getElementById("detailStatus");
  const detailLayout = document.getElementById("detailLayout");
  const tabSection = document.getElementById("tabSection");

  // ---- Faner: enkel klikk-styrt visning, ingen avhengigheter ----
  document.querySelectorAll(".tabBtn").forEach(btn => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".tabBtn").forEach(b => b.classList.remove("active"));
      document.querySelectorAll(".tabPanel").forEach(p => p.classList.remove("active"));
      btn.classList.add("active");
      document.querySelector(`.tabPanel[data-tab-panel="${btn.dataset.tab}"]`).classList.add("active");
    });
  });

  // ---- "Samlingsopplysninger": flat liste over fysiske eksemplarer.
  // Ett eksemplar (physical_copy) = én rad, uansett om det er en
  // enkeltplate eller et box-sett med flere plater - box-settet vises
  // altså bare som én oppføring, ikke én rad pr. plate. Klikk på et
  // eksemplar med flere plater viser en liten tabell til høyre:
  // - box-sett: alle filmene i boksen (tittel/format/lagringsplass)
  // - enkelt-utgivelse med flere plater (f.eks. bonusdisk): alle
  //   platene i dette eksemplaret (platetype/format/lagringsplass) ----
  function renderCollectionTab(item){
    const panel = document.getElementById("collectionPanel");
    const copies = item.physical_copies || [];

    if (!copies.length){
      panel.innerHTML = `<div class="emptyNote">${WTE_I18N.detail.not_in_collection}</div>`;
      return;
    }

    const rows = copies.map((c, i) => {
      const b = formatBadge(c.format);
      const discTxt = c.disc_count > 1
        ? wteFormat(WTE_I18N.detail.disc_count_plural, c.disc_count)
        : c.disc_count === 1 ? WTE_I18N.detail.disc_count_single : WTE_I18N.detail.disc_count_unknown;
      const hasBoxItems = c.is_box_set && (c.box_set_items || []).length;
      // Vis platetabellen for alle ikke-box-sett-eksemplarer med minst
      // én registrert plate - også når det bare er én plate.
      const hasDiscs = !c.is_box_set && (c.discs || []).length > 0;
      const clickable = hasBoxItems || hasDiscs;

      return `
        <div class="copyRow${clickable ? " clickable" : ""}" ${clickable ? `data-copy-index="${i}"` : ""}>
          <span class="fmt">${escapeHtml(b.label)}</span>
          ${c.is_box_set ? `<span class="boxTag">${WTE_I18N.detail.box_set_tag}</span>` : ""}
          <span class="meta">${escapeHtml(discTxt)}</span>
          ${c.barcode ? `<span class="meta">${wteFormat(WTE_I18N.detail.barcode_label, escapeHtml(c.barcode))}</span>` : ""}
          ${c.box_set_barcode ? `<span class="meta">${wteFormat(WTE_I18N.detail.box_barcode_label, escapeHtml(c.box_set_barcode))}</span>` : ""}
          ${hasBoxItems ? `<span class="hint">${WTE_I18N.detail.show_box_contents}</span>` : ""}
          ${hasDiscs ? `<span class="hint">${WTE_I18N.detail.show_discs}</span>` : ""}
        </div>
      `;
    }).join("");

    panel.innerHTML = `
      <div class="collectionLayout">
        <div class="copyList">${rows}</div>
        <div class="boxSetTable" id="boxSetTable" style="display:none;"></div>
      </div>
    `;

    const boxSetTable = document.getElementById("boxSetTable");

    function renderBoxSetTable(copy){
      const tableRows = (copy.box_set_items || []).map(it => `
        <tr class="${it.content_id === item.content_id ? "currentItem" : ""}">
          <td>${escapeHtml(it.title)}</td>
          <td>${escapeHtml(it.format || "-")}</td>
          <td>${it.number_in_storage ?? "-"}</td>
        </tr>
      `).join("");

      boxSetTable.innerHTML = `
        <h4>${WTE_I18N.detail.box_contents_heading}</h4>
        <table>
          <thead>
            <tr><th>${WTE_I18N.detail.table_title}</th><th>${WTE_I18N.detail.table_format}</th><th>${WTE_I18N.detail.table_storage}</th></tr>
          </thead>
          <tbody>${tableRows}</tbody>
        </table>
      `;
      boxSetTable.style.display = "block";
    }

    function renderDiscsTable(copy){
      const tableRows = (copy.discs || []).map(d => `
        <tr>
          <td>${escapeHtml(d.label || d.type_disc || WTE_I18N.detail.disc_default_label)}</td>
          <td>${escapeHtml(d.format || "-")}</td>
          <td>${d.number_in_storage ?? "-"}</td>
        </tr>
      `).join("");

      boxSetTable.innerHTML = `
        <h4>${WTE_I18N.detail.discs_in_copy_heading}</h4>
        <table>
          <thead>
            <tr><th>${WTE_I18N.detail.table_disc}</th><th>${WTE_I18N.detail.table_format}</th><th>${WTE_I18N.detail.table_storage}</th></tr>
          </thead>
          <tbody>${tableRows}</tbody>
        </table>
      `;
      boxSetTable.style.display = "block";
    }

    panel.querySelectorAll(".copyRow[data-copy-index]").forEach(row => {
      row.addEventListener("click", () => {
        const copy = copies[Number(row.dataset.copyIndex)];
        if (copy.is_box_set) {
          renderBoxSetTable(copy);
        } else {
          renderDiscsTable(copy);
        }
      });
    });
  }

  // ---- Format-/kilde-badges: BD/DVD/4K UHD + Plex, kan vises samtidig ----
  function formatBadge(format){
    const f = (format || "").toUpperCase();
    if (f === "BD") return { cls: "bd", icon: "💿", label: WTE_I18N.detail.format_bd };
    if (f === "DVD") return { cls: "dvd", icon: "📀", label: WTE_I18N.detail.format_dvd };
    if (f.includes("UHD") || f.includes("4K")) return { cls: "uhd", icon: "💠", label: WTE_I18N.detail.format_uhd };
    return { cls: "other", icon: "📦", label: format || WTE_I18N.detail.format_unknown };
  }

  function renderOwnershipBadges(item){
    const box = document.getElementById("ownershipBadges");
    const badges = [];

    // Ett badge pr. unikt format blant fysiske utgaver.
    const seenFormats = new Set();
    for (const c of (item.collections || [])){
      const key = (c.format || "").toUpperCase();
      if (seenFormats.has(key)) continue;
      seenFormats.add(key);
      const b = formatBadge(c.format);
      badges.push(`<span class="ownBadge ${b.cls}"><span class="icon">${b.icon}</span>${escapeHtml(b.label)}</span>`);
    }

    // Plex vises som eget badge hvis en slik kilde finnes (klar for
    // fremtidig bruk - ingen "plex"-kilde i databasen ennå).
    const hasPlex = (item.sources || []).some(s => (s.source || "").toLowerCase() === "plex");
    if (hasPlex){
      badges.push(`<span class="ownBadge plex"><span class="icon">▶️</span>${WTE_I18N.detail.format_plex}</span>`);
    }

    box.innerHTML = badges.length
      ? badges.join("")
      : `<span class="noOwnership">${WTE_I18N.detail.no_ownership}</span>`;
  }

  function renderDetail(item){
    document.title = item.title + WTE_I18N.detail.meta_title_suffix;

    const posterBox = document.getElementById("posterBox");
    if (item.cover_image){
      posterBox.style.backgroundImage = `url('${item.cover_image.replace(/'/g, "%27")}')`;
      posterBox.style.backgroundSize = "cover";
      posterBox.style.backgroundPosition = "center";
    } else {
      posterBox.style.backgroundImage = "";
    }

    document.getElementById("posterBadge").textContent = (item.content_type || "").toUpperCase();
    document.getElementById("dTitle").textContent = item.title || WTE_I18N.detail.untitled;

    const originalTitleEl = document.getElementById("dOriginalTitle");
    if (item.original_title && item.original_title !== item.title){
      originalTitleEl.textContent = wteFormat(WTE_I18N.detail.original_title_prefix, item.original_title);
    } else {
      originalTitleEl.textContent = "";
    }

    document.getElementById("fRelease").textContent = item.first_release || "-";
    document.getElementById("fRuntime").textContent = item.runtime ? item.runtime + WTE_I18N.detail.runtime_suffix : "-";
    document.getElementById("fAge").textContent = item.age_restriction || "-";
    document.getElementById("fType").textContent = item.content_type || "-";
    // Produksjonsselskap finnes ikke i databasen ennå - se kommentar i toppen av filen.
    document.getElementById("fProdCompany").textContent = item.production_company || "-";

    const fImdb = document.getElementById("fImdb");
    if (item.imdb_id){
      fImdb.innerHTML = `<a href="https://www.imdb.com/title/${escapeHtml(item.imdb_id)}/" target="_blank" rel="noopener">${escapeHtml(item.imdb_id)}</a>`;
    } else {
      fImdb.textContent = "-";
    }

    // tmdb_id/tvdb_id er (ennå) ikke egne kolonner på content - de må
    // hentes ut fra "sources"-listen (content_external_source-radene).
    const findSource = (name) => (item.sources || []).find(
      s => (s.source || "").toLowerCase() === name
    );
    const tmdbSource = findSource("tmdb");
    const tvdbSource = findSource("tvdb");
    document.getElementById("fTmdb").textContent = tmdbSource?.external_id || "-";
    document.getElementById("fTvdb").textContent = tvdbSource?.external_id || "-";

    // Knappene for å bytte data fra TMDB/TVDB er bare aktive når kilden
    // finnes fra før (content_external_source-raden må allerede finnes -
    // se update_content_external_source() i backend).
    const btnTmdb = document.getElementById("btnRefreshTmdb");
    const btnTvdb = document.getElementById("btnRefreshTvdb");
    btnTmdb.disabled = !tmdbSource?.external_id;
    btnTmdb.dataset.externalId = tmdbSource?.external_id || "";
    btnTvdb.disabled = !tvdbSource?.external_id;
    btnTvdb.dataset.externalId = tvdbSource?.external_id || "";

    // Viser hvilken kilde/tidspunkt content-dataene sist ble flettet
    // inn fra, slik at det alltid er synlig - siden content-tabellen
    // viser "gjeldende" data uansett kilde (se last_merged_source/
    // last_merged_at i content-tabellen).
    const lastMergedInfo = document.getElementById("lastMergedInfo");
    if (item.last_merged_source && item.last_merged_at){
      const [datePart, timePart] = item.last_merged_at.split(" ");
      const formattedDate = datePart ? datePart.split("-").reverse().join(".") : item.last_merged_at;
      lastMergedInfo.textContent = wteFormat(
        WTE_I18N.detail.last_merged_from,
        item.last_merged_source.toUpperCase(),
        formattedDate + (timePart ? " " + timePart.slice(0, 5) : "")
      );
    } else {
      lastMergedInfo.textContent = WTE_I18N.detail.not_merged_yet;
    }

    const overviewText = document.getElementById("overviewText");
    if (item.overview){
      overviewText.textContent = item.overview;
      overviewText.style.color = "var(--text)";
    } else {
      overviewText.textContent = WTE_I18N.detail.no_overview;
      overviewText.style.color = "var(--muted)";
    }

    renderOwnershipBadges(item);
    renderCollectionTab(item);

    // "Bytt cover"-knappen krever bare at content finnes (contentId er
    // allerede kjent fra URL-en) - ingen ekstra betingelse.
    document.getElementById("btnChooseCover").disabled = false;

    detailStatus.style.display = "none";
    detailLayout.style.display = "grid";
    tabSection.style.display = "block";
  }

  async function loadDetail(){
    if (!contentId){
      detailStatus.textContent = WTE_I18N.detail.missing_id;
      detailStatus.style.color = "var(--danger)";
      return;
    }
    try {
      const res = await fetch("api.php?id=" + encodeURIComponent(contentId));
      const json = await res.json();
      if (json && json.error) throw new Error(json.error);
      renderDetail(json);
    } catch (err) {
      detailStatus.textContent = WTE_I18N.detail.fetch_error_prefix + err.message;
      detailStatus.style.color = "var(--danger)";
    }
  }

  // "Bytt data fra kilde"-knappene: ber backend hente fulle detaljer på
  // nytt fra TMDB/TVDB (server-side), lagre dem i
  // content_external_source.data_json, og deretter flette dem inn i
  // content-tabellen (title/overview/runtime osv. - med mindre feltet
  // er låst via content.locked_fields). Til slutt lastes siden på nytt
  // (loadDetail) slik at endringene vises.
  async function refreshSource(source, button){
    const externalId = button.dataset.externalId;
    const statusEl = document.getElementById("refreshStatus");
    if (!externalId) return;

    const allButtons = [document.getElementById("btnRefreshTmdb"), document.getElementById("btnRefreshTvdb")];
    allButtons.forEach(b => b.disabled = true);
    statusEl.className = "refreshStatus";
    statusEl.textContent = wteFormat(WTE_I18N.detail.fetching_from, source.toUpperCase());

    try {
      const refreshRes = await fetch(
        "api.php?action=refresh_external_source&source=" + encodeURIComponent(source) +
          "&external_id=" + encodeURIComponent(externalId),
        { method: "POST" }
      );
      const refreshJson = await refreshRes.json();
      if (!refreshRes.ok || refreshJson.error) {
        throw new Error(refreshJson.error || refreshJson.detail || ("HTTP " + refreshRes.status));
      }

      statusEl.textContent = wteFormat(WTE_I18N.detail.merging_from, source.toUpperCase());

      const mergeRes = await fetch(
        "api.php?action=merge_external_source&source=" + encodeURIComponent(source) +
          "&external_id=" + encodeURIComponent(externalId),
        { method: "POST" }
      );
      const mergeJson = await mergeRes.json();
      if (!mergeRes.ok || mergeJson.error) {
        throw new Error(mergeJson.error || mergeJson.detail || ("HTTP " + mergeRes.status));
      }

      const mergedFields = mergeJson.merged_fields || [];
      const skippedFields = mergeJson.skipped_locked_fields || [];
      let message = wteFormat(WTE_I18N.detail.source_updated, source.toUpperCase(), refreshJson.fetched_at || WTE_I18N.detail.now);
      message += mergedFields.length
        ? wteFormat(WTE_I18N.detail.merged_fields_prefix, mergedFields.join(", "))
        : WTE_I18N.detail.no_fields_changed;
      if (skippedFields.length){
        message += wteFormat(WTE_I18N.detail.locked_fields_skipped, skippedFields.join(", "));
      }

      statusEl.className = "refreshStatus success";
      statusEl.textContent = message;
      await loadDetail();
    } catch (err) {
      statusEl.className = "refreshStatus error";
      statusEl.textContent = wteFormat(WTE_I18N.detail.refresh_error_prefix, source.toUpperCase(), err.message);
      allButtons.forEach(b => b.disabled = false);
    }
  }

  document.getElementById("btnRefreshTmdb").addEventListener("click", (e) => refreshSource("tmdb", e.currentTarget));
  document.getElementById("btnRefreshTvdb").addEventListener("click", (e) => refreshSource("tvdb", e.currentTarget));

  // "Bytt cover"-modal: henter alle tilgjengelige TMDB-postere for
  // filmen (fra sist lagrede data_json - ingen nye TMDB-kall) og lar
  // brukeren velge et av dem som nytt cover_image.
  const coverModalOverlay = document.getElementById("coverModalOverlay");
  const coverModalGrid = document.getElementById("coverModalGrid");
  const coverModalStatus = document.getElementById("coverModalStatus");

  function closeCoverModal(){
    coverModalOverlay.style.display = "none";
    coverModalGrid.innerHTML = "";
    coverModalStatus.textContent = "";
    coverModalStatus.className = "refreshStatus";
  }

  async function openCoverModal(){
    coverModalOverlay.style.display = "flex";
    coverModalGrid.innerHTML = "";
    coverModalStatus.className = "refreshStatus";
    coverModalStatus.textContent = WTE_I18N.detail.loading_posters;

    try {
      const res = await fetch("api.php?action=list_covers&id=" + encodeURIComponent(contentId));
      const json = await res.json();
      if (!res.ok || json.error) throw new Error(json.error || json.detail || ("HTTP " + res.status));

      if (!json.posters || json.posters.length === 0){
        coverModalStatus.textContent = WTE_I18N.detail.no_alt_posters;
        return;
      }

      coverModalStatus.textContent = wteFormat(WTE_I18N.detail.posters_available, json.posters.length);
      coverModalGrid.innerHTML = json.posters.map(p => `
        <div class="coverThumb ${p.is_current ? "current" : ""}"
             style="background-image:url('${p.cover_image.replace(/'/g, "%27")}')"
             data-file-path="${escapeHtml(p.file_path)}">
          ${p.is_current ? `<div class="currentLabel">${WTE_I18N.detail.current_label}</div>` : ""}
        </div>
      `).join("");

      coverModalGrid.querySelectorAll(".coverThumb").forEach(el => {
        el.addEventListener("click", () => setCover(el.dataset.filePath));
      });
    } catch (err) {
      coverModalStatus.className = "refreshStatus error";
      coverModalStatus.textContent = WTE_I18N.detail.fetch_posters_error_prefix + err.message;
    }
  }

  async function setCover(filePath){
    coverModalStatus.className = "refreshStatus";
    coverModalStatus.textContent = WTE_I18N.detail.setting_cover;
    try {
      const res = await fetch("api.php?action=set_cover&id=" + encodeURIComponent(contentId), {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ file_path: filePath }),
      });
      const json = await res.json();
      if (!res.ok || json.error) throw new Error(json.error || json.detail || ("HTTP " + res.status));

      coverModalStatus.className = "refreshStatus success";
      coverModalStatus.textContent = WTE_I18N.detail.cover_updated;
      await loadDetail();
      closeCoverModal();
    } catch (err) {
      coverModalStatus.className = "refreshStatus error";
      coverModalStatus.textContent = WTE_I18N.detail.set_cover_error_prefix + err.message;
    }
  }

  document.getElementById("btnChooseCover").addEventListener("click", openCoverModal);
  document.getElementById("btnCloseCoverModal").addEventListener("click", closeCoverModal);
  coverModalOverlay.addEventListener("click", (e) => {
    if (e.target === coverModalOverlay) closeCoverModal();
  });

  loadDetail();
</script>
</body>
</html>
