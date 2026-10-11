<!doctype html>
<html lang="no">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Media-katalog (demo)</title>
  <style>
    :root{
      --bg:#0b1020;
      --panel:#121a33;
      --panel2:#0f1730;
      --text:#e8ecff;
      --muted:#a8b2d8;
      --line:#253057;
      --accent:#7aa2ff;
      --good:#3ddc97;
    }
    *{ box-sizing:border-box; }
    body{
      margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
      background: radial-gradient(1200px 500px at 20% -10%, #1b2a66, transparent),
                  radial-gradient(900px 400px at 80% 0%, #35215c, transparent),
                  var(--bg);
      color:var(--text);
    }
    header{
      position:sticky; top:0; z-index:10;
      backdrop-filter: blur(10px);
      background: rgba(11,16,32,.75);
      border-bottom: 1px solid rgba(37,48,87,.7);
      padding:16px 18px;
      display:flex; gap:12px; align-items:center; justify-content:space-between;
    }
    header h1{ margin:0; font-size:16px; letter-spacing:.2px; color:var(--muted); font-weight:600; }
    .search{
      flex:1; max-width:720px;
      display:flex; gap:10px; align-items:center;
      background: rgba(18,26,51,.7);
      border:1px solid rgba(37,48,87,.8);
      border-radius: 12px;
      padding:10px 12px;
    }
    .search input{
      width:100%;
      background: transparent; border:0; outline:0; color:var(--text);
      font-size:14px;
    }
    main{ padding:18px; display:grid; grid-template-columns: 280px 1fr; gap:18px; }
    aside{
      background: rgba(18,26,51,.65);
      border:1px solid rgba(37,48,87,.8);
      border-radius: 14px;
      padding:14px;
      height: calc(100vh - 90px);
      position: sticky; top: 76px;
      overflow:auto;
    }
    .filter h2{ font-size:13px; margin:0 0 10px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:.08em;}
    .chiprow{ display:flex; flex-wrap:wrap; gap:8px; }
    .chip{
      padding:8px 10px; border-radius:999px;
      border:1px solid rgba(37,48,87,.9);
      background: rgba(15,23,48,.6);
      color:var(--muted);
      cursor:pointer;
      font-size:12px;
      user-select:none;
    }
    .chip.active{ border-color: rgba(122,162,255,.85); color: var(--text); }
    .grid{
      display:grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap:14px;
      align-content:start;
    }
    .card{
      background: rgba(18,26,51,.55);
      border:1px solid rgba(37,48,87,.8);
      border-radius: 14px;
      overflow:hidden;
      cursor:pointer;
      transition: transform .12s ease, border-color .12s ease;
    }
    .card:hover{ transform: translateY(-2px); border-color: rgba(122,162,255,.7); }
    .cover{
      height:260px;
      background: linear-gradient(135deg, rgba(122,162,255,.35), rgba(61,220,151,.18));
      display:flex; align-items:flex-end; justify-content:flex-start;
      padding:12px;
    }
    .badge{
      font-size:12px; padding:6px 10px; border-radius:999px;
      background: rgba(11,16,32,.65);
      border: 1px solid rgba(232,236,255,.18);
      color: var(--text);
    }
    .meta{ padding:12px; display:grid; gap:6px; }
    .title{ font-weight:700; }
    .sub{ color:var(--muted); font-size:12px; display:flex; gap:10px; flex-wrap:wrap; }
    .pill{ color: var(--muted); font-size:12px; }
    .watched{ color: var(--good); font-weight:700; }

    /* Modal */
    .modalBack{
      position:fixed; inset:0; display:none; place-items:center;
      background: rgba(0,0,0,.55);
      padding:18px;
    }
    .modal{
      width:min(980px, 100%);
      background: rgba(18,26,51,.92);
      border:1px solid rgba(37,48,87,.9);
      border-radius:16px;
      overflow:hidden;
    }
    .modalHeader{
      display:flex; justify-content:space-between; align-items:flex-start;
      gap:12px;
      padding:16px;
      border-bottom:1px solid rgba(37,48,87,.8);
    }
    .modalHeader h3{ margin:0; font-size:18px; }
    .closeBtn{
      background: transparent; border:1px solid rgba(37,48,87,.9);
      color: var(--text);
      border-radius: 10px;
      padding:8px 10px;
      cursor:pointer;
    }
    .modalBody{
      padding:16px;
      display:grid;
      grid-template-columns: 280px 1fr;
      gap:16px;
    }
    .panel{
      background: rgba(15,23,48,.6);
      border:1px solid rgba(37,48,87,.8);
      border-radius: 14px;
      padding:12px;
    }
    .panel h4{
      margin:0 0 10px; color:var(--muted); font-size:12px;
      text-transform:uppercase; letter-spacing:.08em;
    }
    .kv{ display:grid; grid-template-columns: 1fr 1fr; gap:8px 12px; }
    .k{ color:var(--muted); font-size:12px; }
    .v{ font-size:13px; }
    .list{
      display:grid; gap:8px;
    }
    .row{
      display:flex; justify-content:space-between; gap:10px; align-items:center;
      padding:10px 10px; border-radius:12px;
      border:1px solid rgba(37,48,87,.7);
      background: rgba(11,16,32,.35);
    }
    .row strong{ font-size:13px; }
    .row small{ color:var(--muted); }
  </style>
</head>
<body>
<header>
  <h1>Media-katalog</h1>
  <div class="search">
    <input id="q" placeholder="Søk tittel / original tittel…" />
  </div>
  <div style="color:var(--muted); font-size:12px;">Demo UI</div>
</header>

<main>
  <aside>
    <div class="filter">
      <h2>Filter</h2>
      <div style="color:var(--muted); font-size:12px; margin-bottom:10px;">
        (Dette tilsvarer typisk query på <code>content</code>)
      </div>
      <div class="chiprow" id="typeChips"></div>
      <div style="margin-top:12px;">
        <label style="display:flex; gap:10px; align-items:center; color:var(--muted); font-size:12px;">
          <input id="onlyUnwatched" type="checkbox" />
          Vis bare ikke-sett
        </label>
      </div>
    </div>
  </aside>

  <section>
    <div class="grid" id="grid"></div>
  </section>
</main>

<div class="modalBack" id="modalBack" role="dialog" aria-modal="true">
  <div class="modal">
    <div class="modalHeader">
      <div>
        <h3 id="mTitle"></h3>
        <div id="mSub" style="color:var(--muted); font-size:12px; margin-top:4px;"></div>
      </div>
      <button class="closeBtn" id="closeBtn">Lukk</button>
    </div>
    <div class="modalBody">
      <div class="panel">
        <h4>Content (fra content-tabellen)</h4>
        <div class="kv" id="mKv"></div>
      </div>
      <div class="panel">
        <h4>Utgaver du eier (physical_collection)</h4>
        <div class="list" id="mCollections"></div>

        <div style="height:12px;"></div>
        <h4>Eksterne kilder (content_external_source)</h4>
        <div class="list" id="mSources"></div>
      </div>
    </div>
  </div>
</div>

<script>
  // Demo-data (tilsvarer join mellom content + content_in_physical_collection + physical_collection + content_external_source)
  const data = [
    {
      content_id: "c1",
      title: "Dune",
      original_title: "Dune",
      first_release: "2021-10-22",
      runtime: 155,
      age_restriction: "PG-13",
      watched_flag: 1,
      temporary_flag: 0,
      content_type: "movie",
      collections: [
        { collection_id:"col1", format:"4K UHD", barcode:"1234567890123", box_set_barcode:"", notes:"Collector's Edition" }
      ],
      sources: [
        { source:"tmdb", external_id:"438631", fetched_at:"2026-04-01" }
      ]
    },
    {
      content_id: "c2",
      title: "Blade Runner",
      original_title: "Blade Runner",
      first_release: "1982-06-25",
      runtime: 117,
      age_restriction: "R",
      watched_flag: 0,
      temporary_flag: 0,
      content_type: "movie",
      collections: [
        { collection_id:"col2", format:"Blu-ray", barcode:"9999999999999", box_set_barcode:"", notes:"Final Cut" },
        { collection_id:"col3", format:"DVD", barcode:"8888888888888", box_set_barcode:"", notes:"" }
      ],
      sources: [
        { source:"imdb", external_id:"tt0083658", fetched_at:"2026-03-20" }
      ]
    },
    {
      content_id: "c3",
      title: "Planet Earth II",
      original_title: "Planet Earth II",
      first_release: "2016-11-06",
      runtime: 360,
      age_restriction: "TV-G",
      watched_flag: 1,
      temporary_flag: 0,
      content_type: "series",
      collections: [
        { collection_id:"col4", format:"Blu-ray Box Set", barcode:"7777777777777", box_set_barcode:"7777777777000", notes:"6 episoder" }
      ],
      sources: [
        { source:"tmdb", external_id:"68595", fetched_at:"2026-03-15" }
      ]
    },
  ];

  const types = ["movie","series","concert","other"];
  let activeType = null;

  const grid = document.getElementById("grid");
  const q = document.getElementById("q");
  const typeChips = document.getElementById("typeChips");
  const onlyUnwatched = document.getElementById("onlyUnwatched");

  function renderChips(){
    typeChips.innerHTML = "";
    const all = document.createElement("div");
    all.className = "chip" + (activeType === null ? " active" : "");
    all.textContent = "Alle";
    all.onclick = () => { activeType = null; render(); renderChips(); };
    typeChips.appendChild(all);

    for (const t of types){
      const el = document.createElement("div");
      el.className = "chip" + (activeType === t ? " active" : "");
      el.textContent = t;
      el.onclick = () => { activeType = t; render(); renderChips(); };
      typeChips.appendChild(el);
    }
  }

  function card(item){
    const el = document.createElement("div");
    el.className = "card";
    el.onclick = () => openModal(item);

    const cover = document.createElement("div");
    cover.className = "cover";
    const badge = document.createElement("div");
    badge.className = "badge";
    badge.textContent = item.content_type.toUpperCase();
    cover.appendChild(badge);

    const meta = document.createElement("div");
    meta.className = "meta";
    meta.innerHTML = `
      <div class="title">${item.title}</div>
      <div class="sub">
        <span>${item.first_release}</span>
        <span>${item.runtime} min</span>
        <span class="pill">${item.age_restriction}</span>
        <span class="${item.watched_flag ? "watched": ""}">${item.watched_flag ? "SETT" : "IKKE SETT"}</span>
      </div>
      <div class="sub">Utgaver: ${item.collections.length}</div>
    `;

    el.appendChild(cover);
    el.appendChild(meta);
    return el;
  }

  function render(){
    const term = q.value.trim().toLowerCase();
    const showUnwatched = onlyUnwatched.checked;

    const filtered = data.filter(x => {
      if (activeType && x.content_type !== activeType) return false;
      if (showUnwatched && x.watched_flag) return false;
      if (!term) return true;
      return (x.title || "").toLowerCase().includes(term) ||
             (x.original_title || "").toLowerCase().includes(term);
    });

    grid.innerHTML = "";
    for (const item of filtered) grid.appendChild(card(item));
  }

  // Modal
  const modalBack = document.getElementById("modalBack");
  const closeBtn = document.getElementById("closeBtn");
  const mTitle = document.getElementById("mTitle");
  const mSub = document.getElementById("mSub");
  const mKv = document.getElementById("mKv");
  const mCollections = document.getElementById("mCollections");
  const mSources = document.getElementById("mSources");

  function openModal(item){
    mTitle.textContent = item.title;
    mSub.textContent = `Original: ${item.original_title} • Type: ${item.content_type}`;

    const kv = [
      ["first_release", item.first_release],
      ["runtime", item.runtime + " min"],
      ["age_restriction", item.age_restriction],
      ["watched_flag", item.watched_flag ? "1" : "0"],
      ["temporary_flag", item.temporary_flag ? "1" : "0"],
      ["content_id", item.content_id],
    ];
    mKv.innerHTML = kv.map(([k,v]) => `
      <div class="k">${k}</div><div class="v">${v}</div>
    `).join("");

    mCollections.innerHTML = item.collections.map(c => `
      <div class="row">
        <div>
          <strong>${c.format}</strong><br/>
          <small>barcode: ${c.barcode || "-"}</small><br/>
          <small>box_set_barcode: ${c.box_set_barcode || "-"}</small>
        </div>
        <small>${c.notes || ""}</small>
      </div>
    `).join("");

    mSources.innerHTML = item.sources.map(s => `
      <div class="row">
        <div>
          <strong>${s.source}</strong><br/>
          <small>external_id: ${s.external_id}</small>
        </div>
        <small>fetched_at: ${s.fetched_at}</small>
      </div>
    `).join("");

    modalBack.style.display = "grid";
  }

  function closeModal(){ modalBack.style.display = "none"; }
  closeBtn.onclick = closeModal;
  modalBack.addEventListener("click", (e) => { if (e.target === modalBack) closeModal(); });

  q.addEventListener("input", render);
  onlyUnwatched.addEventListener("change", render);

  renderChips();
  render();
</script>
</body>
</html>
