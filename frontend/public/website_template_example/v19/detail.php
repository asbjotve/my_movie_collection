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
  <?= csrf_meta_tag() ?>
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

    /* ---- Penne-ikon for redigering av felter (content/physical_copy) ----
       Skjult som standard - vises kun når redigeringsmodus er slått på
       via "Rediger"-knappen (se btnToggleEditMode), for å unngå at
       siden virker rotete/forstyrrende når man bare skal se på info. */
    .editPencilBtn{
      appearance:none; cursor:pointer; background:none; border:none;
      color: var(--muted); font-size:13px; padding:0 0 0 6px; line-height:1;
      vertical-align:middle;
      display:none;
    }
    body.editModeActive .editPencilBtn{ display:inline-block; }
    .editPencilBtn:hover{ color: var(--accent, #5b8def); }
    /* ---- "+"-knapp for å SETTE en manglende TMDB/TVDB-id (vises
       uavhengig av redigeringsmodus - styres i stedet av JS ut fra om
       kilden mangler, se renderDetail()). Egen klasse (ikke
       .editPencilBtn) slik at den unngår den delegerte klikk-
       handleren for .editPencilBtn (som åpner "Rediger felt"-modalen
       basert på data-field, noe disse knappene ikke har). ---- */
    .setIdBtn{
      appearance:none; cursor:pointer; background:none; border:none;
      color: var(--muted); font-size:13px; padding:0 0 0 6px; line-height:1;
      vertical-align:middle;
    }
    .setIdBtn:hover{ color: var(--accent, #5b8def); }
    .titleEditRow{ display:flex; align-items:center; gap:6px; }
    .factCard .vRow{ display:flex; align-items:center; gap:6px; }

    /* ---- Hengelås-ikon for å låse/åpne content-felter mot TMDB/TVDB-
       fletting (se content.locked_fields). Samme skjul/vis-mønster som
       .editPencilBtn, men styrt av en egen "Lås felter"-knapp/klasse,
       siden man ofte vil redigere UTEN å samtidig se låseikonene. ---- */
    .lockToggleBtn{
      appearance:none; cursor:pointer; background:none; border:none;
      color: var(--muted); font-size:13px; padding:0 0 0 6px; line-height:1;
      vertical-align:middle;
      display:none;
    }
    body.lockModeActive .lockToggleBtn{ display:inline-block; }
    .lockToggleBtn:hover{ color: var(--accent, #5b8def); }
    .lockToggleBtn.isLocked{ color: var(--danger, #e0554a); }

    .sourcesBox{
      background: var(--panel);
      border:1px solid var(--line);
      border-radius: 12px;
      padding:14px;
    }
    .sourcesBox h3{ margin:0 0 10px; font-size:13px; color: var(--muted); text-transform:uppercase; letter-spacing:.06em; }

    /* ---- Andre filmer i samme filmgruppe (se renderGroupMovies()) ---- */
    .groupMoviesList{ display:flex; flex-wrap:wrap; gap:12px; }
    .groupMovieCard{
      display:block; width:100px; text-decoration:none; color:var(--text);
    }
    .groupMovieCoverWrap{ position:relative; }
    .groupMovieCover{
      width:100px; height:150px; border-radius:8px; background-color: var(--bg,#0d0f14);
      border:1px solid var(--line);
      display:block; object-fit:cover;
    }
    /* Rekkefølge-plassering vises som egen sirkel-badge oppå coveret,
       ADSKILT fra tittelen - unngår forveksling med tall som er en del
       av selve filmtittelen (f.eks. "Politiskolen 4"). */
    .groupOrderBadge{
      position:absolute; top:4px; left:4px;
      min-width:20px; height:20px; padding:0 4px;
      border-radius:999px; background: var(--accent); color:#fff;
      font-size:11px; font-weight:700; line-height:20px; text-align:center;
      box-shadow: 0 1px 3px rgba(0,0,0,.5);
    }
    .groupMovieTitle{ font-size:12px; margin-top:6px; line-height:1.3; }
    .groupMovieCard.current .groupMovieCover{ border-color: var(--accent); border-width:2px; }
    .groupSortToggleBtn{
      background:none; border:none; color: var(--muted); font-size:12px; cursor:pointer;
      margin-left:8px; text-decoration:underline;
    }
    .groupSortToggleBtn.active{ color: var(--accent); }
    .groupSortStatus{ font-size:12px; color: var(--muted); margin-left:8px; }
    /* Dra-og-slipp-modus: håndtak-cursor og visuell tilbakemelding mens
       man drar (se renderGroupMovies()/dragstart-/dragover-handlerne).
       Klassen settes på selve gruppe-boksen (ikke <body>) siden en film
       nå kan tilhøre flere grupper samtidig, og sortering skal kunne
       aktiveres for én gruppe om gangen uten å påvirke de andre. */
    .groupMoviesBox.sortModeActive .groupMovieCard{ cursor: grab; }
    .groupMovieCard.dragging{ opacity:.4; }
    .groupMoviesBox{ margin-bottom:12px; }
    .groupMoviesBox:last-child{ margin-bottom:0; }

    /* ---- "Filmgrupper"-administrering (legg til/fjern) i pop-up-modalen ---- */
    .groupChipList{ display:flex; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
    .groupChip{
      display:flex; align-items:center; gap:6px;
      background: var(--bg,#0d0f14); border:1px solid var(--line);
      border-radius:999px; padding:5px 6px 5px 12px; font-size:13px;
    }
    .groupChipRemoveBtn{
      background:none; border:none; color: var(--muted); cursor:pointer;
      font-size:14px; line-height:1; padding:2px 4px; border-radius:999px;
    }
    .groupChipRemoveBtn:hover{ color: var(--danger, #e0554a); }
    .groupsManageAddRow{ display:flex; gap:8px; }
    .groupsManageAddRow input{ flex:1; }
    .groupsManageStatus{ font-size:12px; color: var(--muted); margin-top:8px; min-height:16px; }

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
    /* Native date-picker calendar icon is near-invisible on the dark
       theme by default - force it to render light so it's visible. */
    input[type="date"]{ color-scheme: dark; }
    input[type="date"]::-webkit-calendar-picker-indicator{ filter: invert(1); cursor:pointer; }
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
  <div class="lang-switch">
    <?php foreach (WTE_AVAILABLE_LANGS as $langCode): ?>
      <a href="<?= htmlspecialchars(wte_lang_switch_url($langCode)) ?>" class="<?= $GLOBALS['__wte_lang'] === $langCode ? 'active' : '' ?>"><?= htmlspecialchars(strtoupper($langCode)) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="authState">
    <span class="dot"></span>
    <?php if ($isLoggedIn): ?>
      <?= htmlspecialchars(t('wte.nav.logged_in_as')) ?> <strong><?= htmlspecialchars(current_username()) ?></strong>
      · <a href="logout.php" style="color:inherit;"><?= htmlspecialchars(t('wte.nav.logout')) ?></a>
    <?php else: ?>
      <?= htmlspecialchars(t('wte.nav.not_logged_in')) ?> · <a href="login.php" style="color:inherit;"><?= htmlspecialchars(t('wte.nav.login')) ?></a>
    <?php endif; ?>
  </div>
</div>

<main>
  <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px; flex-wrap:wrap;">
    <a class="backLink" href="index.php"><?= t('wte.detail.back_link') ?></a>
    <?php if ($isLoggedIn): ?>
    <div style="display:flex; gap:8px;">
      <button type="button" class="refreshBtn" id="btnToggleEditMode"><?= htmlspecialchars(t('wte.detail.toggle_edit_mode_btn')) ?></button>
      <button type="button" class="refreshBtn" id="btnToggleLockMode"><?= htmlspecialchars(t('wte.detail.toggle_lock_mode_btn')) ?></button>
    </div>
    <?php endif; ?>
  </div>

  <div id="detailStatus"><?= htmlspecialchars(t('wte.detail.loading')) ?></div>

  <div class="detailLayout" id="detailLayout" style="display:none;">
    <div class="posterCol">
      <div class="poster" id="posterBox">
        <div class="coverBadge" id="posterBadge"></div>
      </div>
      <?php if ($isLoggedIn): ?>
      <button class="refreshBtn" id="btnChooseCover" type="button" style="margin-top:8px; width:100%;" disabled><?= htmlspecialchars(t('wte.detail.choose_cover_btn')) ?></button>
      <?php endif; ?>
      <div class="ownershipBadges" id="ownershipBadges"></div>
    </div>

    <div>
      <div class="titleBlock">
        <div class="titleEditRow">
          <h1 id="dTitle"></h1>
          <?php if ($isLoggedIn): ?>
          <button type="button" class="editPencilBtn" data-field="title_group" data-type="titleGroup" title="<?= htmlspecialchars(t('wte.detail.edit_field_btn')) ?>">✏️</button>
          <button type="button" class="lockToggleBtn" data-lock-fields="title,original_title" title="<?= htmlspecialchars(t('wte.detail.lock_field_btn')) ?>">🔓</button>
          <?php endif; ?>
        </div>
        <div class="originalTitle" id="dOriginalTitle"></div>
        <?php if ($isLoggedIn): ?>
        <div class="refreshButtons">
          <button class="refreshBtn" id="btnRefreshTmdb" type="button" disabled><?= htmlspecialchars(t('wte.detail.refresh_tmdb_btn')) ?></button>
          <button class="refreshBtn" id="btnRefreshTvdb" type="button" disabled><?= htmlspecialchars(t('wte.detail.refresh_tvdb_btn')) ?></button>
          <span class="refreshStatus" id="refreshStatus"></span>
        </div>
        <?php endif; ?>
        <div class="refreshStatus" id="lastMergedInfo"></div>
      </div>

      <div class="factsGrid" id="idsGrid">
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_imdb')) ?></div><div class="vRow"><div class="v" id="fImdb">-</div><?php if ($isLoggedIn): ?><button type="button" class="editPencilBtn" data-field="imdb_id" data-type="text" data-target="fImdb" title="<?= htmlspecialchars(t('wte.detail.edit_field_btn')) ?>">✏️</button><button type="button" class="lockToggleBtn" data-lock-fields="imdb_id" title="<?= htmlspecialchars(t('wte.detail.lock_field_btn')) ?>">🔓</button><?php endif; ?></div></div>
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_tmdb')) ?></div><div class="vRow"><div class="v" id="fTmdb">-</div><?php if ($isLoggedIn): ?><button type="button" class="setIdBtn" id="btnSetTmdbId" title="<?= htmlspecialchars(t('wte.detail.set_id_btn')) ?>">➕</button><?php endif; ?></div></div>
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_tvdb')) ?></div><div class="vRow"><div class="v" id="fTvdb">-</div><?php if ($isLoggedIn): ?><button type="button" class="setIdBtn" id="btnSetTvdbId" title="<?= htmlspecialchars(t('wte.detail.set_id_btn')) ?>">➕</button><?php endif; ?></div></div>
      </div>

      <div class="factsGrid">
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_release')) ?></div><div class="vRow"><div class="v" id="fRelease">-</div><?php if ($isLoggedIn): ?><button type="button" class="editPencilBtn" data-field="first_release" data-type="date" data-target="fRelease" title="<?= htmlspecialchars(t('wte.detail.edit_field_btn')) ?>">✏️</button><button type="button" class="lockToggleBtn" data-lock-fields="first_release" title="<?= htmlspecialchars(t('wte.detail.lock_field_btn')) ?>">🔓</button><?php endif; ?></div></div>
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_runtime')) ?></div><div class="vRow"><div class="v" id="fRuntime">-</div><?php if ($isLoggedIn): ?><button type="button" class="editPencilBtn" data-field="runtime" data-type="number" data-target="fRuntime" title="<?= htmlspecialchars(t('wte.detail.edit_field_btn')) ?>">✏️</button><button type="button" class="lockToggleBtn" data-lock-fields="runtime" title="<?= htmlspecialchars(t('wte.detail.lock_field_btn')) ?>">🔓</button><?php endif; ?></div></div>
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_age')) ?></div><div class="vRow"><div class="v" id="fAge">-</div><?php if ($isLoggedIn): ?><button type="button" class="editPencilBtn" data-field="age_restriction" data-type="text" data-target="fAge" title="<?= htmlspecialchars(t('wte.detail.edit_field_btn')) ?>">✏️</button><button type="button" class="lockToggleBtn" data-lock-fields="age_restriction" title="<?= htmlspecialchars(t('wte.detail.lock_field_btn')) ?>">🔓</button><?php endif; ?></div></div>
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_type')) ?></div><div class="vRow"><div class="v" id="fType">-</div><?php if ($isLoggedIn): ?><button type="button" class="editPencilBtn" data-field="content_type" data-type="select" data-target="fType" title="<?= htmlspecialchars(t('wte.detail.edit_field_btn')) ?>">✏️</button><button type="button" class="lockToggleBtn" data-lock-fields="content_type" title="<?= htmlspecialchars(t('wte.detail.lock_field_btn')) ?>">🔓</button><?php endif; ?></div></div>
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_prod_company')) ?></div><div class="v" id="fProdCompany">-</div></div>
        <!-- Vises kun for filmer (content_type='movie') - se renderWatchedCard(). Åpner watchedDatesModal med denne brukerens egen rewatch-historikk (content_watched, per-bruker, ikke et globalt flagg). -->
        <div class="factCard" id="fWatchedCard" style="display:none;"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_watched')) ?></div><div class="vRow"><div class="v" id="fWatched">-</div><?php if ($isLoggedIn): ?><button type="button" class="editPencilBtn" id="btnManageWatchedDates" title="<?= htmlspecialchars(t('wte.detail.watched_dates_manage_btn')) ?>">✏️</button><?php endif; ?></div></div>
        <!-- Filmgruppe(r) har ingen hengelås - det er ikke et TMDB/TVDB-flettbart felt (se MERGEABLE_CONTENT_FIELDS i backend). En film kan tilhøre flere grupper samtidig (many-to-many via content_group_membership) - administreres i én pop-up (legg til/fjern), se renderGroupsManageModal(). -->
        <div class="factCard"><div class="k"><?= htmlspecialchars(t('wte.detail.fact_group')) ?></div><div class="vRow"><div class="v" id="fGroup">-</div><?php if ($isLoggedIn): ?><button type="button" class="editPencilBtn" data-field="groups_manage" data-type="groupsManage" title="<?= htmlspecialchars(t('wte.detail.edit_field_btn')) ?>">✏️</button><?php endif; ?></div></div>
      </div>

      <div class="sourcesBox">
        <h3><?= htmlspecialchars(t('wte.detail.summary_heading')) ?><?php if ($isLoggedIn): ?><button type="button" class="editPencilBtn" data-field="overview" data-type="textarea" data-target="overviewText" title="<?= htmlspecialchars(t('wte.detail.edit_field_btn')) ?>">✏️</button><button type="button" class="lockToggleBtn" data-lock-fields="overview" title="<?= htmlspecialchars(t('wte.detail.lock_field_btn')) ?>">🔓</button><?php endif; ?></h3>
        <div id="overviewText" style="color:var(--muted); font-size:13px; line-height:1.5;"><?= htmlspecialchars(t('wte.detail.no_overview')) ?></div>
      </div>

      <!-- Vises kun for filmgrupper filmen faktisk tilhører (se renderGroupMovies()) - én boks PER gruppe, siden en film nå kan tilhøre flere grupper samtidig (movie_group/content_group_membership), for enkel navigering mellom f.eks. en trilogi. -->
      <div id="groupMoviesSections"></div>

      <!-- "Lignende filmer"-seksjon basert på delt sjanger, lastes separat via GET api.php?action=similar (se loadSimilarMovies()) - filmer som allerede er vist i en filmgruppe over er med vilje ekskludert av backend, se get_similar_content() i media_catalog.py. Tom/skjult hvis ingen treff. -->
      <div id="similarMoviesSection"></div>
    </div>
  </div>

  <div class="tabSection" id="tabSection" style="display:none;">
    <div class="tabBar">
      <button class="tabBtn active" data-tab="cast"><?= htmlspecialchars(t('wte.detail.tab_cast')) ?></button>
      <button class="tabBtn" data-tab="collection"><?= htmlspecialchars(t('wte.detail.tab_collection')) ?></button>
      <button class="tabBtn" data-tab="purchase"><?= htmlspecialchars(t('wte.detail.tab_purchase')) ?></button>
      <!-- Vises kun for content_type='series' (se showSeasonsTabIfSeries() lenger ned) -->
      <button class="tabBtn" data-tab="seasons" id="seasonsTabBtn" style="display:none;"><?= htmlspecialchars(t('wte.detail.tab_seasons')) ?></button>
    </div>

    <div class="tabPanel active" data-tab-panel="cast">
      <div class="emptyNote">
        <?= htmlspecialchars(t('wte.detail.cast_empty_note')) ?>
      </div>
    </div>

    <div class="tabPanel" data-tab-panel="collection" id="collectionPanel">
      <!-- fylles av renderCollectionTab() -->
    </div>

    <div class="tabPanel" data-tab-panel="purchase" id="purchasePanel">
      <!-- fylles av renderPurchaseTab() -->
    </div>

    <div class="tabPanel" data-tab-panel="seasons" id="seasonsPanel">
      <!-- fylles av renderSeasonsTab() -->
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

<!--
  Generisk redigerings-modal for penne-ikonene: brukes for både
  content-felter (PATCH /media/content/{id}) og eier-/kjøpsinformasjon
  pr. fysisk eksemplar (PATCH /media/physical-copy/{collection_id}/{copy_id}) -
  se openEditFieldModal()/saveEditField() lenger ned.
-->
<div id="editFieldModalOverlay" class="modalOverlay" style="display:none;">
  <div class="modalBox" style="max-width:420px;">
    <div class="modalHeader">
      <h3 id="editFieldModalTitle"></h3>
      <button type="button" id="btnCloseEditFieldModal" class="modalCloseBtn">&times;</button>
    </div>
    <div id="editFieldModalStatus" class="refreshStatus"></div>
    <div id="editFieldModalBody" style="margin-top:10px;"></div>
    <div style="margin-top:16px; display:flex; gap:8px; justify-content:flex-end;">
      <button type="button" id="btnCancelEditField" class="refreshBtn"><?= htmlspecialchars(t('wte.detail.cancel_btn')) ?></button>
      <button type="button" id="btnSaveEditField" class="refreshBtn"><?= htmlspecialchars(t('wte.detail.save_btn')) ?></button>
    </div>
  </div>
</div>

<!--
  "Sett TMDB/TVDB-id"-modal: søk i TMDB/TVDB (faner) og velg riktig
  treff i stedet for å skrive inn en numerisk id direkte - se
  openSetIdModal()/searchExternalId() lenger ned.
-->
<div id="setIdModalOverlay" class="modalOverlay" style="display:none;">
  <div class="modalBox" style="max-width:640px;">
    <div class="modalHeader">
      <h3 id="setIdModalTitle"></h3>
      <button type="button" id="btnCloseSetIdModal" class="modalCloseBtn">&times;</button>
    </div>
    <div class="setIdTabs">
      <button type="button" class="setIdTabBtn" data-source="tmdb">TMDB</button>
      <button type="button" class="setIdTabBtn" data-source="tvdb">TVDB</button>
    </div>
    <div style="margin-top:10px; display:flex; gap:8px; align-items:center;">
      <select id="setIdContentType" style="flex:0 0 auto;">
        <option value="movie"><?= htmlspecialchars(t('wte.detail.set_id_type_movie')) ?></option>
        <option value="tv"><?= htmlspecialchars(t('wte.detail.set_id_type_tv')) ?></option>
      </select>
      <input type="text" id="setIdSearchInput" placeholder="<?= htmlspecialchars(t('wte.detail.set_id_search_placeholder')) ?>" style="flex:1;">
    </div>
    <div id="setIdModalStatus" class="refreshStatus" style="margin-top:8px;"></div>
    <div id="setIdResultsList" class="setIdResultsList"></div>
  </div>
</div>

<!--
  Generisk bekreftelsesmodal, brukt i stedet for confirm() (f.eks. ved
  ID-konflikt der filmen må slås sammen med en eksisterende) - se
  showConfirmModal() lenger ned.
-->
<div id="confirmModalOverlay" class="modalOverlay" style="display:none;">
  <div class="modalBox" style="max-width:460px;">
    <div class="modalHeader">
      <h3 id="confirmModalTitle"></h3>
      <button type="button" id="btnCloseConfirmModal" class="modalCloseBtn">&times;</button>
    </div>
    <p id="confirmModalMessage" style="margin:0 0 16px; line-height:1.5;"></p>
    <div style="display:flex; justify-content:flex-end; gap:10px;">
      <button type="button" id="btnConfirmModalCancel" class="refreshBtn"></button>
      <button type="button" id="btnConfirmModalOk" class="refreshBtn"></button>
    </div>
  </div>
</div>

<!--
  "Legg til/rediger sesong"-modal - se openSeasonModal()/saveSeason()
  lenger ned.
-->
<div id="seasonModalOverlay" class="modalOverlay" style="display:none;">
  <div class="modalBox" style="max-width:420px;">
    <div class="modalHeader">
      <h3 id="seasonModalTitle"></h3>
      <button type="button" id="btnCloseSeasonModal" class="modalCloseBtn">&times;</button>
    </div>
    <div id="seasonModalStatus" class="refreshStatus"></div>
    <label style="display:block; font-size:12px; color:var(--muted); margin:10px 0 4px;"><?= htmlspecialchars(t('wte.detail.season_field_number')) ?></label>
    <input id="seasonInputNumber" type="number" min="0" style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--line); background:var(--bg); color:var(--text);">
    <label style="display:block; font-size:12px; color:var(--muted); margin:10px 0 4px;"><?= htmlspecialchars(t('wte.detail.season_field_title')) ?></label>
    <input id="seasonInputTitle" type="text" style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--line); background:var(--bg); color:var(--text);">
    <label style="display:block; font-size:12px; color:var(--muted); margin:10px 0 4px;"><?= htmlspecialchars(t('wte.detail.season_field_air_date')) ?></label>
    <input id="seasonInputAirDate" type="date" style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--line); background:var(--bg); color:var(--text);">
    <label style="display:block; font-size:12px; color:var(--muted); margin:10px 0 4px;"><?= htmlspecialchars(t('wte.detail.season_field_overview')) ?></label>
    <textarea id="seasonInputOverview" rows="4" style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--line); background:var(--bg); color:var(--text);"></textarea>
    <div style="margin-top:16px; display:flex; gap:8px; justify-content:flex-end;">
      <button type="button" id="btnCancelSeasonModal" class="refreshBtn"><?= htmlspecialchars(t('wte.detail.cancel_btn')) ?></button>
      <button type="button" id="btnSaveSeasonModal" class="refreshBtn"><?= htmlspecialchars(t('wte.detail.save_btn')) ?></button>
    </div>
  </div>
</div>

<!--
  "Legg til/rediger episode"-modal - se openEpisodeModal()/saveEpisode()
  lenger ned.
-->
<div id="episodeModalOverlay" class="modalOverlay" style="display:none;">
  <div class="modalBox" style="max-width:420px;">
    <div class="modalHeader">
      <h3 id="episodeModalTitle"></h3>
      <button type="button" id="btnCloseEpisodeModal" class="modalCloseBtn">&times;</button>
    </div>
    <div id="episodeModalStatus" class="refreshStatus"></div>
    <label style="display:block; font-size:12px; color:var(--muted); margin:10px 0 4px;"><?= htmlspecialchars(t('wte.detail.episode_field_number')) ?></label>
    <input id="episodeInputNumber" type="number" min="0" style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--line); background:var(--bg); color:var(--text);">
    <label style="display:block; font-size:12px; color:var(--muted); margin:10px 0 4px;"><?= htmlspecialchars(t('wte.detail.episode_field_title')) ?></label>
    <input id="episodeInputTitle" type="text" style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--line); background:var(--bg); color:var(--text);">
    <label style="display:block; font-size:12px; color:var(--muted); margin:10px 0 4px;"><?= htmlspecialchars(t('wte.detail.episode_field_air_date')) ?></label>
    <input id="episodeInputAirDate" type="date" style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--line); background:var(--bg); color:var(--text);">
    <label style="display:block; font-size:12px; color:var(--muted); margin:10px 0 4px;"><?= htmlspecialchars(t('wte.detail.episode_field_runtime')) ?></label>
    <input id="episodeInputRuntime" type="number" min="0" style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--line); background:var(--bg); color:var(--text);">
    <label style="display:block; font-size:12px; color:var(--muted); margin:10px 0 4px;"><?= htmlspecialchars(t('wte.detail.episode_field_overview')) ?></label>
    <textarea id="episodeInputOverview" rows="4" style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--line); background:var(--bg); color:var(--text);"></textarea>
    <div style="margin-top:16px; display:flex; gap:8px; justify-content:flex-end;">
      <button type="button" id="btnCancelEpisodeModal" class="refreshBtn"><?= htmlspecialchars(t('wte.detail.cancel_btn')) ?></button>
      <button type="button" id="btnSaveEpisodeModal" class="refreshBtn"><?= htmlspecialchars(t('wte.detail.save_btn')) ?></button>
    </div>
  </div>
</div>

<!--
  "Sett-datoer"-modal (rewatch-historikk, per innlogget bruker) - se
  openWatchedDatesModal() lenger ned. Samme oppbygning/CSS som
  watchedDatesModalOverlay i index.php (Mine filmer).
-->
<div id="watchedDatesModalOverlay" class="modalOverlay" style="display:none;">
  <div class="modalBox" style="max-width:420px;">
    <div class="modalHeader">
      <h3><?= htmlspecialchars(t('wte.detail.watched_dates_modal_title')) ?></h3>
      <button type="button" id="btnCloseWatchedDatesModal" class="modalCloseBtn">&times;</button>
    </div>
    <div id="watchedDatesModalStatus" class="refreshStatus"></div>
    <ul id="watchedDatesModalList" style="list-style:none; margin:10px 0; padding:0; max-height:220px; overflow-y:auto;"></ul>
    <div style="display:flex; gap:8px; align-items:center; margin-top:10px;">
      <input id="watchedDatesModalNewDate" type="date" style="flex:1; padding:8px; border-radius:7px; background:var(--bg,#0d0f14); color:var(--text); border:1px solid var(--line,#262b38);">
      <button type="button" class="refreshBtn" id="btnAddWatchedDate"><?= htmlspecialchars(t('wte.detail.watched_dates_add_btn')) ?></button>
    </div>
    <div style="margin-top:8px;">
      <button type="button" class="refreshBtn" id="btnWatchedToday" style="width:100%;"><?= htmlspecialchars(t('wte.detail.watched_dates_today_btn')) ?></button>
    </div>
  </div>
</div>

<div id="discPickerModalOverlay" class="modalOverlay" style="display:none;">
  <div class="modalBox" style="max-width:420px;">
    <div class="modalHeader">
      <h3 id="discPickerModalTitle"></h3>
      <button type="button" id="btnCloseDiscPickerModal" class="modalCloseBtn">&times;</button>
    </div>
    <div id="discPickerModalStatus" class="refreshStatus"></div>
    <div id="discPickerModalList" style="max-height:360px; overflow-y:auto; margin-top:10px;"></div>
    <div style="margin-top:16px; display:flex; gap:8px; justify-content:flex-end;">
      <button type="button" id="btnCancelDiscPickerModal" class="refreshBtn"><?= htmlspecialchars(t('wte.detail.cancel_btn')) ?></button>
      <button type="button" id="btnSaveDiscPickerModal" class="refreshBtn"><?= htmlspecialchars(t('wte.detail.save_btn')) ?></button>
    </div>
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

  /* ---- "Sett TMDB/TVDB-id"-modal ---- */
  .setIdTabs{ display:flex; gap:4px; border-bottom:1px solid var(--line); }
  .setIdTabBtn{
    appearance:none; cursor:pointer; background:none; border:none;
    color: var(--muted); font-size:13px; font-weight:700; padding:8px 14px;
    border-bottom:2px solid transparent;
  }
  .setIdTabBtn.active{ color: var(--text); border-bottom-color: var(--accent, #5b8def); }
  .setIdResultsList{ display:flex; flex-direction:column; gap:8px; margin-top:12px; max-height:50vh; overflow-y:auto; }
  .setIdResultItem{
    display:flex; gap:10px; align-items:flex-start; cursor:pointer;
    border:1px solid var(--line); border-radius:8px; padding:8px;
  }
  .setIdResultItem:hover{ background: rgba(111,141,255,.10); border-color: rgba(111,141,255,.55); }
  .setIdResultItem .poster{
    width:46px; height:69px; flex-shrink:0; border-radius:4px; background-size:cover; background-position:center;
    background-color: rgba(255,255,255,.06);
  }
  .setIdResultItem .meta .title{ font-weight:700; font-size:14px; }
  .setIdResultItem .meta .year{ color: var(--muted); font-size:12px; }
  .setIdResultItem .meta .overview{ color: var(--muted); font-size:12px; margin-top:4px; }

  /* ---- "Sesonger"-fane ---- */
  .seasonBlock{ border:1px solid var(--line); border-radius:8px; margin-bottom:14px; overflow:hidden; }
  .seasonHeader{
    display:flex; align-items:center; gap:10px; padding:10px 12px; cursor:pointer;
    background: rgba(255,255,255,.03);
  }
  .seasonHeader:hover{ background: rgba(255,255,255,.06); }
  .seasonHeader .seasonTitle{ font-weight:700; flex:1; }
  .seasonHeader .seasonMeta{ color: var(--muted); font-size:12px; }
  .seasonHeader .caret{ transition: transform .15s; }
  .seasonBlock.collapsed .caret{ transform: rotate(-90deg); }
  .seasonActions{ display:flex; gap:4px; }
  .seasonActions button, .episodeActions button{
    appearance:none; cursor:pointer; background:none; border:none; color: var(--muted);
    font-size:14px; padding:2px 4px;
  }
  .seasonActions button:hover, .episodeActions button:hover{ color: var(--text); }
  .seasonActions .btnToggleSeasonWatched{
    font-size:12px; border:1px solid var(--line); border-radius:6px; padding:3px 8px;
  }
  .seasonActions .btnToggleSeasonWatched:disabled{ opacity:.4; cursor:not-allowed; }
  .seasonBody{ padding:10px 12px; }
  .seasonBlock.collapsed .seasonBody{ display:none; }
  .episodeTable{ width:100%; border-collapse:collapse; font-size:12px; }
  .episodeTable th{ text-align:left; color: var(--muted); font-weight:600; padding:4px 6px; border-bottom:1px solid var(--line); }
  .episodeTable td{ padding:6px 6px; border-bottom:1px solid var(--line); vertical-align:top; }
  .episodeTable tr:last-child td{ border-bottom:none; }
  .episodeTable .epOverview{ color: var(--muted); font-size:11px; margin-top:2px; }
  .episodeTable select{ background:var(--bg); color:var(--text); border:1px solid var(--line); border-radius:4px; font-size:11px; padding:2px 4px; }
  .episodeTable select.episodeDiscSelect{ min-width:220px; }
  .episodeDiscCell{ min-width:160px; }
  .epDiscBadges{ display:flex; flex-wrap:wrap; gap:4px; margin-bottom:4px; }
  .epDiscBadge{ background:var(--bg2, var(--bg)); border:1px solid var(--line); border-radius:10px; padding:2px 8px; font-size:10px; white-space:nowrap; }
  .epDiscSep{ color:var(--muted); font-size:10px; }
  .epDiscNone{ color:var(--muted); font-size:11px; }
  .btnPickEpisodeDiscs.small{ font-size:10px; padding:2px 6px; border:1px solid var(--line); border-radius:6px; background:none; color:var(--muted); cursor:pointer; }
  .btnPickEpisodeDiscs.small:hover{ color:var(--text); }
  .discPickerRow{ display:flex; align-items:center; gap:8px; padding:6px 4px; border-bottom:1px solid var(--line); font-size:13px; cursor:pointer; }
  .discPickerRow:last-child{ border-bottom:none; }
</style>

<script>
  const WTE_I18N = <?= json_encode([
      'detail' => wte_translations_branch('wte.detail'),
  ], JSON_UNESCAPED_UNICODE) ?>;
  // Styrer om penne-ikonene for redigering skal fungere (de vises kun
  // i DOM-en for innloggede besøkende via $isLoggedIn i PHP-delen
  // over, men vi trenger flagget i JS også for pencil-klikk-handleren
  // som er felles for alle ikonene).
  const IS_LOGGED_IN = <?= $isLoggedIn ? 'true' : 'false' ?>;

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

  // Se index.php for forklaring - samme CSRF-header-helper, duplisert
  // her siden detail.php er en egen side uten delt JS-fil.
  function csrfHeader(){
    const token = document.querySelector('meta[name="csrf-token"]')?.content || "";
    return { "X-CSRF-Token": token };
  }

  // Generisk bekreftelsesmodal (erstatter confirm()). Returnerer et
  // Promise<boolean> som løses når brukeren klikker "Avbryt" eller
  // "Bekreft" (eller lukker/klikker utenfor - teller som avbryt).
  const confirmModalOverlay = document.getElementById("confirmModalOverlay");
  const confirmModalTitle = document.getElementById("confirmModalTitle");
  const confirmModalMessage = document.getElementById("confirmModalMessage");
  const btnConfirmModalCancel = document.getElementById("btnConfirmModalCancel");
  const btnConfirmModalOk = document.getElementById("btnConfirmModalOk");
  const btnCloseConfirmModal = document.getElementById("btnCloseConfirmModal");
  let confirmModalResolve = null;

  function showConfirmModal({ title, message, confirmLabel, cancelLabel }){
    confirmModalTitle.textContent = title || WTE_I18N.detail.confirm_title;
    confirmModalMessage.textContent = message || "";
    btnConfirmModalOk.textContent = confirmLabel || WTE_I18N.detail.confirm_ok;
    btnConfirmModalCancel.textContent = cancelLabel || WTE_I18N.detail.confirm_cancel;
    confirmModalOverlay.style.display = "flex";
    return new Promise((resolve) => { confirmModalResolve = resolve; });
  }

  function resolveConfirmModal(result){
    confirmModalOverlay.style.display = "none";
    const resolve = confirmModalResolve;
    confirmModalResolve = null;
    if (resolve) resolve(result);
  }

  btnConfirmModalOk.addEventListener("click", () => resolveConfirmModal(true));
  btnConfirmModalCancel.addEventListener("click", () => resolveConfirmModal(false));
  btnCloseConfirmModal.addEventListener("click", () => resolveConfirmModal(false));
  confirmModalOverlay.addEventListener("click", (e) => { if (e.target === confirmModalOverlay) resolveConfirmModal(false); });

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
      // Et TV-boks-eksemplar (season_number satt) er alltid mer
      // nyttig å vise som plate/episode-tabell enn som "innhold i
      // boksen" (som for en sesongs innerkasse bare ville vist selve
      // serietittelen, ikke noe nyttig) - derfor prioriteres
      // plate-tabellen for disse selv om is_box_set er satt (siden de
      // deler strekkode med ytterboksen). Rene fler-titler-bokser
      // (season_number=null) viser fortsatt "innhold i boksen" som før.
      const hasDiscs = (c.discs || []).length > 0 && (!c.is_box_set || c.season_number != null);
      const hasBoxItems = !hasDiscs && c.is_box_set && (c.box_set_items || []).length;
      const clickable = hasBoxItems || hasDiscs;

      return `
        <div class="copyRow${clickable ? " clickable" : ""}" ${clickable ? `data-copy-index="${i}"` : ""}>
          <span class="fmt">${escapeHtml(b.label)}</span>
          ${c.is_box_set ? `<span class="boxTag">${WTE_I18N.detail.box_set_tag}</span>` : ""}
          <span class="meta">${escapeHtml(discTxt)}</span>
          ${c.barcode ? `<span class="meta">${wteFormat(WTE_I18N.detail.barcode_label, escapeHtml(c.barcode))}</span>` : ""}
          ${c.box_set_barcode ? `<span class="meta">${wteFormat(WTE_I18N.detail.box_barcode_label, escapeHtml(c.box_set_barcode))}</span>` : ""}
          ${c.owner ? `<span class="meta">${wteFormat(WTE_I18N.detail.owner_label, escapeHtml(c.owner))}</span>` : ""}
          ${IS_LOGGED_IN ? `<button type="button" class="editPencilBtn" data-field="owner" data-type="ownerSelect" data-collection-id="${escapeHtml(c.collection_id)}" data-copy-id="${c.copy_id}" title="${escapeHtml(WTE_I18N.detail.edit_field_btn)}">✏️</button>` : ""}
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
      const hasEpisodes = (copy.discs || []).some(d => d.episode_summary);
      const tableRows = (copy.discs || []).map(d => `
        <tr>
          <td>${escapeHtml(d.label || d.type_disc || WTE_I18N.detail.disc_default_label)}</td>
          <td>${escapeHtml(d.format || "-")}</td>
          <td>${d.number_in_storage ?? "-"}</td>
          ${hasEpisodes ? `<td>${escapeHtml(d.episode_summary || "-")}</td>` : ""}
        </tr>
      `).join("");

      boxSetTable.innerHTML = `
        <h4>${WTE_I18N.detail.discs_in_copy_heading}</h4>
        <table>
          <thead>
            <tr><th>${WTE_I18N.detail.table_disc}</th><th>${WTE_I18N.detail.table_format}</th><th>${WTE_I18N.detail.table_storage}</th>${hasEpisodes ? `<th>${WTE_I18N.detail.table_episodes}</th>` : ""}</tr>
          </thead>
          <tbody>${tableRows}</tbody>
        </table>
      `;
      boxSetTable.style.display = "block";
    }

    panel.querySelectorAll(".copyRow[data-copy-index]").forEach(row => {
      row.addEventListener("click", () => {
        const copy = copies[Number(row.dataset.copyIndex)];
        const showDiscs = (copy.discs || []).length > 0 && (!copy.is_box_set || copy.season_number != null);
        if (showDiscs) {
          renderDiscsTable(copy);
        } else {
          renderBoxSetTable(copy);
        }
      });
    });
  }

  // ---- "Kjøpsinformasjon": ett kort pr. fysisk eksemplar (samme
  // eksemplar-liste som "Samlingsopplysninger"), med butikk/kjøpsdato/
  // pris fra physical_copy (store/purchased_at/price). Viser en egen
  // melding pr. eksemplar hvis ingenting av dette er registrert ennå. ----
  function renderPurchaseTab(item){
    const panel = document.getElementById("purchasePanel");
    const copies = item.physical_copies || [];

    if (!copies.length){
      panel.innerHTML = `<div class="emptyNote">${WTE_I18N.detail.purchase_not_in_collection}</div>`;
      return;
    }

    const cards = copies.map(c => {
      const b = formatBadge(c.format);
      const hasInfo = c.store || c.purchased_at || (c.price !== null && c.price !== undefined);
      const collId = escapeHtml(c.collection_id);
      // Butikk/kjøpsdato/pris/valuta redigeres samlet i én pop-up (ett
      // penne-ikon pr. eksemplar), samme resonnement som for
      // tittel/original tittel - se "titleGroup" lenger opp.
      const purchaseEditBtn = IS_LOGGED_IN
        ? `<button type="button" class="editPencilBtn" data-field="purchase_group" data-type="purchaseGroup" data-collection-id="${collId}" data-copy-id="${c.copy_id}" title="${escapeHtml(WTE_I18N.detail.edit_field_btn)}">✏️</button>`
        : "";

      const lines = [
        c.store ? `<span class="meta">${wteFormat(WTE_I18N.detail.purchase_store_label, escapeHtml(c.store))}</span>` : "",
        c.purchased_at ? `<span class="meta">${wteFormat(WTE_I18N.detail.purchase_date_label, escapeHtml(c.purchased_at))}</span>` : "",
        (c.price !== null && c.price !== undefined) ? `<span class="meta">${wteFormat(WTE_I18N.detail.purchase_price_label, escapeHtml(String(c.price)), escapeHtml(c.currency || "NOK"))}</span>` : "",
      ].filter(Boolean).join("");

      return `
        <div class="copyRow">
          <span class="fmt">${escapeHtml(b.label)}</span>
          ${c.is_box_set ? `<span class="boxTag">${WTE_I18N.detail.box_set_tag}</span>` : ""}
          ${hasInfo ? "" : `<span class="meta">${WTE_I18N.detail.purchase_no_info_for_copy}</span>`}
          ${lines}
          ${purchaseEditBtn}
        </div>
      `;
    }).join("");

    panel.innerHTML = `<div class="copyList">${cards}</div>`;
  }

  // ---- "Sesonger"-fane: viser kun for content_type='series' (se
  // currentItemContentId/showSeasonsTabIfSeries() under). Henter
  // nøstet sesong/episode-data via GET api.php?action=list_seasons og
  // disk-liste via action=list_discs (for disk-velgeren pr. episode) -
  // se list_seasons()/list_discs_for_content() i backend. ----
  let seasonsDiscsCache = [];
  // Husker hvilke sesonger som er kollapset mellom reloads (f.eks.
  // etter lagring av en episode), nøkkel = season_id.
  const collapsedSeasons = new Set();

  async function showSeasonsTabIfSeries(item){
    const btn = document.getElementById("seasonsTabBtn");
    if (!btn) return;
    if (item.content_type === "series") {
      btn.style.display = "";
      await loadSeasonsTab(item.content_id);
    } else {
      btn.style.display = "none";
    }
  }

  async function loadSeasonsTab(contentId){
    const panel = document.getElementById("seasonsPanel");
    panel.innerHTML = `<div class="emptyNote">${WTE_I18N.detail.set_id_searching}</div>`;
    try {
      const [seasonsRes, discsRes] = await Promise.all([
        fetch("api.php?action=list_seasons&id=" + encodeURIComponent(contentId)),
        fetch("api.php?action=list_discs&id=" + encodeURIComponent(contentId)),
      ]);
      const seasons = await seasonsRes.json();
      const discs = await discsRes.json();
      if (!seasonsRes.ok) throw new Error(seasons.error || seasons.detail || ("HTTP " + seasonsRes.status));
      seasonsDiscsCache = discsRes.ok ? discs : [];
      renderSeasonsTab(contentId, seasons);
    } catch (err) {
      panel.innerHTML = `<div class="emptyNote">${escapeHtml(WTE_I18N.detail.seasons_load_error)} (${escapeHtml(err.message)})</div>`;
    }
  }

  function renderSeasonsTab(contentId, seasons){
    const panel = document.getElementById("seasonsPanel");

    const addSeasonBtn = IS_LOGGED_IN
      ? `<button type="button" class="refreshBtn" id="btnAddSeason">${escapeHtml(WTE_I18N.detail.add_season_btn)}</button>`
      : "";

    if (!seasons.length){
      panel.innerHTML = `
        <div class="emptyNote">${WTE_I18N.detail.seasons_empty_note}</div>
        <div style="margin-top:10px;">${addSeasonBtn}</div>
      `;
      if (IS_LOGGED_IN) document.getElementById("btnAddSeason").addEventListener("click", () => openSeasonModal(contentId, null));
      return;
    }

    // Episode-disk-koblingen vises som en kompakt liste med etiketter
    // + en knapp som åpner en modal for å velge/fjerne disker (se
    // openDiscPickerModal nedenfor) - en inline multi-select tok for
    // mye plass i tabellen når episoden lå på mange disker.
    const episodeDiscBadgesHtml = (discs) => {
      if (!discs || !discs.length) {
        return `<span class="epDiscNone">${escapeHtml(WTE_I18N.detail.episode_disc_none_assigned)}</span>`;
      }
      return discs
        .map((d) => `<span class="epDiscBadge">${escapeHtml(d.label || d.disc_id)}</span>`)
        .join('<span class="epDiscSep">/</span>');
    };

    const blocks = seasons.map(season => {
      const isCollapsed = collapsedSeasons.has(season.season_id);
      const episodeCount = (season.episodes || []).length;
      const allWatched = episodeCount > 0 && (season.episodes || []).every(ep => ep.watched_flag);
      const episodeRows = (season.episodes || []).map(ep => `
        <tr data-episode-id="${escapeHtml(ep.episode_id)}">
          <td>${ep.episode_number ?? "-"}</td>
          <td>
            ${escapeHtml(ep.title || WTE_I18N.detail.untitled)}
            ${ep.overview ? `<div class="epOverview">${escapeHtml(ep.overview)}</div>` : ""}
          </td>
          <td>${escapeHtml(ep.original_air_date || "-")}</td>
          <td>${ep.runtime ? ep.runtime + WTE_I18N.detail.runtime_suffix : "-"}</td>
          <td><input type="checkbox" class="episodeWatchedCheckbox" ${ep.watched_flag ? "checked" : ""} ${IS_LOGGED_IN ? "" : "disabled"}></td>
          <td class="episodeDiscCell">
            <div class="epDiscBadges">${episodeDiscBadgesHtml(ep.discs)}</div>
            ${IS_LOGGED_IN ? `<button type="button" class="btnPickEpisodeDiscs small" title="${escapeHtml(WTE_I18N.detail.episode_disc_pick_btn)}">${escapeHtml(WTE_I18N.detail.episode_disc_pick_btn)}</button>` : ""}
          </td>
          ${IS_LOGGED_IN ? `
          <td class="episodeActions">
            <button type="button" class="btnEditEpisode" title="${escapeHtml(WTE_I18N.detail.edit_btn)}">✏️</button>
            <button type="button" class="btnDeleteEpisode" title="${escapeHtml(WTE_I18N.detail.delete_btn)}">🗑️</button>
          </td>` : ""}
        </tr>
      `).join("");

      return `
        <div class="seasonBlock${isCollapsed ? " collapsed" : ""}" data-season-id="${escapeHtml(season.season_id)}">
          <div class="seasonHeader">
            <span class="caret">▾</span>
            <span class="seasonTitle">${escapeHtml(season.title || wteFormat(WTE_I18N.detail.season_label, season.season_number))}</span>
            <span class="seasonMeta">${wteFormat(WTE_I18N.detail.season_episode_count, (season.episodes || []).length)}</span>
            ${IS_LOGGED_IN ? `
            <div class="seasonActions">
              <button type="button" class="btnToggleSeasonWatched" data-watched="${allWatched ? "1" : "0"}" ${episodeCount ? "" : "disabled"}>${escapeHtml(allWatched ? WTE_I18N.detail.season_mark_unwatched_btn : WTE_I18N.detail.season_mark_watched_btn)}</button>
              <button type="button" class="btnRefreshSeason" data-source="tmdb" data-season-type="default" title="${escapeHtml(WTE_I18N.detail.refresh_tmdb_btn)}">TMDB⟳</button>
              <button type="button" class="btnRefreshSeason" data-source="tvdb" data-season-type="default" title="${escapeHtml(WTE_I18N.detail.refresh_tvdb_btn)}">TVDB⟳</button>
              <button type="button" class="btnRefreshSeason" data-source="tvdb" data-season-type="dvd" title="${escapeHtml(WTE_I18N.detail.refresh_tvdb_dvd_btn)}">TVDB⟳(DVD)</button>
              <button type="button" class="btnEditSeason" title="${escapeHtml(WTE_I18N.detail.edit_btn)}">✏️</button>
              <button type="button" class="btnDeleteSeason" title="${escapeHtml(WTE_I18N.detail.delete_btn)}">🗑️</button>
            </div>` : ""}
          </div>
          <div class="seasonBody">
            <div class="refreshStatus seasonRefreshStatus"></div>
            <table class="episodeTable">
              <thead>
                <tr>
                  <th>#</th><th>${WTE_I18N.detail.episode_field_title}</th><th>${WTE_I18N.detail.episode_field_air_date}</th>
                  <th>${WTE_I18N.detail.episode_field_runtime}</th><th>${WTE_I18N.detail.episode_watched_label}</th><th>${WTE_I18N.detail.episode_field_disc}</th>
                  ${IS_LOGGED_IN ? "<th></th>" : ""}
                </tr>
              </thead>
              <tbody>${episodeRows}</tbody>
            </table>
            ${IS_LOGGED_IN ? `<button type="button" class="refreshBtn btnAddEpisode" style="margin-top:10px;">${escapeHtml(WTE_I18N.detail.add_episode_btn)}</button>` : ""}
          </div>
        </div>
      `;
    }).join("");

    panel.innerHTML = blocks + `<div style="margin-top:10px;">${addSeasonBtn}</div>`;

    // ---- Event-bindinger (delegert pr. sesong-blokk siden hele
    // panelet re-renders ved hver reload) ----
    panel.querySelectorAll(".seasonBlock").forEach(block => {
      const seasonId = block.dataset.seasonId;
      const season = seasons.find(s => s.season_id === seasonId);

      block.querySelector(".seasonHeader").addEventListener("click", (e) => {
        if (e.target.closest("button")) return; // ikke kollaps ved klikk på knapper i headeren
        block.classList.toggle("collapsed");
        if (block.classList.contains("collapsed")) collapsedSeasons.add(seasonId);
        else collapsedSeasons.delete(seasonId);
      });

      const editBtn = block.querySelector(".btnEditSeason");
      if (editBtn) editBtn.addEventListener("click", () => openSeasonModal(contentId, season));

      const deleteBtn = block.querySelector(".btnDeleteSeason");
      if (deleteBtn) deleteBtn.addEventListener("click", () => deleteSeason(contentId, season));

      block.querySelectorAll(".btnRefreshSeason").forEach(btn => {
        btn.addEventListener("click", () => refreshSeason(contentId, season, btn.dataset.source, block, btn.dataset.seasonType || "default"));
      });

      const toggleWatchedBtn = block.querySelector(".btnToggleSeasonWatched");
      if (toggleWatchedBtn) toggleWatchedBtn.addEventListener("click", () => toggleSeasonWatched(contentId, season, toggleWatchedBtn.dataset.watched === "1"));

      const addEpBtn = block.querySelector(".btnAddEpisode");
      if (addEpBtn) addEpBtn.addEventListener("click", () => openEpisodeModal(contentId, seasonId, null));

      block.querySelectorAll("tr[data-episode-id]").forEach(row => {
        const episodeId = row.dataset.episodeId;
        const episode = (season.episodes || []).find(e => e.episode_id === episodeId);

        const editEpBtn = row.querySelector(".btnEditEpisode");
        if (editEpBtn) editEpBtn.addEventListener("click", () => openEpisodeModal(contentId, seasonId, episode));

        const deleteEpBtn = row.querySelector(".btnDeleteEpisode");
        if (deleteEpBtn) deleteEpBtn.addEventListener("click", () => deleteEpisode(contentId, episode));

        const watchedCb = row.querySelector(".episodeWatchedCheckbox");
        if (watchedCb) watchedCb.addEventListener("change", () => toggleEpisodeWatched(contentId, episode, watchedCb));

        const pickDiscsBtn = row.querySelector(".btnPickEpisodeDiscs");
        if (pickDiscsBtn) pickDiscsBtn.addEventListener("click", () => openDiscPickerModal(contentId, episode));
      });
    });
  }

  async function refreshSeason(contentId, season, source, block, seasonType){
    const statusEl = block.querySelector(".seasonRefreshStatus");
    statusEl.className = "refreshStatus seasonRefreshStatus";
    statusEl.textContent = WTE_I18N.detail.refreshing_season;
    try {
      const res = await fetch("api.php?action=refresh_season&season_id=" + encodeURIComponent(season.season_id) + "&source=" + encodeURIComponent(source) + "&season_type=" + encodeURIComponent(seasonType || "default"), {
        method: "POST",
        headers: csrfHeader(),
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.detail || json.error || ("HTTP " + res.status));
      await loadSeasonsTab(contentId);
    } catch (err) {
      statusEl.className = "refreshStatus seasonRefreshStatus error";
      statusEl.textContent = WTE_I18N.detail.season_refresh_error_prefix + err.message;
    }
  }

  async function toggleEpisodeWatched(contentId, episode, checkboxEl){
    try {
      const res = await fetch("api.php?action=update_episode&episode_id=" + encodeURIComponent(episode.episode_id), {
        method: "POST",
        headers: { "Content-Type": "application/json", ...csrfHeader() },
        body: JSON.stringify({ watched_flag: checkboxEl.checked }),
      });
      if (!res.ok) throw new Error("HTTP " + res.status);
    } catch (err) {
      checkboxEl.checked = !checkboxEl.checked; // rull tilbake ved feil
      alert(WTE_I18N.detail.save_error_prefix + err.message);
    }
  }

  async function toggleSeasonWatched(contentId, season, currentlyAllWatched){
    const newWatchedFlag = !currentlyAllWatched;
    try {
      const res = await fetch("api.php?action=update_season_watched&season_id=" + encodeURIComponent(season.season_id), {
        method: "POST",
        headers: { "Content-Type": "application/json", ...csrfHeader() },
        body: JSON.stringify({ watched_flag: newWatchedFlag }),
      });
      if (!res.ok) { const j = await res.json().catch(() => ({})); throw new Error(j.detail || j.error || ("HTTP " + res.status)); }
      await loadSeasonsTab(contentId);
    } catch (err) {
      alert(WTE_I18N.detail.save_error_prefix + err.message);
    }
  }

  async function assignEpisodeDisc(contentId, episodeId, discIds){
    const res = await fetch("api.php?action=assign_episode_disc&episode_id=" + encodeURIComponent(episodeId), {
      method: "POST",
      headers: { "Content-Type": "application/json", ...csrfHeader() },
      body: JSON.stringify({ disc_ids: discIds }),
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(json.detail || json.error || ("HTTP " + res.status));
  }

  // ---- "Velg disker for episode"-modal ----
  const discPickerModalOverlay = document.getElementById("discPickerModalOverlay");
  const discPickerModalTitle = document.getElementById("discPickerModalTitle");
  const discPickerModalStatus = document.getElementById("discPickerModalStatus");
  const discPickerModalList = document.getElementById("discPickerModalList");
  let discPickerContentId = null;
  let discPickerEpisodeId = null;

  function openDiscPickerModal(contentId, episode){
    discPickerContentId = contentId;
    discPickerEpisodeId = episode.episode_id;
    discPickerModalTitle.textContent = wteFormat(WTE_I18N.detail.episode_disc_modal_title, episode.episode_number ?? "?");
    discPickerModalStatus.className = "refreshStatus";
    discPickerModalStatus.textContent = "";

    const selectedIds = (episode.discs || []).map(d => d.disc_id);
    if (!seasonsDiscsCache.length) {
      discPickerModalList.innerHTML = `<div class="emptyNote">${escapeHtml(WTE_I18N.detail.episode_disc_no_discs)}</div>`;
    } else {
      discPickerModalList.innerHTML = seasonsDiscsCache.map(d => `
        <label class="discPickerRow">
          <input type="checkbox" value="${escapeHtml(d.disc_id)}" ${selectedIds.includes(d.disc_id) ? "checked" : ""}>
          <span>${escapeHtml(d.label || d.disc_id)}</span>
        </label>
      `).join("");
    }
    discPickerModalOverlay.style.display = "flex";
  }

  function closeDiscPickerModal(){
    discPickerModalOverlay.style.display = "none";
  }

  document.getElementById("btnCloseDiscPickerModal").addEventListener("click", closeDiscPickerModal);
  document.getElementById("btnCancelDiscPickerModal").addEventListener("click", closeDiscPickerModal);
  discPickerModalOverlay.addEventListener("click", (e) => { if (e.target === discPickerModalOverlay) closeDiscPickerModal(); });

  document.getElementById("btnSaveDiscPickerModal").addEventListener("click", async () => {
    const discIds = Array.from(discPickerModalList.querySelectorAll("input[type=checkbox]:checked")).map(cb => cb.value);
    discPickerModalStatus.className = "refreshStatus";
    discPickerModalStatus.textContent = WTE_I18N.detail.saving_episode;
    try {
      await assignEpisodeDisc(discPickerContentId, discPickerEpisodeId, discIds);
      closeDiscPickerModal();
      await loadSeasonsTab(discPickerContentId);
    } catch (err) {
      discPickerModalStatus.className = "refreshStatus error";
      discPickerModalStatus.textContent = WTE_I18N.detail.save_error_prefix + err.message;
    }
  });

  async function deleteSeason(contentId, season){
    const ok = await showConfirmModal({
      title: WTE_I18N.detail.confirm_title,
      message: wteFormat(WTE_I18N.detail.confirm_delete_season, season.season_number),
      confirmLabel: WTE_I18N.detail.delete_btn,
    });
    if (!ok) return;
    try {
      const res = await fetch("api.php?action=delete_season&season_id=" + encodeURIComponent(season.season_id), {
        method: "POST",
        headers: csrfHeader(),
      });
      if (!res.ok) { const j = await res.json().catch(() => ({})); throw new Error(j.detail || j.error || ("HTTP " + res.status)); }
      await loadSeasonsTab(contentId);
    } catch (err) {
      alert(WTE_I18N.detail.save_error_prefix + err.message);
    }
  }

  async function deleteEpisode(contentId, episode){
    const ok = await showConfirmModal({
      title: WTE_I18N.detail.confirm_title,
      message: wteFormat(WTE_I18N.detail.confirm_delete_episode, episode.episode_number, episode.title || WTE_I18N.detail.untitled),
      confirmLabel: WTE_I18N.detail.delete_btn,
    });
    if (!ok) return;
    try {
      const res = await fetch("api.php?action=delete_episode&episode_id=" + encodeURIComponent(episode.episode_id), {
        method: "POST",
        headers: csrfHeader(),
      });
      if (!res.ok) { const j = await res.json().catch(() => ({})); throw new Error(j.detail || j.error || ("HTTP " + res.status)); }
      await loadSeasonsTab(contentId);
    } catch (err) {
      alert(WTE_I18N.detail.save_error_prefix + err.message);
    }
  }

  // ---- "Legg til/rediger sesong"-modal ----
  const seasonModalOverlay = document.getElementById("seasonModalOverlay");
  const seasonModalTitle = document.getElementById("seasonModalTitle");
  const seasonModalStatus = document.getElementById("seasonModalStatus");
  let seasonModalContentId = null;
  let seasonModalEditingId = null;

  function openSeasonModal(contentId, season){
    seasonModalContentId = contentId;
    seasonModalEditingId = season?.season_id || null;
    seasonModalTitle.textContent = season ? WTE_I18N.detail.season_modal_title_edit : WTE_I18N.detail.season_modal_title_add;
    seasonModalStatus.className = "refreshStatus";
    seasonModalStatus.textContent = "";
    document.getElementById("seasonInputNumber").value = season?.season_number ?? "";
    document.getElementById("seasonInputTitle").value = season?.title ?? "";
    document.getElementById("seasonInputAirDate").value = season?.air_date ?? "";
    document.getElementById("seasonInputOverview").value = season?.overview ?? "";
    seasonModalOverlay.style.display = "flex";
  }

  function closeSeasonModal(){
    seasonModalOverlay.style.display = "none";
  }

  document.getElementById("btnCloseSeasonModal").addEventListener("click", closeSeasonModal);
  document.getElementById("btnCancelSeasonModal").addEventListener("click", closeSeasonModal);
  seasonModalOverlay.addEventListener("click", (e) => { if (e.target === seasonModalOverlay) closeSeasonModal(); });

  document.getElementById("btnSaveSeasonModal").addEventListener("click", async () => {
    const numberVal = document.getElementById("seasonInputNumber").value;
    const titleVal = document.getElementById("seasonInputTitle").value;
    const airDateVal = document.getElementById("seasonInputAirDate").value;
    const overviewVal = document.getElementById("seasonInputOverview").value;

    if (numberVal === ""){
      seasonModalStatus.className = "refreshStatus error";
      seasonModalStatus.textContent = WTE_I18N.detail.season_field_number;
      return;
    }

    const body = {
      season_number: parseInt(numberVal, 10),
      title: titleVal === "" ? null : titleVal,
      air_date: airDateVal === "" ? null : airDateVal,
      overview: overviewVal === "" ? null : overviewVal,
    };

    seasonModalStatus.className = "refreshStatus";
    seasonModalStatus.textContent = WTE_I18N.detail.saving_season;

    try {
      const url = seasonModalEditingId
        ? "api.php?action=update_season&season_id=" + encodeURIComponent(seasonModalEditingId)
        : "api.php?action=create_season&id=" + encodeURIComponent(seasonModalContentId);

      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json", ...csrfHeader() },
        body: JSON.stringify(body),
      });
      const json = await res.json();
      if (!res.ok) {
        const detail = Array.isArray(json.detail) ? json.detail.map(d => d.msg).join(", ") : (json.detail || json.error);
        throw new Error(detail || ("HTTP " + res.status));
      }
      closeSeasonModal();
      await loadSeasonsTab(seasonModalContentId);
    } catch (err) {
      seasonModalStatus.className = "refreshStatus error";
      seasonModalStatus.textContent = WTE_I18N.detail.save_error_prefix + err.message;
    }
  });

  // ---- "Legg til/rediger episode"-modal ----
  const episodeModalOverlay = document.getElementById("episodeModalOverlay");
  const episodeModalTitle = document.getElementById("episodeModalTitle");
  const episodeModalStatus = document.getElementById("episodeModalStatus");
  let episodeModalContentId = null;
  let episodeModalSeasonId = null;
  let episodeModalEditingId = null;

  function openEpisodeModal(contentId, seasonId, episode){
    episodeModalContentId = contentId;
    episodeModalSeasonId = seasonId;
    episodeModalEditingId = episode?.episode_id || null;
    episodeModalTitle.textContent = episode ? WTE_I18N.detail.episode_modal_title_edit : WTE_I18N.detail.episode_modal_title_add;
    episodeModalStatus.className = "refreshStatus";
    episodeModalStatus.textContent = "";
    document.getElementById("episodeInputNumber").value = episode?.episode_number ?? "";
    document.getElementById("episodeInputTitle").value = episode?.title ?? "";
    document.getElementById("episodeInputAirDate").value = episode?.original_air_date ?? "";
    document.getElementById("episodeInputRuntime").value = episode?.runtime ?? "";
    document.getElementById("episodeInputOverview").value = episode?.overview ?? "";
    episodeModalOverlay.style.display = "flex";
  }

  function closeEpisodeModal(){
    episodeModalOverlay.style.display = "none";
  }

  document.getElementById("btnCloseEpisodeModal").addEventListener("click", closeEpisodeModal);
  document.getElementById("btnCancelEpisodeModal").addEventListener("click", closeEpisodeModal);
  episodeModalOverlay.addEventListener("click", (e) => { if (e.target === episodeModalOverlay) closeEpisodeModal(); });

  document.getElementById("btnSaveEpisodeModal").addEventListener("click", async () => {
    const numberVal = document.getElementById("episodeInputNumber").value;
    const titleVal = document.getElementById("episodeInputTitle").value;
    const airDateVal = document.getElementById("episodeInputAirDate").value;
    const runtimeVal = document.getElementById("episodeInputRuntime").value;
    const overviewVal = document.getElementById("episodeInputOverview").value;

    if (numberVal === ""){
      episodeModalStatus.className = "refreshStatus error";
      episodeModalStatus.textContent = WTE_I18N.detail.episode_field_number;
      return;
    }

    const body = episodeModalEditingId ? {
      title: titleVal === "" ? null : titleVal,
      original_air_date: airDateVal === "" ? null : airDateVal,
      runtime: runtimeVal === "" ? null : parseInt(runtimeVal, 10),
      overview: overviewVal === "" ? null : overviewVal,
    } : {
      episode_number: parseInt(numberVal, 10),
      title: titleVal === "" ? null : titleVal,
      original_air_date: airDateVal === "" ? null : airDateVal,
      runtime: runtimeVal === "" ? null : parseInt(runtimeVal, 10),
      overview: overviewVal === "" ? null : overviewVal,
    };

    episodeModalStatus.className = "refreshStatus";
    episodeModalStatus.textContent = WTE_I18N.detail.saving_episode;

    try {
      const url = episodeModalEditingId
        ? "api.php?action=update_episode&episode_id=" + encodeURIComponent(episodeModalEditingId)
        : "api.php?action=create_episode&season_id=" + encodeURIComponent(episodeModalSeasonId);

      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json", ...csrfHeader() },
        body: JSON.stringify(body),
      });
      const json = await res.json();
      if (!res.ok) {
        const detail = Array.isArray(json.detail) ? json.detail.map(d => d.msg).join(", ") : (json.detail || json.error);
        throw new Error(detail || ("HTTP " + res.status));
      }
      closeEpisodeModal();
      await loadSeasonsTab(episodeModalContentId);
    } catch (err) {
      episodeModalStatus.className = "refreshStatus error";
      episodeModalStatus.textContent = WTE_I18N.detail.save_error_prefix + err.message;
    }
  });

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

  // Viser andre filmer i hver filmgruppe filmen tilhører (item.groups -
  // en film kan nå tilhøre 0, 1 eller flere grupper samtidig, se
  // get_groups_for_content() i backend). Én boks pr. gruppe, hver med
  // sin egen dra-og-slipp-sortering (sortModeActive-settet under
  // holder styr på hvilke boksers sortering som er aktivert - flere
  // kan være aktive samtidig, uavhengig av hverandre).
  const sortModeActiveGroupIds = new Set();

  function groupMoviesWithSelf(item, group){
    const self = {
      content_id: item.content_id,
      title: item.title,
      cover_image: item.cover_image,
      group_sort_order: group.sort_order,
      first_release: item.first_release,
      isCurrent: true,
    };
    const siblings = (group.group_movies || []).map(m => ({ ...m, isCurrent: false }));
    return [self, ...siblings].sort((a, b) => {
      const aNull = a.group_sort_order == null;
      const bNull = b.group_sort_order == null;
      if (aNull !== bNull) return aNull ? 1 : -1;
      if (!aNull && a.group_sort_order !== b.group_sort_order) return a.group_sort_order - b.group_sort_order;
      return (a.first_release || "") < (b.first_release || "") ? -1 : 1;
    });
  }

  function renderGroupMovies(item){
    const container = document.getElementById("groupMoviesSections");
    const groups = item.groups || [];

    if (!groups.length) {
      container.innerHTML = "";
      return;
    }

    container.innerHTML = groups.map((group) => {
      const movies = groupMoviesWithSelf(item, group);
      const heading = wteFormat(WTE_I18N.detail.group_movies_heading, group.group_name);
      const sortActive = sortModeActiveGroupIds.has(group.group_id);
      const cardsHtml = movies.map((m) => {
        const cover = m.cover_image
          ? `<img class="groupMovieCover" loading="lazy" decoding="async" src="${escapeHtml(m.cover_image)}" alt="${escapeHtml(m.title || WTE_I18N.detail.untitled)}">`
          : `<div class="groupMovieCover"></div>`;
        // Rekkefølge-tallet vises som en egen sirkel-badge OVENPÅ coveret
        // (ikke foran tittelen) - "#4 · Politiskolen 4" ble lett forvekslet
        // med filmtittelen selv når tittelen allerede inneholder et tall.
        const orderBadge = m.group_sort_order != null
          ? `<span class="groupOrderBadge" title="${escapeHtml(WTE_I18N.detail.group_order_badge_title)}">${m.group_sort_order}</span>`
          : "";
        const title = `${escapeHtml(m.title || WTE_I18N.detail.untitled)}${m.isCurrent ? ` (${WTE_I18N.detail.group_movies_current_tag})` : ""}`;
        const cardClass = "groupMovieCard" + (m.isCurrent ? " current" : "");
        // Selve filmen (denne siden) er ikke en lenke - man er jo allerede her.
        const tag = m.isCurrent ? "div" : "a";
        const href = m.isCurrent ? "" : ` href="detail.php?id=${encodeURIComponent(m.content_id)}"`;
        return `<${tag} class="${cardClass}" data-id="${escapeHtml(m.content_id)}" data-first-release="${escapeHtml(m.first_release || "")}"${href}><div class="groupMovieCoverWrap">${cover}${orderBadge}</div><div class="groupMovieTitle">${title}</div></${tag}>`;
      }).join("");

      const controls = IS_LOGGED_IN ? `
        <button type="button" class="groupSortToggleBtn" data-action="sort-by-year" data-group-id="${group.group_id}">${escapeHtml(WTE_I18N.detail.group_sort_by_year_btn)}</button>
        <button type="button" class="groupSortToggleBtn${sortActive ? " active" : ""}" data-action="toggle-sort" data-group-id="${group.group_id}">${escapeHtml(sortActive ? WTE_I18N.detail.group_sort_toggle_done_btn : WTE_I18N.detail.group_sort_toggle_btn)}</button>
        <span class="groupSortStatus" data-group-status="${group.group_id}"></span>
      ` : "";

      return `<div class="sourcesBox groupMoviesBox${sortActive ? " sortModeActive" : ""}" data-group-id="${group.group_id}">
        <h3>${escapeHtml(heading)}${controls}</h3>
        <div class="groupMoviesList" data-group-movies-list="${group.group_id}">${cardsHtml}</div>
      </div>`;
    }).join("");

    applySortModeAttrs();
  }

  // "Lignende filmer": separat, ikke-blokkerende henting (se
  // get_similar_content() i media_catalog.py / api.php?action=similar)
  // - lastes etter renderDetail() er ferdig, slik at selve detaljsiden
  // ikke venter på denne ekstra spørringen. Skjuler hele seksjonen
  // (tom innerHTML) hvis det ikke finnes noen treff.
  async function loadSimilarMovies(id){
    const container = document.getElementById("similarMoviesSection");
    if (!container || !id) return;
    try {
      const res = await fetch("api.php?action=similar&id=" + encodeURIComponent(id));
      const movies = await res.json();
      if (!Array.isArray(movies) || !movies.length) {
        container.innerHTML = "";
        return;
      }

      const cardsHtml = movies.map((m) => {
        const cover = m.cover_image
          ? `<img class="groupMovieCover" loading="lazy" decoding="async" src="${escapeHtml(m.cover_image)}" alt="${escapeHtml(m.title || WTE_I18N.detail.untitled)}">`
          : `<div class="groupMovieCover"></div>`;
        const title = escapeHtml(m.title || WTE_I18N.detail.untitled);
        return `<a class="groupMovieCard" href="detail.php?id=${encodeURIComponent(m.content_id)}"><div class="groupMovieCoverWrap">${cover}</div><div class="groupMovieTitle">${title}</div></a>`;
      }).join("");

      container.innerHTML = `<div class="sourcesBox groupMoviesBox">
        <h3>${escapeHtml(WTE_I18N.detail.similar_movies_heading)}</h3>
        <div class="groupMoviesList">${cardsHtml}</div>
      </div>`;
    } catch (err) {
      // Stille feil her - "lignende filmer" er en ekstra, ikke-kritisk
      // seksjon, så vi vil ikke vise en feilmelding midt på detaljsiden
      // bare fordi denne ene tilleggsspørringen feilet.
      container.innerHTML = "";
    }
  }

  // Dra-og-slipp-sortering: aktiveres/deaktiveres pr. gruppe-boks med
  // "Sorter"-knappen. Rekkefølgen på ALLE kortene i den boksen (inkl.
  // denne filmen selv) sendes samlet til
  // PATCH /media/groups/{group_id}/reorder (via api.php?action=reorder_group)
  // hver gang et kort slippes.
  let draggedCard = null;

  function applySortModeAttrs(){
    document.querySelectorAll(".groupMoviesBox").forEach((box) => {
      const groupId = box.dataset.groupId;
      const active = sortModeActiveGroupIds.has(Number(groupId));
      box.querySelectorAll(".groupMovieCard").forEach((card) => {
        card.draggable = active;
        card.removeEventListener("click", preventNavDuringSort);
        if (active) card.addEventListener("click", preventNavDuringSort);
      });
    });
  }

  function preventNavDuringSort(e){ e.preventDefault(); }

  const groupMoviesSectionsEl = document.getElementById("groupMoviesSections");

  groupMoviesSectionsEl.addEventListener("click", async (e) => {
    const toggleBtn = e.target.closest("[data-action='toggle-sort']");
    const yearBtn = e.target.closest("[data-action='sort-by-year']");

    if (toggleBtn) {
      const groupId = Number(toggleBtn.dataset.groupId);
      if (sortModeActiveGroupIds.has(groupId)) {
        sortModeActiveGroupIds.delete(groupId);
      } else {
        sortModeActiveGroupIds.add(groupId);
      }
      renderGroupMovies(currentItem);
      return;
    }

    if (yearBtn) {
      const groupId = Number(yearBtn.dataset.groupId);
      const list = groupMoviesSectionsEl.querySelector(`[data-group-movies-list="${groupId}"]`);
      if (!list) return;
      const cards = [...list.querySelectorAll(".groupMovieCard")];
      cards.sort((a, b) => {
        const aDate = a.dataset.firstRelease || "";
        const bDate = b.dataset.firstRelease || "";
        if (!aDate && !bDate) return 0;
        if (!aDate) return 1;
        if (!bDate) return -1;
        return aDate < bDate ? -1 : (aDate > bDate ? 1 : 0);
      });
      cards.forEach(card => list.appendChild(card));
      await saveGroupOrder(groupId);
    }
  });

  groupMoviesSectionsEl.addEventListener("dragstart", (e) => {
    const card = e.target.closest(".groupMovieCard");
    const box = e.target.closest(".groupMoviesBox");
    if (!card || !box || !box.classList.contains("sortModeActive")) return;
    draggedCard = card;
    card.classList.add("dragging");
    e.dataTransfer.effectAllowed = "move";
  });
  groupMoviesSectionsEl.addEventListener("dragend", async (e) => {
    const card = e.target.closest(".groupMovieCard");
    const box = e.target.closest(".groupMoviesBox");
    if (card) card.classList.remove("dragging");
    if (draggedCard && box) {
      draggedCard = null;
      await saveGroupOrder(Number(box.dataset.groupId));
    }
  });

  // Finner kortet som ligger nærmest musepekeren (2D-avstand, ikke bare
  // venstre/høyre) og om det nye kortet skal settes inn før eller etter
  // det - dette håndterer at kortene brytes over flere rader
  // (flex-wrap) mye bedre enn en enkel venstre/høyre-sjekk av ett og
  // ett kort. Det dragede kortet flyttes fortløpende mens man drar
  // (samme mønster som SortableJS/de fleste dra-og-slipp-biblioteker),
  // slik at man ser resultatet med én gang - man trenger ikke treffe
  // nøyaktig og prøve flere ganger.
  function findClosestCard(listEl, x, y){
    const cards = [...listEl.querySelectorAll(".groupMovieCard:not(.dragging)")];
    let closest = null;
    let closestDistance = Infinity;
    for (const card of cards) {
      const box = card.getBoundingClientRect();
      const centerX = box.left + box.width / 2;
      const centerY = box.top + box.height / 2;
      const distance = Math.hypot(x - centerX, y - centerY);
      if (distance < closestDistance) {
        closestDistance = distance;
        closest = { card, centerX };
      }
    }
    return closest;
  }

  groupMoviesSectionsEl.addEventListener("dragover", (e) => {
    const box = e.target.closest(".groupMoviesBox");
    if (!box || !box.classList.contains("sortModeActive") || !draggedCard) return;
    const list = box.querySelector(".groupMoviesList");
    if (!list || !list.contains(draggedCard) && draggedCard.closest(".groupMoviesBox") !== box) return;
    e.preventDefault();
    const closest = findClosestCard(list, e.clientX, e.clientY);
    if (!closest) return;
    const insertAfter = e.clientX > closest.centerX;
    const targetSibling = insertAfter ? closest.card.nextElementSibling : closest.card;
    if (targetSibling !== draggedCard) {
      list.insertBefore(draggedCard, insertAfter ? closest.card.nextElementSibling : closest.card);
    }
  });
  groupMoviesSectionsEl.addEventListener("drop", (e) => {
    const box = e.target.closest(".groupMoviesBox");
    if (!box || !box.classList.contains("sortModeActive")) return;
    e.preventDefault();
    // Selve flyttingen/lagringen skjer allerede fortløpende i dragover/
    // dragend - drop trenger bare å hindre nettleserens standard-
    // oppførsel (f.eks. å åpne en lenke/fil).
  });

  async function saveGroupOrder(groupId){
    if (!groupId) return;
    const statusEl = groupMoviesSectionsEl.querySelector(`[data-group-status="${groupId}"]`);
    const list = groupMoviesSectionsEl.querySelector(`[data-group-movies-list="${groupId}"]`);
    if (!list) return;
    const ids = [...list.querySelectorAll(".groupMovieCard")].map(c => c.dataset.id);
    if (statusEl) statusEl.textContent = WTE_I18N.detail.group_sort_saving;
    try {
      // keepalive: true - sikrer at forespørselen fullføres selv om
      // brukeren rekker å navigere bort fra siden rett etter at
      // draget er sluppet (samme mekanisme som brukes til
      // analytics-kall ved side-avslutning) - uten denne kunne
      // rekkefølgen se ut til å bli lagret visuelt, men aldri faktisk
      // nå fram til serveren.
      const res = await fetch(`api.php?action=reorder_group&group_id=${encodeURIComponent(groupId)}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json", ...csrfHeader() },
        body: JSON.stringify({ content_ids: ids }),
        keepalive: true,
      });
      const json = await res.json();
      if (!res.ok || json.error) throw new Error(json.error || res.statusText);
      // Oppdater tall-badgene i DOM-en uten å re-fetche hele siden.
      list.querySelectorAll(".groupMovieCard").forEach((card, i) => {
        let badge = card.querySelector(".groupOrderBadge");
        if (!badge) {
          badge = document.createElement("span");
          badge.className = "groupOrderBadge";
          badge.title = WTE_I18N.detail.group_order_badge_title;
          card.querySelector(".groupMovieCoverWrap")?.appendChild(badge);
        }
        badge.textContent = i + 1;
      });
      if (statusEl) {
        statusEl.textContent = WTE_I18N.detail.group_sort_saved;
        setTimeout(() => { statusEl.textContent = ""; }, 1500);
      }
    } catch (err) {
      if (statusEl) statusEl.textContent = "";
      alert(WTE_I18N.detail.group_sort_error_prefix + err.message);
    }
  }

  // Holder siste innlastede item tilgjengelig for penne-ikon-
  // redigeringen (se editPencilBtn-klikkhandleren lenger ned) - slik
  // slipper vi å re-fetche eller parse DOM-tekst for å finne "nåværende
  // verdi" når redigeringsmodalen åpnes.
  let currentItem = null;

  function renderDetail(item){
    currentItem = item;
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
    renderWatchedCard(item);
    document.getElementById("fGroup").textContent = (item.groups && item.groups.length)
      ? item.groups.map(g => g.group_name).join(", ")
      : "-";

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

    // "+"-knappene lot deg tidligere bare SETTE en TMDB/TVDB-id når
    // kilden manglet fra før - nå er de alltid synlige og tillater
    // også å ENDRE en allerede satt id (f.eks. hvis man ved en feil
    // har koblet til feil film/serie) - samme applyExternalId()-flyt
    // håndterer begge tilfeller (ren INSERT vs. upsert), se
    // set_content_external_id() i backend.
    const btnSetTmdbId = document.getElementById("btnSetTmdbId");
    const btnSetTvdbId = document.getElementById("btnSetTvdbId");
    if (btnSetTmdbId) {
      const has = !!tmdbSource?.external_id;
      btnSetTmdbId.textContent = has ? "✏️" : "➕";
      btnSetTmdbId.title = has ? WTE_I18N.detail.change_id_btn : WTE_I18N.detail.set_id_btn;
    }
    if (btnSetTvdbId) {
      const has = !!tvdbSource?.external_id;
      btnSetTvdbId.textContent = has ? "✏️" : "➕";
      btnSetTvdbId.title = has ? WTE_I18N.detail.change_id_btn : WTE_I18N.detail.set_id_btn;
    }

    // Knappene for å bytte data fra TMDB/TVDB er bare aktive når kilden
    // finnes fra før (content_external_source-raden må allerede finnes -
    // se update_content_external_source() i backend). Knappene finnes
    // ikke i det hele tatt i DOM-en for ikke-innloggede besøkende (se
    // $isLoggedIn i HTML-delen over) - derfor null-sjekk her.
    const btnTmdb = document.getElementById("btnRefreshTmdb");
    const btnTvdb = document.getElementById("btnRefreshTvdb");
    if (btnTmdb) {
      btnTmdb.disabled = !tmdbSource?.external_id;
      btnTmdb.dataset.externalId = tmdbSource?.external_id || "";
    }
    if (btnTvdb) {
      btnTvdb.disabled = !tvdbSource?.external_id;
      btnTvdb.dataset.externalId = tvdbSource?.external_id || "";
    }

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
    renderPurchaseTab(item);
    renderLockIcons(item);
    renderGroupMovies(item);
    loadSimilarMovies(item.content_id);
    showSeasonsTabIfSeries(item);

    // "Bytt cover"-knappen krever bare at content finnes (contentId er
    // allerede kjent fra URL-en) - ingen ekstra betingelse. Finnes ikke
    // i DOM-en for ikke-innloggede besøkende, derfor null-sjekk.
    const btnChooseCover = document.getElementById("btnChooseCover");
    if (btnChooseCover) btnChooseCover.disabled = false;

    detailStatus.style.display = "none";
    detailLayout.style.display = "grid";
    tabSection.style.display = "block";
  }

  // ---- "Sett"-kort + sett-datoer-modal (rewatch-historikk, per
  // innlogget bruker) - kun for filmer (content_type='movie'), se
  // content_watched/watched_flag/watched_dates i list_content()/
  // get_content_by_id() (media_catalog.py). Samme mønster som
  // watchedDatesModalOverlay i index.php (Mine filmer).
  function watchedBadgeLabel(item){
    const n = (item.watched_dates || []).length;
    if (n === 0) return WTE_I18N.detail.watched_dates_none;
    return wteFormat(WTE_I18N.detail.watched_dates_count, n);
  }

  function renderWatchedCard(item){
    const card = document.getElementById("fWatchedCard");
    if (!card) return;
    if (item.content_type !== "movie") {
      card.style.display = "none";
      return;
    }
    card.style.display = "";
    document.getElementById("fWatched").textContent = watchedBadgeLabel(item);
  }

  const watchedDatesModalOverlay = document.getElementById("watchedDatesModalOverlay");
  const watchedDatesModalStatus = document.getElementById("watchedDatesModalStatus");
  const watchedDatesModalList = document.getElementById("watchedDatesModalList");
  const watchedDatesModalNewDate = document.getElementById("watchedDatesModalNewDate");
  const btnCloseWatchedDatesModal = document.getElementById("btnCloseWatchedDatesModal");
  const btnAddWatchedDate = document.getElementById("btnAddWatchedDate");
  const btnManageWatchedDates = document.getElementById("btnManageWatchedDates");

  function openWatchedDatesModal(){
    if (!currentItem) return;
    watchedDatesModalStatus.textContent = "";
    watchedDatesModalStatus.classList.remove("error");
    watchedDatesModalNewDate.value = new Date().toISOString().slice(0, 10);
    renderWatchedDatesModalList(currentItem.watched_dates || []);
    watchedDatesModalOverlay.style.display = "flex";
  }

  function closeWatchedDatesModal(){
    watchedDatesModalOverlay.style.display = "none";
  }

  function renderWatchedDatesModalList(dates){
    if (dates.length === 0) {
      watchedDatesModalList.innerHTML = `<li style="color:var(--muted); font-size:13px;">${escapeHtml(WTE_I18N.detail.watched_dates_empty)}</li>`;
      return;
    }
    watchedDatesModalList.innerHTML = dates.map(d => `
      <li style="display:flex; align-items:center; gap:8px; padding:4px 0; border-bottom:1px solid var(--line,#262b38);">
        <input type="date" class="watchedDateEditInput" data-watched-id="${d.watched_id}" value="${escapeHtml(d.date)}" style="flex:1; padding:6px; border-radius:7px; background:var(--bg,#0d0f14); color:var(--text); border:1px solid var(--line,#262b38);">
        <button type="button" class="refreshBtn watchedDateDeleteBtn" data-watched-id="${d.watched_id}">&times;</button>
      </li>
    `).join("");
    watchedDatesModalList.querySelectorAll(".watchedDateEditInput").forEach(el => {
      el.addEventListener("change", () => updateWatchedDate(el.dataset.watchedId, el.value));
    });
    watchedDatesModalList.querySelectorAll(".watchedDateDeleteBtn").forEach(el => {
      el.addEventListener("click", () => deleteWatchedDate(el.dataset.watchedId));
    });
  }

  function applyWatchedDatesResult(result){
    if (!currentItem) return;
    currentItem.watched_flag = result.watched_flag;
    currentItem.watched_dates = result.watched_dates;
    renderWatchedDatesModalList(currentItem.watched_dates);
    renderWatchedCard(currentItem);
  }

  async function addWatchedDate(dateOverride){
    if (!currentItem) return false;
    watchedDatesModalStatus.textContent = "";
    watchedDatesModalStatus.classList.remove("error");
    try {
      const res = await fetch("api.php?action=add_content_watched_date&id=" + encodeURIComponent(currentItem.content_id), {
        method: "POST",
        headers: { "Content-Type": "application/json", ...csrfHeader() },
        body: JSON.stringify({ watched_date: dateOverride || watchedDatesModalNewDate.value || null }),
      });
      if (!res.ok) throw new Error("HTTP " + res.status);
      applyWatchedDatesResult(await res.json());
      return true;
    } catch (err) {
      watchedDatesModalStatus.textContent = WTE_I18N.detail.watched_dates_error;
      watchedDatesModalStatus.classList.add("error");
      return false;
    }
  }

  // "Sett i dag"-snarvei: lagrer dagens dato direkte og lukker modalen
  // automatisk ved suksess (i stedet for å kreve "velg dato" + "legg til").
  async function addWatchedDateToday(){
    const ok = await addWatchedDate(new Date().toISOString().slice(0, 10));
    if (ok) closeWatchedDatesModal();
  }

  async function updateWatchedDate(watchedId, newDate){
    if (!currentItem || !newDate) return;
    watchedDatesModalStatus.textContent = "";
    watchedDatesModalStatus.classList.remove("error");
    try {
      const res = await fetch("api.php?action=update_content_watched_date&id=" + encodeURIComponent(currentItem.content_id) + "&watched_id=" + encodeURIComponent(watchedId), {
        method: "POST",
        headers: { "Content-Type": "application/json", ...csrfHeader() },
        body: JSON.stringify({ watched_date: newDate }),
      });
      if (!res.ok) throw new Error("HTTP " + res.status);
      applyWatchedDatesResult(await res.json());
    } catch (err) {
      watchedDatesModalStatus.textContent = WTE_I18N.detail.watched_dates_error;
      watchedDatesModalStatus.classList.add("error");
    }
  }

  async function deleteWatchedDate(watchedId){
    if (!currentItem) return;
    watchedDatesModalStatus.textContent = "";
    watchedDatesModalStatus.classList.remove("error");
    try {
      const res = await fetch("api.php?action=delete_content_watched_date&id=" + encodeURIComponent(currentItem.content_id) + "&watched_id=" + encodeURIComponent(watchedId), {
        method: "POST",
        headers: { "Content-Type": "application/json", ...csrfHeader() },
      });
      if (!res.ok) throw new Error("HTTP " + res.status);
      applyWatchedDatesResult(await res.json());
    } catch (err) {
      watchedDatesModalStatus.textContent = WTE_I18N.detail.watched_dates_error;
      watchedDatesModalStatus.classList.add("error");
    }
  }

  if (btnManageWatchedDates) {
    btnManageWatchedDates.addEventListener("click", openWatchedDatesModal);
    btnCloseWatchedDatesModal.addEventListener("click", closeWatchedDatesModal);
    watchedDatesModalOverlay.addEventListener("click", (e) => {
      if (e.target === watchedDatesModalOverlay) closeWatchedDatesModal();
    });
    btnAddWatchedDate.addEventListener("click", () => addWatchedDate());
    const btnWatchedToday = document.getElementById("btnWatchedToday");
    if (btnWatchedToday) btnWatchedToday.addEventListener("click", addWatchedDateToday);
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

    const allButtons = [document.getElementById("btnRefreshTmdb"), document.getElementById("btnRefreshTvdb")].filter(Boolean);
    allButtons.forEach(b => b.disabled = true);
    statusEl.className = "refreshStatus";
    statusEl.textContent = wteFormat(WTE_I18N.detail.fetching_from, source.toUpperCase());

    try {
      const refreshRes = await fetch(
        "api.php?action=refresh_external_source&source=" + encodeURIComponent(source) +
          "&external_id=" + encodeURIComponent(externalId),
        { method: "POST", headers: csrfHeader() }
      );
      const refreshJson = await refreshRes.json();
      if (!refreshRes.ok || refreshJson.error) {
        throw new Error(refreshJson.error || refreshJson.detail || ("HTTP " + refreshRes.status));
      }

      statusEl.textContent = wteFormat(WTE_I18N.detail.merging_from, source.toUpperCase());

      const mergeRes = await fetch(
        "api.php?action=merge_external_source&source=" + encodeURIComponent(source) +
          "&external_id=" + encodeURIComponent(externalId),
        { method: "POST", headers: csrfHeader() }
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

  // Knappene finnes ikke i DOM-en for ikke-innloggede besøkende (se
  // $isLoggedIn i HTML-delen over) - derfor null-sjekk før addEventListener.
  document.getElementById("btnRefreshTmdb")?.addEventListener("click", (e) => refreshSource("tmdb", e.currentTarget));
  document.getElementById("btnRefreshTvdb")?.addEventListener("click", (e) => refreshSource("tvdb", e.currentTarget));

  // "Sett TMDB/TVDB-id"-modal: søk i TMDB/TVDB (faner) og velg riktig
  // treff fra en liste i stedet for å skrive inn en numerisk id blindt
  // via prompt(). Selve tildelingen (+ konflikt/merge-håndtering) skjer
  // i applyExternalId() under, som kalles når brukeren klikker et
  // søkeresultat.
  const setIdModalOverlay = document.getElementById("setIdModalOverlay");
  const setIdModalTitle = document.getElementById("setIdModalTitle");
  const setIdSearchInput = document.getElementById("setIdSearchInput");
  const setIdContentType = document.getElementById("setIdContentType");
  const setIdModalStatus = document.getElementById("setIdModalStatus");
  const setIdResultsList = document.getElementById("setIdResultsList");
  const setIdTabBtns = document.querySelectorAll(".setIdTabBtn");
  let setIdActiveSource = "tmdb";
  let setIdSearchTimeout = null;

  function openSetIdModal(source){
    setIdActiveSource = source;
    setIdTabBtns.forEach(b => b.classList.toggle("active", b.dataset.source === source));
    setIdModalTitle.textContent = wteFormat(WTE_I18N.detail.set_id_modal_title, currentItem?.title || "");
    // Foreslår samme type som filmen allerede er registrert som, men
    // brukeren kan overstyre - f.eks. hvis en Plex-import feilaktig
    // har satt content_type=movie for noe som egentlig er en tv-serie.
    setIdContentType.value = currentItem?.content_type === "series" ? "tv" : "movie";
    setIdSearchInput.value = currentItem?.title || "";
    setIdResultsList.innerHTML = "";
    setIdModalStatus.className = "refreshStatus";
    setIdModalStatus.textContent = "";
    setIdModalOverlay.style.display = "flex";
    setIdSearchInput.focus();
    setIdSearchInput.select();
    runSetIdSearch();
  }

  function closeSetIdModal(){
    setIdModalOverlay.style.display = "none";
    clearTimeout(setIdSearchTimeout);
  }

  document.getElementById("btnSetTmdbId")?.addEventListener("click", () => openSetIdModal("tmdb"));
  document.getElementById("btnSetTvdbId")?.addEventListener("click", () => openSetIdModal("tvdb"));
  document.getElementById("btnCloseSetIdModal").addEventListener("click", closeSetIdModal);
  setIdModalOverlay.addEventListener("click", (e) => { if (e.target === setIdModalOverlay) closeSetIdModal(); });

  setIdContentType.addEventListener("change", runSetIdSearch);

  setIdTabBtns.forEach(btn => {
    btn.addEventListener("click", () => {
      setIdActiveSource = btn.dataset.source;
      setIdTabBtns.forEach(b => b.classList.toggle("active", b === btn));
      runSetIdSearch();
    });
  });

  setIdSearchInput.addEventListener("input", () => {
    clearTimeout(setIdSearchTimeout);
    setIdSearchTimeout = setTimeout(runSetIdSearch, 400);
  });

  // Teller som økes for hvert nye søk, slik at et tregere/eldre svar
  // ikke kan "vinne" kappløpet og overskrive resultatlisten etter at
  // et nyere søk allerede har kommet tilbake (f.eks. modalen søker
  // automatisk på filmens/seriens nåværende tittel med en gang den
  // åpnes - hvis brukeren rekker å skrive inn et nytt søkeord og få
  // svar før det første søket er ferdig, skal ikke det første søkets
  // (nå utdaterte) treff vises).
  let setIdSearchSeq = 0;

  async function runSetIdSearch(){
    const mySeq = ++setIdSearchSeq;
    const query = setIdSearchInput.value.trim();
    setIdResultsList.innerHTML = "";
    if (query.length < 2){
      setIdModalStatus.className = "refreshStatus";
      setIdModalStatus.textContent = WTE_I18N.detail.set_id_search_min_chars;
      return;
    }

    setIdModalStatus.className = "refreshStatus";
    setIdModalStatus.textContent = WTE_I18N.detail.set_id_searching;

    try {
      const contentType = setIdContentType.value === "tv" ? "tv" : "movie";
      const res = await fetch(
        "api.php?action=search_external_source&source=" + encodeURIComponent(setIdActiveSource) +
          "&query=" + encodeURIComponent(query) +
          "&content_type=" + encodeURIComponent(contentType)
      );
      const json = await res.json();
      if (mySeq !== setIdSearchSeq) return; // et nyere søk er allerede startet/vist
      if (!res.ok || json.error) throw new Error(json.error || json.detail || ("HTTP " + res.status));

      const results = json.results || [];
      if (!results.length){
        setIdModalStatus.textContent = WTE_I18N.detail.set_id_no_results;
        return;
      }
      setIdModalStatus.textContent = "";
      renderSetIdResults(results);
    } catch (err) {
      if (mySeq !== setIdSearchSeq) return;
      setIdModalStatus.className = "refreshStatus error";
      setIdModalStatus.textContent = wteFormat(WTE_I18N.detail.refresh_error_prefix, setIdActiveSource.toUpperCase(), err.message);
    }
  }

  function renderSetIdResults(results){
    setIdResultsList.innerHTML = results.map(r => `
      <div class="setIdResultItem" data-external-id="${escapeHtml(r.external_id)}">
        <div class="poster" style="${r.poster_url ? `background-image:url('${r.poster_url.replace(/'/g, "%27")}')` : ""}"></div>
        <div class="meta">
          <div class="title">${escapeHtml(r.title || "?")}</div>
          <div class="year">${escapeHtml(r.year || "")} · ${escapeHtml(r.external_id)}</div>
          ${r.overview ? `<div class="overview">${escapeHtml(r.overview.length > 160 ? r.overview.slice(0, 157) + "…" : r.overview)}</div>` : ""}
        </div>
      </div>
    `).join("");

    setIdResultsList.querySelectorAll(".setIdResultItem").forEach(el => {
      el.addEventListener("click", () => applyExternalId(setIdActiveSource, el.dataset.externalId));
    });
  }

  // Selve tildelingen: kalles når brukeren klikker et søkeresultat i
  // "Sett TMDB/TVDB-id"-modalen. Håndterer også 409-konflikt (IDen
  // tilhører allerede en annen film) med en confirm()-dialog for å
  // slå filmene sammen, samme mønster som "fjern fra filmgruppe"
  // lenger ned i filen.
  async function applyExternalId(source, externalId){
    const statusEl = document.getElementById("refreshStatus");

    async function callSet(confirmMerge){
      // content_type sendes med slik backend vet om brukeren faktisk
      // søkte som "Film" eller "Serie" i modalen - overstyrer radens
      // egen (muligens feilimporterte, f.eks. fra Plex) content_type,
      // se set_content_external_id() i media_catalog.py.
      const contentType = setIdContentType.value === "tv" ? "tv" : "movie";
      const res = await fetch(
        "api.php?action=set_external_id&content_id=" + encodeURIComponent(contentId) +
          "&source=" + encodeURIComponent(source) +
          "&external_id=" + encodeURIComponent(externalId) +
          "&content_type=" + encodeURIComponent(contentType) +
          (confirmMerge ? "&confirm_merge=1" : ""),
        { method: "POST", headers: csrfHeader() }
      );
      const json = await res.json();
      return { ok: res.ok, status: res.status, json };
    }

    setIdModalStatus.className = "refreshStatus";
    setIdModalStatus.textContent = wteFormat(WTE_I18N.detail.setting_id, source.toUpperCase());

    try {
      let { ok, status, json } = await callSet(false);

      if (!ok && status === 409) {
        const conflict = json.detail || json;
        const ownership = conflict.physically_owned
          ? WTE_I18N.detail.conflict_physically_owned
          : (conflict.digitally_owned ? WTE_I18N.detail.conflict_digitally_owned : WTE_I18N.detail.conflict_not_owned);
        const confirmed = await showConfirmModal({
          title: WTE_I18N.detail.conflict_merge_title,
          message: wteFormat(WTE_I18N.detail.conflict_merge_confirm, conflict.conflict_title || "?", ownership),
          confirmLabel: WTE_I18N.detail.conflict_merge_ok,
          cancelLabel: WTE_I18N.detail.confirm_cancel
        });
        if (!confirmed) {
          setIdModalStatus.className = "refreshStatus";
          setIdModalStatus.textContent = "";
          return;
        }
        ({ ok, status, json } = await callSet(true));
      }

      if (!ok) {
        throw new Error((json.detail && json.detail.message) || json.error || json.detail || ("HTTP " + status));
      }

      if (json.merged) {
        // Filmen som manglet ID var faktisk et duplikat av en annen
        // film - all data ble flyttet dit, og denne raden ble slettet.
        // Går derfor til den filmen i stedet for å laste denne siden
        // på nytt (denne content_id-en finnes ikke lenger).
        closeSetIdModal();
        statusEl.className = "refreshStatus success";
        statusEl.textContent = wteFormat(WTE_I18N.detail.merge_done, json.into_title);
        window.location.href = "detail.php?id=" + encodeURIComponent(json.into_content_id);
        return;
      }

      // Ingen konflikt - koblingen er opprettet, men feltene (title/
      // overview/runtime osv.) er ikke flettet inn i content-tabellen
      // ennå. Gjør det samme andre steget som refreshSource() gjør.
      setIdModalStatus.textContent = wteFormat(WTE_I18N.detail.merging_from, source.toUpperCase());
      const mergeRes = await fetch(
        "api.php?action=merge_external_source&source=" + encodeURIComponent(source) +
          "&external_id=" + encodeURIComponent(externalId),
        { method: "POST", headers: csrfHeader() }
      );
      const mergeJson = await mergeRes.json();
      if (!mergeRes.ok || mergeJson.error) {
        throw new Error(mergeJson.error || mergeJson.detail || ("HTTP " + mergeRes.status));
      }

      closeSetIdModal();
      statusEl.className = "refreshStatus success";
      statusEl.textContent = wteFormat(WTE_I18N.detail.source_updated, source.toUpperCase(), json.fetched_at || WTE_I18N.detail.now);
      await loadDetail();
    } catch (err) {
      setIdModalStatus.className = "refreshStatus error";
      setIdModalStatus.textContent = wteFormat(WTE_I18N.detail.refresh_error_prefix, source.toUpperCase(), err.message);
    }
  }

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
        headers: { "Content-Type": "application/json", ...csrfHeader() },
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

  document.getElementById("btnChooseCover")?.addEventListener("click", openCoverModal);
  document.getElementById("btnCloseCoverModal").addEventListener("click", closeCoverModal);
  coverModalOverlay.addEventListener("click", (e) => {
    if (e.target === coverModalOverlay) closeCoverModal();
  });

  // ---- Penne-ikon-redigering: én felles modal for både content-felter
  // (title/overview/runtime/osv. - PATCH /media/content/{id}) og
  // eier-/kjøpsinformasjon pr. fysisk eksemplar (owner/store/
  // purchased_at/price/currency - PATCH
  // /media/physical-copy/{collection_id}/{copy_id}). Penne-ikonene
  // finnes kun i DOM-en for innloggede besøkende (se IS_LOGGED_IN og
  // $isLoggedIn i PHP-delen), men selve skrive-tilgangen er uansett
  // beskyttet av innlogging på backend-siden (get_current_user på
  // begge endepunktene) - dette er bare et UX-lag. ----
  const editFieldModalOverlay = document.getElementById("editFieldModalOverlay");
  const editFieldModalTitle = document.getElementById("editFieldModalTitle");
  const editFieldModalStatus = document.getElementById("editFieldModalStatus");
  const editFieldModalBody = document.getElementById("editFieldModalBody");
  let editFieldContext = null;

  function closeEditFieldModal(){
    editFieldModalOverlay.style.display = "none";
    editFieldModalBody.innerHTML = "";
    editFieldContext = null;
  }

  // Henter alle eksisterende filmgruppenavn (GET /media/groups) og
  // fyller <datalist id="groupNameOptions"> i redigerings-popupen, slik
  // at brukeren kan velge en gruppe som allerede finnes fremfor å måtte
  // huske/skrive nøyaktig samme navn på nytt (unngår f.eks.
  // "Olsenbanden" vs. "olsenbanden" som to forskjellige grupper).
  // Skriver man inn noe som ikke finnes i listen, blir det uansett en
  // ny gruppe (se _get_or_create_group_id() i backend) - datalist
  // tvinger ikke brukeren til å velge et av forslagene.
  async function loadGroupNameOptions(){
    const datalist = document.getElementById("groupNameOptions");
    if (!datalist) return;
    try {
      const res = await fetch("api.php?action=list_groups");
      if (!res.ok) return;
      const groups = await res.json();
      datalist.innerHTML = groups.map(
        (g) => `<option value="${escapeHtml(g.name)}"></option>`
      ).join("");
    } catch (err) {
      // Stille feil - autofullføring er en bekvemmelighet, ikke
      // nødvendig for at redigeringen skal fungere.
    }
  }

  // Samme resonnement som loadGroupNameOptions() over, men for
  // eier-feltet på "Samlingsopplysninger" (GET /media/owners) - lar
  // brukeren velge en eksisterende eier i stedet for å skrive inn
  // navnet manuelt hver gang (unngår stave-/kasus-varianter). Skriver
  // man inn noe som ikke finnes i listen, opprettes en ny eier (se
  // _get_or_create_owner_id() i backend).
  async function loadOwnerNameOptions(){
    const datalist = document.getElementById("ownerNameOptions");
    if (!datalist) return;
    try {
      const res = await fetch("api.php?action=list_owners");
      if (!res.ok) return;
      const owners = await res.json();
      datalist.innerHTML = owners.map(
        (o) => `<option value="${escapeHtml(o.name)}"></option>`
      ).join("");
    } catch (err) {
      // Stille feil - autofullføring er en bekvemmelighet, ikke
      // nødvendig for at redigeringen skal fungere.
    }
  }

  // Bygger innholdet i "Filmgrupper"-redigeringsmodalen: én "chip" pr.
  // gruppe filmen tilhører (med en ×-knapp for å fjerne den), pluss et
  // felt for å legge til en ny/eksisterende gruppe. Kalles både når
  // modalen åpnes første gang og etter hver add/remove (se
  // refreshGroupsManageModal()) for å vise oppdatert tilstand uten å
  // lukke modalen.
  function renderGroupsManageBodyHtml(groups){
    const inputStyle = "width:100%; padding:8px; border-radius:7px; background:var(--bg,#0d0f14); color:var(--text); border:1px solid var(--line,#262b38);";
    const chips = groups.length
      ? groups.map(g => `
          <span class="groupChip" data-group-id="${g.group_id}">
            ${escapeHtml(g.group_name)}
            <button type="button" class="groupChipRemoveBtn" data-group-id="${g.group_id}" title="${escapeHtml(WTE_I18N.detail.groups_remove_btn_title)}">&times;</button>
          </span>
        `).join("")
      : `<span style="color:var(--muted); font-size:13px;">${escapeHtml(WTE_I18N.detail.groups_no_groups)}</span>`;

    return `
      <div class="groupChipList">${chips}</div>
      <label style="display:block; font-size:12px; color:var(--muted); margin:12px 0 4px;">${escapeHtml(WTE_I18N.detail.groups_add_label)}</label>
      <div class="groupsManageAddRow">
        <input id="groupsManageAddInput" type="text" list="groupNameOptions" autocomplete="off" style="${inputStyle}">
        <datalist id="groupNameOptions"></datalist>
        <button type="button" id="groupsManageAddBtn" class="refreshBtn">${escapeHtml(WTE_I18N.detail.groups_add_btn)}</button>
      </div>
      <div id="groupsManageStatus" class="groupsManageStatus"></div>
    `;
  }

  async function handleGroupsManageAdd(){
    const input = document.getElementById("groupsManageAddInput");
    const statusEl = document.getElementById("groupsManageStatus");
    const name = (input?.value || "").trim();
    if (!name) {
      if (statusEl) statusEl.textContent = WTE_I18N.detail.groups_add_name_required;
      return;
    }
    if (statusEl) statusEl.textContent = WTE_I18N.detail.saving_field;
    try {
      const res = await fetch("api.php?action=add_content_to_group&id=" + encodeURIComponent(contentId), {
        method: "POST",
        headers: { "Content-Type": "application/json", ...csrfHeader() },
        body: JSON.stringify({ group: name }),
      });
      const json = await res.json();
      if (!res.ok) {
        const detail = Array.isArray(json.detail) ? json.detail.map(d => d.msg).join(", ") : (json.detail || json.error);
        throw new Error(detail || ("HTTP " + res.status));
      }
      await refreshGroupsManageModal();
    } catch (err) {
      if (statusEl) statusEl.textContent = WTE_I18N.detail.groups_add_error_prefix + err.message;
    }
  }

  async function handleGroupsManageRemove(groupId){
    const chip = editFieldModalBody.querySelector(`.groupChip[data-group-id="${groupId}"]`);
    const groupName = chip ? chip.textContent.replace("×", "").trim() : "";
    if (!confirm(wteFormat(WTE_I18N.detail.groups_remove_confirm, groupName))) return;
    const statusEl = document.getElementById("groupsManageStatus");
    if (statusEl) statusEl.textContent = WTE_I18N.detail.saving_field;
    try {
      const res = await fetch(
        "api.php?action=remove_content_from_group&id=" + encodeURIComponent(contentId) + "&group_id=" + encodeURIComponent(groupId),
        { method: "POST", headers: csrfHeader() }
      );
      const json = await res.json().catch(() => ({}));
      if (!res.ok) {
        const detail = Array.isArray(json.detail) ? json.detail.map(d => d.msg).join(", ") : (json.detail || json.error);
        throw new Error(detail || ("HTTP " + res.status));
      }
      await refreshGroupsManageModal();
    } catch (err) {
      if (statusEl) statusEl.textContent = WTE_I18N.detail.groups_remove_error_prefix + err.message;
    }
  }

  // Etter en add/remove: laster hele siden på nytt (fact-kort +
  // "andre filmer i denne gruppen"-boksene oppdateres via
  // renderDetail()/renderGroupMovies()), men holder selve modalen åpen
  // og tegner kun chip-listen på nytt - slik kan man legge til/fjerne
  // flere grupper etter hverandre uten å måtte åpne penne-ikonet igjen
  // for hver gang.
  async function refreshGroupsManageModal(){
    await loadDetail();
    if (editFieldContext && editFieldContext.type === "groupsManage") {
      editFieldContext.value = { groups: currentItem.groups || [] };
      editFieldModalBody.innerHTML = renderGroupsManageBodyHtml(currentItem.groups || []);
      loadGroupNameOptions();
      document.getElementById("groupsManageAddInput")?.focus();
    }
  }

  function openEditFieldModal(ctx){
    editFieldContext = ctx;
    editFieldModalStatus.textContent = "";
    editFieldModalStatus.className = "refreshStatus";
    const label = ctx.field === "title_group"
      ? WTE_I18N.detail.edit_field_label_title
      : ctx.field === "purchase_group"
      ? WTE_I18N.detail.tab_purchase
      : ctx.field === "groups_manage"
      ? WTE_I18N.detail.groups_manage_title
      : (WTE_I18N.detail["edit_field_label_" + ctx.field] || ctx.field);
    editFieldModalTitle.textContent = wteFormat(WTE_I18N.detail.edit_field_title, label);

    const inputStyle = "width:100%; padding:8px; border-radius:7px; background:var(--bg,#0d0f14); color:var(--text); border:1px solid var(--line,#262b38);";
    let inputHtml;
    if (ctx.type === "titleGroup") {
      // Tittel og original tittel redigeres samlet i én pop-up, siden
      // original_title ofte ikke er utfylt og et eget penne-ikon der
      // ser rart ut når feltet er tomt.
      const titleLabel = WTE_I18N.detail.edit_field_label_title;
      const originalTitleLabel = WTE_I18N.detail.edit_field_label_original_title;
      inputHtml = `
        <label style="display:block; font-size:12px; color:var(--muted); margin-bottom:4px;">${escapeHtml(titleLabel)}</label>
        <input id="editFieldInputTitle" type="text" value="${escapeHtml(ctx.value.title ?? "")}" style="${inputStyle}">
        <label style="display:block; font-size:12px; color:var(--muted); margin:10px 0 4px;">${escapeHtml(originalTitleLabel)}</label>
        <input id="editFieldInputOriginalTitle" type="text" value="${escapeHtml(ctx.value.original_title ?? "")}" style="${inputStyle}">
      `;
    } else if (ctx.type === "groupsManage") {
      // Filmgrupper redigeres nå med et eget grensesnitt (ikke via den
      // vanlige lagre-og-lukk-flyten i saveEditField()) - en film kan
      // tilhøre 0, 1 eller flere grupper samtidig, så en enkel
      // tekstboks holder ikke lenger. Hver chip fjernes/legges til med
      // én gang (kaller add_content_to_group/remove_content_from_group
      // direkte), og modalen holdes åpen slik at man kan legge til
      // flere grupper etter hverandre uten å måtte åpne penne-ikonet
      // på nytt hver gang.
      inputHtml = renderGroupsManageBodyHtml(ctx.value.groups || []);
    } else if (ctx.type === "purchaseGroup") {
      // Butikk/kjøpsdato/pris/valuta redigeres samlet i én pop-up (ett
      // penne-ikon pr. eksemplar) - samme resonnement som "titleGroup".
      inputHtml = `
        <label style="display:block; font-size:12px; color:var(--muted); margin-bottom:4px;">${escapeHtml(WTE_I18N.detail.edit_field_label_store)}</label>
        <input id="editFieldInputStore" type="text" value="${escapeHtml(ctx.value.store ?? "")}" style="${inputStyle}">
        <label style="display:block; font-size:12px; color:var(--muted); margin:10px 0 4px;">${escapeHtml(WTE_I18N.detail.edit_field_label_purchased_at)}</label>
        <input id="editFieldInputPurchasedAt" type="date" value="${escapeHtml(ctx.value.purchased_at ?? "")}" style="${inputStyle}">
        <label style="display:block; font-size:12px; color:var(--muted); margin:10px 0 4px;">${escapeHtml(WTE_I18N.detail.edit_field_label_price)}</label>
        <input id="editFieldInputPrice" type="number" value="${escapeHtml(ctx.value.price ?? "")}" style="${inputStyle}">
        <label style="display:block; font-size:12px; color:var(--muted); margin:10px 0 4px;">${escapeHtml(WTE_I18N.detail.edit_field_label_currency)}</label>
        <input id="editFieldInputCurrency" type="text" value="${escapeHtml(ctx.value.currency ?? "")}" style="${inputStyle}">
      `;
    } else if (ctx.type === "textarea") {
      inputHtml = `<textarea id="editFieldInput" rows="6" style="${inputStyle}">${escapeHtml(ctx.value ?? "")}</textarea>`;
    } else if (ctx.type === "select" && ctx.field === "content_type") {
      const options = ["movie", "series"].map(v =>
        `<option value="${v}" ${ctx.value === v ? "selected" : ""}>${escapeHtml(WTE_I18N.detail["edit_field_type_" + v])}</option>`
      ).join("");
      inputHtml = `<select id="editFieldInput" style="${inputStyle}">${options}</select>`;
    } else if (ctx.type === "ownerSelect") {
      // Fritekst-input med <datalist>-forslag (samme kombo-mønster
      // som gruppenavn-feltet) - lar brukeren velge en eksisterende
      // eier, men hindrer ikke at en ny eier opprettes ved å skrive
      // inn et navn som ikke finnes i listen (se
      // _get_or_create_owner_id() i backend).
      inputHtml = `
        <input id="editFieldInput" type="text" list="ownerNameOptions" autocomplete="off" value="${escapeHtml(ctx.value ?? "")}" style="${inputStyle}">
        <datalist id="ownerNameOptions"></datalist>
      `;
    } else {
      inputHtml = `<input id="editFieldInput" type="${ctx.type}" value="${escapeHtml(ctx.value ?? "")}" style="${inputStyle}">`;
    }
    editFieldModalBody.innerHTML = inputHtml;
    editFieldModalOverlay.style.display = "flex";
    // "Lagre"-knappen skjules for groupsManage - der lagres hver
    // endring (legg til/fjern) med én gang, det finnes ingen samlet
    // "lagre alt"-handling. "Avbryt" fungerer fortsatt som en vanlig
    // lukk-knapp siden ingenting usaved gjenstår å avbryte.
    document.getElementById("btnSaveEditField").style.display = ctx.type === "groupsManage" ? "none" : "";
    if (ctx.type === "groupsManage") loadGroupNameOptions();
    if (ctx.type === "ownerSelect") loadOwnerNameOptions();
    const focusTarget = document.getElementById("editFieldInput")
      || document.getElementById("editFieldInputTitle")
      || document.getElementById("groupsManageAddInput")
      || document.getElementById("editFieldInputStore");
    if (focusTarget) focusTarget.focus();
  }

  async function saveEditField(){
    if (!editFieldContext) return;
    const { kind, field, type, collectionId, copyId } = editFieldContext;

    let body;
    if (type === "titleGroup") {
      const titleVal = document.getElementById("editFieldInputTitle").value;
      const originalTitleVal = document.getElementById("editFieldInputOriginalTitle").value;
      body = {
        title: titleVal === "" ? null : titleVal,
        original_title: originalTitleVal === "" ? null : originalTitleVal,
      };
    } else if (type === "purchaseGroup") {
      const storeVal = document.getElementById("editFieldInputStore").value;
      const purchasedAtVal = document.getElementById("editFieldInputPurchasedAt").value;
      const priceVal = document.getElementById("editFieldInputPrice").value;
      const currencyVal = document.getElementById("editFieldInputCurrency").value;
      body = {
        store: storeVal === "" ? null : storeVal,
        purchased_at: purchasedAtVal === "" ? null : purchasedAtVal,
        price: priceVal === "" ? null : parseFloat(priceVal),
        currency: currencyVal === "" ? null : currencyVal,
      };
    } else {
      const inputEl = document.getElementById("editFieldInput");
      const rawValue = inputEl.value;

      // Tomt felt tolkes som "fjern verdien" (sender null), ikke "ingen
      // endring" - "ingen endring" oppnås ved å ikke åpne redigeringen i
      // det hele tatt. Se docstring for update_physical_copy_fields()/
      // update_content_fields() i backend for samme resonnement.
      let value;
      if (rawValue === "") {
        value = null;
      } else if (type === "number") {
        value = field === "price" ? parseFloat(rawValue) : parseInt(rawValue, 10);
      } else {
        value = rawValue;
      }
      body = { [field]: value };
    }

    editFieldModalStatus.className = "refreshStatus";
    editFieldModalStatus.textContent = WTE_I18N.detail.saving_field;

    try {
      const url = kind === "content"
        ? "api.php?action=update_content_field&id=" + encodeURIComponent(contentId)
        : "api.php?action=update_physical_copy_field&collection_id=" + encodeURIComponent(collectionId) + "&copy_id=" + encodeURIComponent(copyId);

      const res = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/json", ...csrfHeader() },
        body: JSON.stringify(body),
      });
      const json = await res.json();
      if (!res.ok) {
        const detail = Array.isArray(json.detail) ? json.detail.map(d => d.msg).join(", ") : (json.detail || json.error);
        throw new Error(detail || ("HTTP " + res.status));
      }

      closeEditFieldModal();
      await loadDetail();
    } catch (err) {
      editFieldModalStatus.className = "refreshStatus error";
      editFieldModalStatus.textContent = WTE_I18N.detail.save_field_error_prefix + err.message;
    }
  }

  // ---- Hengelås-ikoner: viser om et content-felt står i
  // content.locked_fields (låst mot TMDB/TVDB-fletting) og lar
  // innloggede brukere låse/åpne det manuelt, uten å endre verdien.
  // .lockToggleBtn-knappene finnes bare for content-felter (ikke
  // physical_copy-felter - locked_fields er en kolonne på
  // content-tabellen). ----
  function renderLockIcons(item){
    const lockedFields = new Set(item.locked_fields || []);
    document.querySelectorAll(".lockToggleBtn").forEach((btn) => {
      const fields = btn.dataset.lockFields.split(",");
      const isLocked = fields.every((f) => lockedFields.has(f));
      btn.textContent = isLocked ? "🔒" : "🔓";
      btn.classList.toggle("isLocked", isLocked);
      btn.title = isLocked
        ? WTE_I18N.detail.unlock_field_btn
        : WTE_I18N.detail.lock_field_btn;
    });
  }

  document.addEventListener("click", async (e) => {
    const lockBtn = e.target.closest(".lockToggleBtn");
    if (!lockBtn || !currentItem) return;

    const fields = lockBtn.dataset.lockFields.split(",");
    const currentlyLocked = lockBtn.classList.contains("isLocked");
    const nextLocked = !currentlyLocked;

    lockBtn.disabled = true;
    try {
      for (const field of fields) {
        const res = await fetch(
          "api.php?action=set_content_field_lock&id=" + encodeURIComponent(contentId),
          {
            method: "POST",
            headers: { "Content-Type": "application/json", ...csrfHeader() },
            body: JSON.stringify({ field, locked: nextLocked }),
          }
        );
        const json = await res.json();
        if (!res.ok) {
          throw new Error(json.detail || json.error || ("HTTP " + res.status));
        }
      }
      await loadDetail();
    } catch (err) {
      alert(WTE_I18N.detail.save_field_error_prefix + err.message);
    } finally {
      lockBtn.disabled = false;
    }
  });

  // Event delegation - penne-ikonene for fysiske eksemplarer finnes
  // ikke i DOM-en før renderCollectionTab()/renderPurchaseTab() har
  // kjørt, så en vanlig addEventListener pr. knapp fungerer ikke der.
  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".editPencilBtn");
    if (!btn || !currentItem) return;
    // btnManageWatchedDates (se renderWatchedCard()) gjenbruker
    // .editPencilBtn kun for samme visuelle stil/edit-mode-synlighet,
    // men har sin egen klikk-handler og mangler data-field - uten
    // denne guarden ville denne delegerte handleren også kjørt og
    // åpnet "Rediger undefined"-modalen ved siden av.
    if (!btn.dataset.field) return;

    const field = btn.dataset.field;
    const type = btn.dataset.type;
    const collectionId = btn.dataset.collectionId;
    const copyId = btn.dataset.copyId;

    if (collectionId && copyId !== undefined) {
      const copy = (currentItem.physical_copies || []).find(
        c => c.collection_id === collectionId && String(c.copy_id) === copyId
      );
      if (!copy) return;
      const value = field === "purchase_group"
        ? { store: copy.store, purchased_at: copy.purchased_at, price: copy.price, currency: copy.currency }
        : copy[field];
      openEditFieldModal({ kind: "physical_copy", field, type, collectionId, copyId, value });
    } else if (type === "titleGroup") {
      openEditFieldModal({ kind: "content", field, type, value: { title: currentItem.title, original_title: currentItem.original_title } });
    } else if (type === "groupsManage") {
      openEditFieldModal({ kind: "content", field, type, value: { groups: currentItem.groups || [] } });
    } else {
      openEditFieldModal({ kind: "content", field, type, value: currentItem[field] });
    }
  });

  document.getElementById("btnCloseEditFieldModal").addEventListener("click", closeEditFieldModal);
  document.getElementById("btnCancelEditField").addEventListener("click", closeEditFieldModal);
  document.getElementById("btnSaveEditField").addEventListener("click", saveEditField);
  editFieldModalOverlay.addEventListener("click", (e) => {
    if (e.target === editFieldModalOverlay) closeEditFieldModal();
  });

  // Legg til/fjern-knappene inne i "Filmgrupper"-modalen tegnes på nytt
  // ved hver add/remove (se refreshGroupsManageModal()), så en vanlig
  // addEventListener pr. knapp ville mistet lytteren igjen - derfor
  // event delegation på selve modal-body-elementet i stedet.
  editFieldModalBody.addEventListener("click", (e) => {
    if (!editFieldContext || editFieldContext.type !== "groupsManage") return;
    const removeBtn = e.target.closest(".groupChipRemoveBtn");
    const addBtn = e.target.closest("#groupsManageAddBtn");
    if (removeBtn) {
      handleGroupsManageRemove(Number(removeBtn.dataset.groupId));
    } else if (addBtn) {
      handleGroupsManageAdd();
    }
  });
  editFieldModalBody.addEventListener("keydown", (e) => {
    if (!editFieldContext || editFieldContext.type !== "groupsManage") return;
    if (e.key === "Enter" && e.target.id === "groupsManageAddInput") {
      e.preventDefault();
      handleGroupsManageAdd();
    }
  });

  // "Rediger"-knapp: viser/skjuler alle penne-ikonene via en klasse på
  // <body> (se CSS: body.editModeActive .editPencilBtn). Kun relevant
  // for innloggede, siden knappen bare finnes i DOM-en da. Valget
  // huskes i localStorage slik at redigeringsmodus ikke slår seg av
  // igjen ved neste sidevisning.
  const btnToggleEditMode = document.getElementById("btnToggleEditMode");
  if (btnToggleEditMode) {
    const EDIT_MODE_STORAGE_KEY = "wte_edit_mode_active";

    function updateEditModeBtnLabel(active) {
      btnToggleEditMode.textContent = active
        ? WTE_I18N.detail.toggle_edit_mode_done_btn
        : WTE_I18N.detail.toggle_edit_mode_btn;
    }

    function setEditMode(active) {
      document.body.classList.toggle("editModeActive", active);
      updateEditModeBtnLabel(active);
      localStorage.setItem(EDIT_MODE_STORAGE_KEY, active ? "1" : "0");
    }

    setEditMode(localStorage.getItem(EDIT_MODE_STORAGE_KEY) === "1");

    btnToggleEditMode.addEventListener("click", () => {
      setEditMode(!document.body.classList.contains("editModeActive"));
    });
  }

  // "Lås felter"-knapp: viser/skjuler hengelås-ikonene, samme mønster
  // som "Rediger"-knappen over (egen klasse på <body>, egen
  // localStorage-nøkkel - de to modusene er uavhengige av hverandre).
  const btnToggleLockMode = document.getElementById("btnToggleLockMode");
  if (btnToggleLockMode) {
    const LOCK_MODE_STORAGE_KEY = "wte_lock_mode_active";

    function updateLockModeBtnLabel(active) {
      btnToggleLockMode.textContent = active
        ? WTE_I18N.detail.toggle_lock_mode_done_btn
        : WTE_I18N.detail.toggle_lock_mode_btn;
    }

    function setLockMode(active) {
      document.body.classList.toggle("lockModeActive", active);
      updateLockModeBtnLabel(active);
      localStorage.setItem(LOCK_MODE_STORAGE_KEY, active ? "1" : "0");
    }

    setLockMode(localStorage.getItem(LOCK_MODE_STORAGE_KEY) === "1");

    btnToggleLockMode.addEventListener("click", () => {
      setLockMode(!document.body.classList.contains("lockModeActive"));
    });
  }

  loadDetail();
</script>
</body>
</html>
