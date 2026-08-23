<?php
declare(strict_types=1);

/**
 * website_template_example v16 – "fra scratch"-forslag med toppmeny.
 *
 * Status: RENT FRONTEND-PROTOTYPE. Ingen databasekobling ennå (kommer
 * evt. i en senere versjon, slik v15 gjorde for media-katalogen).
 * All data under er hardkodet demo-/plassholderdata.
 *
 * ============================================================
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
 */

$isLoggedIn = false; // Plassholder – finnes ingen ekte innloggingsløsning ennå.
?>
<!doctype html>
<html lang="no">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Media-katalog – v16 (toppmeny, prototype)</title>
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

    main{ padding: 22px; max-width: 1200px; margin: 0 auto; }
    .panel{ display:none; }
    .panel.active{ display:block; }

    h2.pageTitle{ margin: 0 0 4px; font-size:20px; }
    p.pageHint{ margin: 0 0 20px; color: var(--muted); font-size:13px; }

    .grid{
      display:grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap:14px;
    }
    .card{
      background: var(--panel);
      border:1px solid var(--line);
      border-radius: 14px;
      padding:14px;
    }
    .card .title{ font-weight:700; margin-bottom:6px; }
    .card .sub{ color: var(--muted); font-size:12px; display:flex; gap:8px; flex-wrap:wrap; }
    .tag{
      font-size:11px; padding:3px 8px; border-radius:999px;
      background: rgba(111,141,255,.14);
      border:1px solid rgba(111,141,255,.4);
      color: var(--text);
    }
    .tag.good{ background: rgba(61,220,151,.14); border-color: rgba(61,220,151,.5); }

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
    <button data-panel="mine_filmer" class="active">Mine filmer</button>
    <button data-panel="onskeliste">Ønskeliste</button>
    <button data-panel="andre_lister">Andre lister<span class="lockIcon">🔒</span></button>
    <button data-panel="administrering">Administrering<span class="lockIcon">🔒</span></button>
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
    <p class="pageHint">Plassholder-data – ingen databasekobling i denne versjonen.</p>
    <div class="grid" id="mineFilmerGrid"></div>
  </section>

  <!-- ============ ØNSKELISTE ============ -->
  <section class="panel" id="panel-onskeliste">
    <h2 class="pageTitle">Ønskeliste</h2>
    <p class="pageHint">Plassholder-data – ingen databasekobling i denne versjonen.</p>
    <div class="list" id="onskelisteList"></div>
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
  const demoMovies = [
    { title:"Dune", year:2021, type:"Film", watched:true },
    { title:"Blade Runner", year:1982, type:"Film", watched:false },
    { title:"Planet Earth II", year:2016, type:"Serie", watched:true },
  ];

  const demoWishlist = [
    { title:"Oppenheimer", addedAt:"2026-05-01" },
    { title:"Poor Things", addedAt:"2026-04-18" },
  ];

  const demoLists = [
    { name:"Julefilmer", itemCount:7 },
    { name:"Barnefilmer", itemCount:12 },
    { name:"Skal ses med kompiser", itemCount:3 },
  ];

  // ---- Render: Mine filmer ----
  const mineFilmerGrid = document.getElementById("mineFilmerGrid");
  mineFilmerGrid.innerHTML = demoMovies.map(m => `
    <div class="card">
      <div class="title">${m.title}</div>
      <div class="sub">
        <span class="tag">${m.type}</span>
        <span>${m.year}</span>
        <span class="tag ${m.watched ? "good" : ""}">${m.watched ? "Sett" : "Ikke sett"}</span>
      </div>
    </div>
  `).join("");

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
      <div class="title">${l.name}</div>
      <div class="sub"><span class="tag">${l.itemCount} elementer</span></div>
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
