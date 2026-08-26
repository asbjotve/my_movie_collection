<?php
declare(strict_types=1);

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
<html lang="no">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Media-katalog – detaljer (v18)</title>
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
    .sourceRow{
      display:flex; justify-content:space-between; align-items:center; gap:10px;
      padding:8px 0;
      border-bottom:1px solid var(--line);
      font-size:13px;
    }
    .sourceRow:last-child{ border-bottom:none; }
    .sourceRow a{ color: var(--accent); text-decoration:none; }
    .sourceRow a:hover{ text-decoration:underline; }

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
    <a href="index.php" class="active">Mine filmer</a>
    <a href="index.php">Ønskeliste<span style="margin-left:6px; opacity:.7; font-size:12px;">🔒</span></a>
    <a href="index.php">Andre lister<span style="margin-left:6px; opacity:.7; font-size:12px;">🔒</span></a>
    <a href="index.php">Administrering<span style="margin-left:6px; opacity:.7; font-size:12px;">🔒</span></a>
  </nav>
  <div class="authState">
    <span class="dot"></span>
    Ikke innlogget (kommer senere)
  </div>
</div>

<main>
  <a class="backLink" href="index.php">&larr; Tilbake til Mine filmer</a>

  <div id="detailStatus">Laster…</div>

  <div class="detailLayout" id="detailLayout" style="display:none;">
    <div class="posterCol">
      <div class="poster" id="posterBox">
        <div class="coverBadge" id="posterBadge"></div>
      </div>
      <div class="ownershipBadges" id="ownershipBadges"></div>
    </div>

    <div>
      <div class="titleBlock">
        <h1 id="dTitle"></h1>
        <div class="originalTitle" id="dOriginalTitle"></div>
      </div>

      <div class="factsGrid">
        <div class="factCard"><div class="k">Utgitt</div><div class="v" id="fRelease">-</div></div>
        <div class="factCard"><div class="k">Spilletid</div><div class="v" id="fRuntime">-</div></div>
        <div class="factCard"><div class="k">Aldersgrense</div><div class="v" id="fAge">-</div></div>
        <div class="factCard"><div class="k">Type</div><div class="v" id="fType">-</div></div>
        <div class="factCard"><div class="k">Produksjonsselskap</div><div class="v" id="fProdCompany">-</div></div>
        <div class="factCard"><div class="k">IMDb</div><div class="v" id="fImdb">-</div></div>
      </div>

      <div class="sourcesBox">
        <h3>Eksterne kilder</h3>
        <div id="sourcesList"><div style="color:var(--muted); font-size:13px;">Ingen registrert.</div></div>
      </div>
    </div>
  </div>

  <div class="tabSection" id="tabSection" style="display:none;">
    <div class="tabBar">
      <button class="tabBtn active" data-tab="cast">Rollebesetning</button>
      <button class="tabBtn" data-tab="collection">Samlingsopplysninger</button>
      <button class="tabBtn" data-tab="purchase">Kjøpsinformasjon</button>
    </div>

    <div class="tabPanel active" data-tab-panel="cast">
      <div class="emptyNote">
        Ingen data registrert ennå. Dette krever en egen tabell for
        skuespillere/crew (rolle, navn, ev. bilde) koblet til filmen –
        finnes ikke i databasen i dag.
      </div>
    </div>

    <div class="tabPanel" data-tab-panel="collection" id="collectionPanel">
      <!-- fylles av renderCollectionTab() -->
    </div>

    <div class="tabPanel" data-tab-panel="purchase">
      <div class="emptyNote">
        Ingen kjøpsinformasjon registrert ennå. Dette krever egne felt/
        tabell for f.eks. pris, kjøpsdato og butikk – finnes ikke i
        databasen i dag.
      </div>
    </div>
  </div>
</main>

<script>
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
      panel.innerHTML = `<div class="emptyNote">Ikke registrert i fysisk samling (ingen Blu-ray/DVD-eksemplar for denne tittelen).</div>`;
      return;
    }

    const rows = copies.map((c, i) => {
      const b = formatBadge(c.format);
      const discTxt = c.disc_count > 1
        ? c.disc_count + " plater"
        : c.disc_count === 1 ? "1 plate" : "Ukjent antall plater";
      const hasBoxItems = c.is_box_set && (c.box_set_items || []).length;
      // Vis platetabellen for alle ikke-box-sett-eksemplarer med minst
      // én registrert plate - også når det bare er én plate.
      const hasDiscs = !c.is_box_set && (c.discs || []).length > 0;
      const clickable = hasBoxItems || hasDiscs;

      return `
        <div class="copyRow${clickable ? " clickable" : ""}" ${clickable ? `data-copy-index="${i}"` : ""}>
          <span class="fmt">${escapeHtml(b.label)}</span>
          ${c.is_box_set ? `<span class="boxTag">Box-sett</span>` : ""}
          <span class="meta">${escapeHtml(discTxt)}</span>
          ${c.barcode ? `<span class="meta">Strekkode: ${escapeHtml(c.barcode)}</span>` : ""}
          ${c.box_set_barcode ? `<span class="meta">Boks-strekkode: ${escapeHtml(c.box_set_barcode)}</span>` : ""}
          ${hasBoxItems ? `<span class="hint">Vis innhold i boksen &rarr;</span>` : ""}
          ${hasDiscs ? `<span class="hint">Vis platene &rarr;</span>` : ""}
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
        <h4>Innhold i boksen</h4>
        <table>
          <thead>
            <tr><th>Tittel</th><th>Format</th><th>Lagringsplass</th></tr>
          </thead>
          <tbody>${tableRows}</tbody>
        </table>
      `;
      boxSetTable.style.display = "block";
    }

    function renderDiscsTable(copy){
      const tableRows = (copy.discs || []).map(d => `
        <tr>
          <td>${escapeHtml(d.label || d.type_disc || "Plate")}</td>
          <td>${escapeHtml(d.format || "-")}</td>
          <td>${d.number_in_storage ?? "-"}</td>
        </tr>
      `).join("");

      boxSetTable.innerHTML = `
        <h4>Plater i dette eksemplaret</h4>
        <table>
          <thead>
            <tr><th>Plate</th><th>Format</th><th>Lagringsplass</th></tr>
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
    if (f === "BD") return { cls: "bd", icon: "💿", label: "Blu-ray" };
    if (f === "DVD") return { cls: "dvd", icon: "📀", label: "DVD" };
    if (f.includes("UHD") || f.includes("4K")) return { cls: "uhd", icon: "💠", label: "4K UHD" };
    return { cls: "other", icon: "📦", label: format || "Ukjent format" };
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
      badges.push(`<span class="ownBadge plex"><span class="icon">▶️</span>Plex</span>`);
    }

    box.innerHTML = badges.length
      ? badges.join("")
      : `<span class="noOwnership">Ingen registrerte eksemplarer/kilder ennå.</span>`;
  }

  function renderDetail(item){
    document.title = item.title + " – Media-katalog (v18)";

    document.getElementById("posterBadge").textContent = (item.content_type || "").toUpperCase();
    document.getElementById("dTitle").textContent = item.title || "(uten tittel)";

    const originalTitleEl = document.getElementById("dOriginalTitle");
    if (item.original_title && item.original_title !== item.title){
      originalTitleEl.textContent = "Original tittel: " + item.original_title;
    } else {
      originalTitleEl.textContent = "";
    }

    document.getElementById("fRelease").textContent = item.first_release || "-";
    document.getElementById("fRuntime").textContent = item.runtime ? item.runtime + " min" : "-";
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

    const sourcesList = document.getElementById("sourcesList");
    if ((item.sources || []).length){
      sourcesList.innerHTML = item.sources.map(s => `
        <div class="sourceRow">
          <strong>${escapeHtml(s.source)}</strong>
          <span>${escapeHtml(s.external_id || "-")}</span>
        </div>
      `).join("");
    }

    renderOwnershipBadges(item);
    renderCollectionTab(item);

    detailStatus.style.display = "none";
    detailLayout.style.display = "grid";
    tabSection.style.display = "block";
  }

  async function loadDetail(){
    if (!contentId){
      detailStatus.textContent = "Mangler id i URL-en (?id=...).";
      detailStatus.style.color = "var(--danger)";
      return;
    }
    try {
      const res = await fetch("api.php?id=" + encodeURIComponent(contentId));
      const json = await res.json();
      if (json && json.error) throw new Error(json.error);
      renderDetail(json);
    } catch (err) {
      detailStatus.textContent = "Klarte ikke å hente data: " + err.message;
      detailStatus.style.color = "var(--danger)";
    }
  }

  loadDetail();
</script>
</body>
</html>
