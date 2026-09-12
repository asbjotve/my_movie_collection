<?php
declare(strict_types=1);

/**
 * tv_series_add_form v1 - first draft/prototype for registering a
 * physical TV series box set (season/episode/disc structure).
 *
 * This mirrors the early stages of bulk_add_movies_form (v1-v3): a
 * single-page form for manual entry with a JSON payload preview, no
 * backend submission yet and no TVDB search yet (see
 * documentation/bulk_add_movies_form_REVISION_HISTORY.md for how the
 * movie form evolved from this same starting point). The intent is
 * for this to be a base to discuss and iterate on, not a finished
 * feature.
 *
 * Payload shape ("kind": "tv_series_boxset"):
 *   series:  { title, imdb_id, tvdb_id }
 *   box:     { format, box_set_barcode, storage_id, copy_count }
 *   seasons: [ { season_number, title, air_date, inner_case_ean,
 *                episodes: [ { episode_number, title, runtime,
 *                original_air_date } ] } ]
 *   discs:   [ { order, format, label, season_number, inner_case_ean,
 *                storage_slot_no, add_to_storage,
 *                episode_refs: [ { season_number, episode_number } ] } ]
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';
require_login();

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="nb">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Registrer TV-serie-boks (v1, utkast)</title>
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
      --radius:16px;
      --shadow:0 18px 40px rgba(0,0,0,.25);
    }
    *{ box-sizing:border-box; }
    html, body{ margin:0; min-height:100%; }
    body{
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
      background:
        radial-gradient(circle at top left, rgba(111,141,255,.16), transparent 28%),
        radial-gradient(circle at top right, rgba(61,220,151,.10), transparent 24%),
        var(--bg);
      color:var(--text);
    }
    button, input, select, textarea{ font:inherit; }
    button{ cursor:pointer; }
    .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    .page{ max-width:1160px; margin:0 auto; padding:24px 18px 60px; }
    .hero{
      margin-bottom:18px;
      padding:18px 20px;
      background:rgba(20,26,51,.92);
      border:1px solid var(--line);
      border-radius:20px;
      box-shadow:var(--shadow);
    }
    .hero h1{ margin:0 0 6px; font-size:26px; }
    .hero p{ margin:0; color:var(--muted); max-width:800px; }
    .draftTag{
      display:inline-block;
      margin-bottom:10px;
      padding:3px 10px;
      border-radius:999px;
      border:1px solid rgba(255,211,106,.4);
      background:rgba(255,211,106,.12);
      color:#ffd36a;
      font-size:12px;
    }
    .card{
      background:rgba(20,26,51,.95);
      border:1px solid var(--line);
      border-radius:18px;
      box-shadow:var(--shadow);
      margin-bottom:18px;
    }
    .cardHead{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:12px;
      padding:16px 18px 0;
    }
    .cardHead h2{ margin:0; font-size:18px; }
    .cardBody{ padding:18px; }
    .grid{ display:grid; gap:12px; }
    .grid.cols2{ grid-template-columns:repeat(2, minmax(0,1fr)); }
    .grid.cols4{ grid-template-columns:repeat(4, minmax(0,1fr)); }
    label{ display:block; font-size:12px; color:var(--muted); margin-bottom:4px; }
    input[type=text], input[type=number], input[type=date], select{
      width:100%;
      padding:8px 10px;
      background:var(--panel2);
      border:1px solid var(--line);
      border-radius:10px;
      color:var(--text);
    }
    .field{ margin-bottom:4px; }
    .btn{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:8px 14px;
      border-radius:10px;
      border:1px solid var(--line);
      background:var(--panel2);
      color:var(--text);
      text-decoration:none;
    }
    .btn.primary{ background:var(--accent); border-color:var(--accent); color:#0c1024; font-weight:600; }
    .btn.good{ background:var(--accent2); border-color:var(--accent2); color:#0c1024; font-weight:600; }
    .btn.danger{ border-color:rgba(255,138,138,.5); color:var(--danger); background:transparent; }
    .btn.small{ padding:5px 10px; font-size:12px; }
    table{ width:100%; border-collapse:collapse; }
    th, td{ text-align:left; padding:6px 8px; border-bottom:1px solid var(--line); font-size:13px; vertical-align:top; }
    th{ color:var(--muted); font-weight:600; font-size:12px; }
    .seasonBlock{
      border:1px solid var(--line);
      border-radius:14px;
      padding:14px;
      margin-bottom:14px;
      background:var(--panel2);
    }
    .seasonBlock .seasonHead{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
      margin-bottom:10px;
    }
    .seasonBlock .seasonHead h3{ margin:0; font-size:15px; }
    .discRow select[multiple]{ min-height:70px; }
    .muted{ color:var(--muted); font-size:12px; }
    pre#payloadPreview{
      background:var(--panel2);
      border:1px solid var(--line);
      border-radius:14px;
      padding:14px;
      max-height:420px;
      overflow:auto;
      font-size:12px;
      white-space:pre-wrap;
      word-break:break-word;
    }
    .actionsRow{ display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; }
    .tvdbResultRow{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
      padding:8px 10px;
      border:1px solid var(--line);
      border-radius:10px;
      background:var(--panel2);
      margin-bottom:6px;
    }
    .tvdbResultRow .info{ font-size:13px; }
    .tvdbResultRow .info .muted{ display:block; }
    .tvdbThumb{ cursor:zoom-in; }
    .lightboxOverlay{
      display:none;
      position:fixed;
      inset:0;
      background:rgba(6,8,20,.85);
      z-index:50;
      align-items:center;
      justify-content:center;
      cursor:zoom-out;
    }
    .lightboxOverlay.open{ display:flex; }
    .lightboxOverlay img{
      max-width:90vw;
      max-height:90vh;
      border-radius:12px;
      box-shadow:var(--shadow);
    }
  </style>
</head>
<body>
<div class="page">

  <div class="hero">
    <span class="draftTag">Utkast / v1 - ikke koblet til backend ennå</span>
    <h1>Registrer TV-serie-boks</h1>
    <p>
      Første utkast til skjema for fysiske TV-serie-bokser (sesong/episode/disk).
      Fyll ut manuelt her og bruk JSON-forhåndsvisningen nederst som utgangspunkt
      for videre diskusjon - ingen data sendes til serveren fra denne versjonen ennå.
      TVDB er tenkt som primærkilde for sesong-/episodedata senere (mer utfyllende enn TMDB),
      men søk er ikke koblet inn i v1. Episoder angis foreløpig kun med antall per
      sesong (ikke tittel/varighet/sendedato per episode) - det er tenkt hentet fra
      TVDB senere i stedet for å fylles ut manuelt.
    </p>
  </div>

  <div class="card">
    <div class="cardHead"><h2>1. Serien</h2></div>
    <div class="cardBody">
      <div class="grid cols2">
        <div class="field">
          <label for="seriesTitle">Tittel</label>
          <input type="text" id="seriesTitle" placeholder="f.eks. Breaking Bad">
        </div>
        <div class="field">
          <label for="seriesImdb">IMDb-ID</label>
          <input type="text" id="seriesImdb" placeholder="tt0903747">
        </div>
        <div class="field">
          <label for="seriesTvdb">TVDB-ID</label>
          <input type="text" id="seriesTvdb" placeholder="81189">
        </div>
      </div>
      <div class="actionsRow" style="margin-top:12px;">
        <button type="button" class="btn small" id="searchTvdbBtn">Søk TVDB</button>
        <span class="muted" id="tvdbSearchStatus"></span>
      </div>
      <div id="tvdbResults" style="margin-top:10px;"></div>
    </div>
  </div>

  <div class="card">
    <div class="cardHead"><h2>2. Boksen (fysisk emballasje)</h2></div>
    <div class="cardBody">
      <div class="grid cols4">
        <div class="field">
          <label for="boxFormat">Format</label>
          <select id="boxFormat">
            <option value="DVD">DVD</option>
            <option value="Blu-ray">Blu-ray</option>
            <option value="4K UHD">4K UHD</option>
          </select>
        </div>
        <div class="field">
          <label for="boxBarcode">Boks-strekkode (EAN)</label>
          <input type="text" id="boxBarcode" placeholder="7031...">
        </div>
        <div class="field">
          <label for="storageId">storage_id</label>
          <input type="text" id="storageId" placeholder="UUID for hylle/kasse">
        </div>
        <div class="field">
          <label for="copyCount">Antall eksemplarer</label>
          <input type="number" id="copyCount" value="1" min="1">
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="cardHead">
      <h2>3. Sesonger og episoder</h2>
      <button type="button" class="btn small" id="addSeasonBtn">+ Legg til sesong</button>
    </div>
    <div class="cardBody">
      <div id="seasonsContainer"></div>
      <p class="muted" id="noSeasonsMsg">Ingen sesonger lagt til ennå.</p>
    </div>
  </div>

  <div class="card">
    <div class="cardHead">
      <h2>4. Disker</h2>
      <button type="button" class="btn small" id="addDiscBtn">+ Legg til disk</button>
    </div>
    <div class="cardBody">
      <table id="discTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Format</th>
            <th>Etikett</th>
            <th>Sesong</th>
            <th>Lagerplass nr.</th>
            <th>Til lager?</th>
            <th>Episoder på disken</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="discTableBody"></tbody>
      </table>
      <p class="muted" id="noDiscsMsg">Ingen disker lagt til ennå.</p>
    </div>
  </div>

  <div class="card">
    <div class="cardHead"><h2>5. JSON-forhåndsvisning</h2></div>
    <div class="cardBody">
      <div class="actionsRow">
        <button type="button" class="btn primary" id="previewBtn">Bygg JSON-forhåndsvisning</button>
        <button type="button" class="btn" id="copyBtn">Kopier JSON</button>
      </div>
      <pre id="payloadPreview" class="mono">(ikke generert ennå)</pre>
    </div>
  </div>

</div>
<script src="script.js"></script>
<div class="lightboxOverlay" id="lightboxOverlay">
  <img id="lightboxImg" src="" alt="">
</div>
</body>
</html>
