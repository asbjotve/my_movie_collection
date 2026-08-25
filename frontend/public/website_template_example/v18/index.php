<?php
declare(strict_types=1);

/**
 * website_template_example v18 – som v17, men "Mine filmer" hentes nå
 * via FastAPI-backend i stedet for at PHP snakker direkte til MySQL.
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
 *  FREMTIDIG INNLOGGING – HVOR SKAL DEN KOBLES INN?
 *  Når prosjektet får ekte innlogging, er dette stedet å legge inn en
 *  sjekk, f.eks.:
 *
 *      require_once __DIR__ . '/auth.php';
 *      $currentUser = require_login(); // redirect til /login.php hvis ikke innlogget
 *
 *  Menypunktene "Andre lister" og "Administrering" er de mest naturlige
 *  kandidatene til å kreve innlogging (evt. skjules helt for
 *  ikke-innloggede brukere i stedet for bare å vises som "låst" slik de
 *  gjør nå). "Mine filmer" og "Ønskeliste" kan trolig forbli åpne,
 *  avhengig av om løsningen skal være hel-privat eller delvis offentlig.
 * ============================================================
 *
 *  KONFIGURERBAR TILGANG PER MENYPUNKT
 *  $sectionAccess under styrer om et menypunkt vises med hengelås-ikon
 *  og merknadsboks (dvs. "krever innlogging når det kommer") eller om
 *  det er helt åpent. Sett verdien til true/false per punkt – ingen
 *  annen kode må endres for å justere dette. "Ønskeliste" er satt til
 *  true her (låst) som eksempel på at den også kan konfigureres, men
 *  kan enkelt endres til false igjen.
 * ============================================================
 */

$isLoggedIn = false; // Plassholder – finnes ingen ekte innloggingsløsning ennå.

$sectionAccess = [
    'mine_filmer'    => false, // åpen
    'onskeliste'     => true,  // låst (konfigurerbar – kan settes til false)
    'andre_lister'   => true,  // låst
    'administrering' => true,  // låst
];
?>
<!doctype html>
<html lang="no">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Media-katalog – v18 (toppmeny, via API)</title>
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

    /* ---- Toppmeny ---- */
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
    nav.mainnav{
      display:flex; gap:6px;
      flex:1;
    }
    nav.mainnav button{
      appearance:none; border:1px solid transparent; background:transparent;
      color: var(--muted);
      font-size:14px; font-weight:600;
      padding:8px 14px;
      border-radius: 10px;
      cursor:pointer;
    }
    nav.mainnav button:hover{ color: var(--text); background: rgba(111,141,255,.08); }
    nav.mainnav button.active{
      color: var(--text);
      background: rgba(111,141,255,.15);
      border-color: rgba(111,141,255,.5);
    }
    nav.mainnav button .lockIcon{ margin-left:6px; opacity:.7; font-size:12px; }

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

    main{ padding: 14px 22px; max-width: 1800px; margin: 0 auto; }
    .panel{ display:none; }
    .panel.active{ display:block; }

    h2.pageTitle{ margin: 0 0 2px; font-size:15px; }
    p.pageHint{ margin: 0 0 10px; color: var(--muted); font-size:11px; }

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
    }
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

<div class="topbar">
  <div class="brand">🎬 Media-katalog</div>
  <nav class="mainnav" id="mainnav">
    <button data-panel="mine_filmer" class="active">Mine filmer<?= $sectionAccess['mine_filmer'] ? '<span class="lockIcon">🔒</span>' : '' ?></button>
    <button data-panel="onskeliste">Ønskeliste<?= $sectionAccess['onskeliste'] ? '<span class="lockIcon">🔒</span>' : '' ?></button>
    <button data-panel="andre_lister">Andre lister<?= $sectionAccess['andre_lister'] ? '<span class="lockIcon">🔒</span>' : '' ?></button>
    <button data-panel="administrering">Administrering<?= $sectionAccess['administrering'] ? '<span class="lockIcon">🔒</span>' : '' ?></button>
  </nav>
  <div class="authState">
    <span class="dot"></span>
    <?= $isLoggedIn ? 'Innlogget' : 'Ikke innlogget (kommer senere)' ?>
  </div>
</div>

<main>

  <!-- ============ MINE FILMER ============ -->
  <section class="panel active" id="panel-mine_filmer">
    <h2 class="pageTitle">Mine filmer</h2>
    <p class="pageHint">Data hentes via API (FastAPI-backend), ikke direkte MySQL som v15/v17.</p>

    <div class="filterBar">
      <div class="search">
        <input id="mineFilmerSearch" placeholder="Søk tittel / original tittel…" />
      </div>
      <div class="chiprow" id="mineFilmerTypeChips"></div>
      <label class="unwatchedToggle">
        <input id="mineFilmerOnlyUnwatched" type="checkbox" />
        Vis bare ikke-sett
      </label>
      <div class="viewToggle" id="mineFilmerViewToggle">
        <button data-view="grid" class="active">🖼️ Rutenett</button>
        <button data-view="list">📋 Liste</button>
      </div>
    </div>

    <div id="mineFilmerStatus" style="color:var(--muted); font-size:13px;">Laster data fra databasen…</div>
    <div class="grid" id="mineFilmerGrid"></div>
    <table class="dataTable" id="mineFilmerTable" style="display:none;">
      <thead>
        <tr>
          <th>Tittel</th>
          <th>Original tittel</th>
          <th>Årstall</th>
          <th>IMDb-id</th>
        </tr>
      </thead>
      <tbody id="mineFilmerTableBody"></tbody>
    </table>
  </section>

  <!-- ============ ØNSKELISTE ============ -->
  <section class="panel" id="panel-onskeliste">
    <h2 class="pageTitle">Ønskeliste</h2>
    <p class="pageHint">Plassholder-data – ingen databasekobling i denne versjonen.</p>
    <div class="list" id="onskelisteList"></div>
    <?php if ($sectionAccess['onskeliste']): ?>
    <div class="noteBox">
      🔒 <strong>Vurdering:</strong> her satt til å kreve innlogging (konfigurerbart via
      <code>$sectionAccess['onskeliste']</code> øverst i filen) – kan enkelt settes åpen igjen.
    </div>
    <?php endif; ?>
  </section>

  <!-- ============ ANDRE LISTER ============ -->
  <section class="panel" id="panel-andre_lister">
    <h2 class="pageTitle">Andre lister</h2>
    <p class="pageHint">Plassholder-data – ingen databasekobling i denne versjonen.</p>
    <div class="grid" id="andreListerGrid"></div>
    <div class="noteBox">
      🔒 <strong>Vurdering:</strong> dette punktet bør trolig kreve innlogging når ekte
      innlogging kommer på plass, siden egendefinerte lister er personlig innhold.
    </div>
  </section>

  <!-- ============ ADMINISTRERING ============ -->
  <section class="panel" id="panel-administrering">
    <h2 class="pageTitle">Administrering</h2>
    <p class="pageHint">Plassholder – handlinger er deaktivert inntil innlogging finnes.</p>
    <div class="adminGrid">
      <div class="adminCard">
        <span class="lockedBadge">Låst</span>
        <h3>Legg til film</h3>
        <p>Manuell registrering av nye filmer/serier i katalogen.</p>
      </div>
      <div class="adminCard">
        <span class="lockedBadge">Låst</span>
        <h3>Rediger lister</h3>
        <p>Opprett, endre eller slett egendefinerte lister.</p>
      </div>
      <div class="adminCard">
        <span class="lockedBadge">Låst</span>
        <h3>Brukere</h3>
        <p>Administrer hvem som har tilgang til løsningen.</p>
      </div>
      <div class="adminCard">
        <span class="lockedBadge">Låst</span>
        <h3>Systemstatus</h3>
        <p>Enkel oversikt over database/API-tilkobling.</p>
      </div>
    </div>
    <div class="noteBox">
      🔒 <strong>Vurdering:</strong> "Administrering" bør nesten helt sikkert kreve
      innlogging – dette er stedet hvor data kan endres/slettes, i motsetning til
      "Mine filmer"/"Ønskeliste" som trolig bare viser data.
    </div>
  </section>

</main>

<script>
  // ---- Demo-data (kun plassholder, ingen database) ----
  const demoWishlist = [
    { title:"Oppenheimer", addedAt:"2026-05-01" },
    { title:"Poor Things", addedAt:"2026-04-18" },
  ];

  const demoLists = [
    { name:"Julefilmer", itemCount:7 },
    { name:"Barnefilmer", itemCount:12 },
    { name:"Skal ses med kompiser", itemCount:3 },
  ];

  // ---- Mine filmer: live data fra databasen (api.php), som v15 ----
  let mineFilmerData = [];
  let mineFilmerView = "grid"; // "grid" eller "list"

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
      <div class="card">
        <div class="cover">
          <div class="coverBadge">${escapeHtml((item.content_type || "").toUpperCase())}</div>
        </div>
        <div class="meta">
          <div class="title">${escapeHtml(item.title)}</div>
          <div class="sub">
            <span>${escapeHtml(item.first_release || "-")}</span>
            <span class="tag ${item.watched_flag ? "good" : ""}">${item.watched_flag ? "Sett" : "Ikke sett"}</span>
          </div>
        </div>
      </div>
    `).join("");
  }

  function renderMineFilmerTable(items){
    mineFilmerTableBody.innerHTML = items.map(item => {
      const year = (item.first_release || "").slice(0, 4) || "-";
      const imdbCell = item.imdb_id
        ? `<a href="https://www.imdb.com/title/${escapeHtml(item.imdb_id)}/" target="_blank" rel="noopener">${escapeHtml(item.imdb_id)}</a>`
        : "-";
      return `
        <tr>
          <td>${escapeHtml(item.title)}</td>
          <td>${escapeHtml(item.original_title || "-")}</td>
          <td>${year}</td>
          <td>${imdbCell}</td>
        </tr>
      `;
    }).join("");
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
    all.className = "chip" + (mineFilmerActiveType === null ? " active" : "");
    all.textContent = "Alle";
    all.onclick = () => { mineFilmerActiveType = null; renderMineFilmerTypeChips(); renderMineFilmer(); };
    mineFilmerTypeChips.appendChild(all);

    for (const t of types){
      const el = document.createElement("div");
      el.className = "chip" + (mineFilmerActiveType === t ? " active" : "");
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
      mineFilmerStatus.textContent = "Klarte ikke å hente data: " + err.message;
      mineFilmerStatus.style.color = "var(--danger)";
      return;
    }
    renderMineFilmerTypeChips();
    renderMineFilmer();
  }


  loadMineFilmer();

  // ---- Render: Ønskeliste ----
  const onskelisteList = document.getElementById("onskelisteList");
  onskelisteList.innerHTML = demoWishlist.map(w => `
    <div class="row">
      <strong>${w.title}</strong>
      <small>Lagt til: ${w.addedAt}</small>
    </div>
  `).join("");

  // ---- Render: Andre lister ----
  const andreListerGrid = document.getElementById("andreListerGrid");
  andreListerGrid.innerHTML = demoLists.map(l => `
    <div class="card">
      <div class="meta">
        <div class="title">${l.name}</div>
        <div class="sub"><span class="tag">${l.itemCount} elementer</span></div>
      </div>
    </div>
  `).join("");

  // ---- Toppmeny: bytt synlig panel ----
  const navButtons = document.querySelectorAll("#mainnav button");
  navButtons.forEach(btn => {
    btn.addEventListener("click", () => {
      navButtons.forEach(b => b.classList.remove("active"));
      document.querySelectorAll(".panel").forEach(p => p.classList.remove("active"));


      btn.classList.add("active");
      document.getElementById("panel-" + btn.dataset.panel).classList.add("active");
    });
  });
</script>
</body>
</html>
