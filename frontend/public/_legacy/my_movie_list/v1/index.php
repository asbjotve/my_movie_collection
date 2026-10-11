<!doctype html>
<html lang="no">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Min filmliste</title>
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
      flex-wrap: wrap;
    }
    header h1{ margin:0; font-size:16px; letter-spacing:.2px; color:var(--muted); font-weight:600; }
    .search{
      flex:1; min-width:220px; max-width:420px;
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
    .search input::placeholder{ color:#5c6690; }

    main{ padding:24px 18px 40px; display:grid; place-items:start center; }

    .card{
      width:min(100%, 960px);
      background: rgba(18,26,51,.65);
      border:1px solid rgba(37,48,87,.8);
      border-radius: 16px;
      padding:20px;
      overflow:hidden;
    }
    .card h2{ margin:0 0 6px; font-size:20px; }
    .card p.lead{ margin:0 0 20px; color:var(--muted); font-size:13px; line-height:1.5; }

    table{
      width:100%;
      border-collapse: collapse;
      font-size:14px;
    }
    thead th{
      text-align:left;
      color:var(--muted);
      font-size:12px;
      text-transform:uppercase;
      letter-spacing:.06em;
      padding:10px 12px;
      border-bottom:1px solid rgba(37,48,87,.9);
    }
    tbody td{
      padding:12px;
      border-bottom:1px solid rgba(37,48,87,.6);
      vertical-align:middle;
    }
    tbody tr:hover{ background: rgba(122,162,255,.06); }
    tbody tr:last-child td{ border-bottom:none; }

    .title-cell{ font-weight:600; }
    .id-cell{ color:var(--muted); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:13px; }
    .id-cell.empty{ color:#4a5480; font-style:italic; }

    .empty-state{
      text-align:center;
      color:var(--muted);
      padding:30px 12px;
      font-size:14px;
    }

    .count{ color:var(--muted); font-size:12px; }

    @media (max-width: 640px){
      table, thead, tbody, th, td, tr{ display:block; }
      thead{ display:none; }
      tbody tr{
        margin-bottom:12px;
        border:1px solid rgba(37,48,87,.7);
        border-radius:12px;
        padding:10px;
      }
      tbody td{
        border-bottom:none;
        padding:6px 4px;
        display:flex;
        justify-content:space-between;
        gap:10px;
      }
      tbody td::before{
        content: attr(data-label);
        color:var(--muted);
        font-size:11px;
        text-transform:uppercase;
        letter-spacing:.05em;
      }
    }
  </style>
</head>
<body>
<header>
  <h1>Min filmliste</h1>
  <div class="search">
    <input id="q" placeholder="Søk etter tittel…" autocomplete="off" />
  </div>
  <div class="count" id="count"></div>
</header>

<main>
  <section class="card">
    <h2>Filmer</h2>
    <p class="lead">Enkel oversikt over filmer med tilhørende eksterne ID-er (IMDb, TMDB, TVDB).</p>

    <table>
      <thead>
        <tr>
          <th>Tittel</th>
          <th>IMDb ID</th>
          <th>TMDB ID</th>
          <th>TVDB ID</th>
        </tr>
      </thead>
      <tbody id="movieRows"></tbody>
    </table>

    <div class="empty-state" id="emptyState" style="display:none;">Ingen filmer matcher søket.</div>
  </section>
</main>

<script>
  // Demo-data (10 filmtitler)
  const movies = [
    { title: "Dune",                 imdb_id: "tt1160419", tmdb_id: "438631", tvdb_id: null },
    { title: "Blade Runner",         imdb_id: "tt0083658", tmdb_id: "78",     tvdb_id: null },
    { title: "Inception",            imdb_id: "tt1375666", tmdb_id: "27205",  tvdb_id: null },
    { title: "The Matrix",           imdb_id: "tt0133093", tmdb_id: "603",    tvdb_id: null },
    { title: "Interstellar",         imdb_id: "tt0816692", tmdb_id: "157336", tvdb_id: null },
    { title: "Pulp Fiction",         imdb_id: "tt0110912", tmdb_id: "680",    tvdb_id: null },
    { title: "The Dark Knight",      imdb_id: "tt0468569", tmdb_id: "155",    tvdb_id: null },
    { title: "Fight Club",           imdb_id: "tt0137523", tmdb_id: "550",    tvdb_id: null },
    { title: "Django Unchained",     imdb_id: "tt1853728", tmdb_id: "68718",  tvdb_id: null },
    { title: "Planet Earth II",      imdb_id: "tt5491994", tmdb_id: "68595",  tvdb_id: "295760" },
  ];

  const rowsEl = document.getElementById("movieRows");
  const emptyStateEl = document.getElementById("emptyState");
  const countEl = document.getElementById("count");
  const q = document.getElementById("q");

  function idCell(value) {
    return value
      ? `<span class="id-cell">${value}</span>`
      : `<span class="id-cell empty">–</span>`;
  }

  function render() {
    const term = q.value.trim().toLowerCase();
    const filtered = movies.filter(m => m.title.toLowerCase().includes(term));

    rowsEl.innerHTML = filtered.map(m => `
      <tr>
        <td data-label="Tittel" class="title-cell">${m.title}</td>
        <td data-label="IMDb ID">${idCell(m.imdb_id)}</td>
        <td data-label="TMDB ID">${idCell(m.tmdb_id)}</td>
        <td data-label="TVDB ID">${idCell(m.tvdb_id)}</td>
      </tr>
    `).join("");

    emptyStateEl.style.display = filtered.length === 0 ? "block" : "none";
    countEl.textContent = `${filtered.length} av ${movies.length} filmer`;
  }

  q.addEventListener("input", render);
  render();
</script>
</body>
</html>
