<?php
// Wishlist View v1
// Fetches the "Wishlist" custom list from the backend API and renders it
// as a mobile-friendly table (collapses to stacked cards on small screens).

$apiEndpoint = 'http://172.19.0.1:9500/wishlist/movies';

$movies = [];
$fetchError = null;

$ch = curl_init($apiEndpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $curlError) {
    $fetchError = 'Could not reach the wishlist API: ' . htmlspecialchars($curlError);
} elseif ($httpCode !== 200) {
    $fetchError = 'Wishlist API returned HTTP ' . $httpCode . '.';
} else {
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        $fetchError = 'Unexpected response from wishlist API.';
    } else {
        $movies = $decoded;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Wishlist</title>
<style>
  :root {
    --bg:#0b1020; --panel:#121a33; --panel2:#0f1730; --text:#e8ecff;
    --muted:#a8b2d8; --line:#253057; --accent:#7aa2ff; --good:#3ddc97; --bad:#ff6b6b;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
    color: var(--text);
    background: radial-gradient(1200px 800px at 80% -10%, #16214a 0%, var(--bg) 60%);
    min-height: 100vh;
  }
  header {
    position: sticky; top: 0; z-index: 10;
    backdrop-filter: blur(8px);
    background: rgba(11,16,32,0.75);
    border-bottom: 1px solid var(--line);
    padding: 18px 24px;
  }
  header h1 { margin: 0; font-size: 1.4rem; }
  header p { margin: 4px 0 0; color: var(--muted); font-size: 0.9rem; }
  main { max-width: 1000px; margin: 0 auto; padding: 24px 16px 60px; }
  .search {
    width: 100%;
    padding: 12px 14px;
    margin-bottom: 18px;
    border-radius: 10px;
    border: 1px solid var(--line);
    background: var(--panel2);
    color: var(--text);
    font-size: 1rem;
  }
  .search:focus { outline: none; border-color: var(--accent); }
  .empty, .error {
    color: var(--muted);
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 16px;
    text-align: center;
  }
  .error { color: var(--bad); border-color: var(--bad); }
  table {
    width: 100%;
    border-collapse: collapse;
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 12px;
    overflow: hidden;
  }
  thead th {
    text-align: left;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--muted);
    background: var(--panel2);
    padding: 12px 14px;
    border-bottom: 1px solid var(--line);
  }
  tbody td {
    padding: 12px 14px;
    border-bottom: 1px solid var(--line);
    font-size: 0.95rem;
    vertical-align: middle;
  }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:hover { background: rgba(122,162,255,0.06); }
  .cover {
    width: 46px; height: 66px;
    object-fit: cover;
    border-radius: 6px;
    background: var(--panel2);
    display: block;
  }
  .title { font-weight: 600; }
  .year { color: var(--muted); font-weight: 400; margin-left: 6px; }
  .idlink { color: var(--accent); text-decoration: none; font-size: 0.85rem; }
  .idlink:hover { text-decoration: underline; }
  .muted { color: var(--muted); }

  /* Mobile: collapse table into stacked cards */
  @media (max-width: 640px) {
    thead { display: none; }
    table, tbody, tr, td { display: block; width: 100%; }
    table { border: none; background: none; }
    tr {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: 12px;
      margin-bottom: 12px;
      padding: 12px;
    }
    td {
      border-bottom: none;
      padding: 4px 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
    }
    td::before {
      content: attr(data-label);
      color: var(--muted);
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.04em;
      flex-shrink: 0;
    }
    td.cell-title { justify-content: flex-start; }
    td.cell-title::before { display: none; }
  }
</style>
</head>
<body>
<header>
  <h1>Wishlist</h1>
  <p>Movies added to the wishlist.</p>
</header>
<main>
  <input type="text" id="search" class="search" placeholder="Filter by title…" autocomplete="off">

  <?php if ($fetchError): ?>
    <div class="error"><?= $fetchError ?></div>
  <?php elseif (empty($movies)): ?>
    <div class="empty">The wishlist is empty.</div>
  <?php else: ?>
    <table id="wishlist-table">
      <thead>
        <tr>
          <th></th>
          <th>Title</th>
          <th>IMDb</th>
          <th>TMDB</th>
          <th>TVDB</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($movies as $m):
            $title = $m['title'] ?? '';
            $year = $m['first_release_year'] ?? null;
            $cover = $m['cover_image'] ?? null;
            $imdb = $m['imdb_id'] ?? null;
            $tmdb = $m['tmdb_id'] ?? null;
            $tvdb = $m['tvdb_id'] ?? null;
        ?>
        <tr data-title="<?= htmlspecialchars(mb_strtolower($title)) ?>">
          <td class="cell-cover">
            <?php if ($cover): ?>
              <img class="cover" src="<?= htmlspecialchars($cover) ?>" alt="" loading="lazy">
            <?php else: ?>
              <div class="cover"></div>
            <?php endif; ?>
          </td>
          <td class="cell-title" data-label="Title">
            <span class="title"><?= htmlspecialchars($title) ?></span>
            <?php if ($year): ?><span class="year">(<?= (int) $year ?>)</span><?php endif; ?>
          </td>
          <td data-label="IMDb">
            <?php if ($imdb): ?>
              <a class="idlink" target="_blank" rel="noopener" href="https://www.imdb.com/title/<?= htmlspecialchars($imdb) ?>/"><?= htmlspecialchars($imdb) ?></a>
            <?php else: ?><span class="muted">–</span><?php endif; ?>
          </td>
          <td data-label="TMDB">
            <?php if ($tmdb): ?>
              <a class="idlink" target="_blank" rel="noopener" href="https://www.themoviedb.org/movie/<?= htmlspecialchars($tmdb) ?>"><?= htmlspecialchars($tmdb) ?></a>
            <?php else: ?><span class="muted">–</span><?php endif; ?>
          </td>
          <td data-label="TVDB">
            <?php if ($tvdb): ?>
              <a class="idlink" target="_blank" rel="noopener" href="https://thetvdb.com/search?query=<?= htmlspecialchars($tvdb) ?>"><?= htmlspecialchars($tvdb) ?></a>
            <?php else: ?><span class="muted">–</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p id="no-results" class="empty" style="display:none; margin-top: 12px;">No titles match your search.</p>
  <?php endif; ?>
</main>
<script>
  const searchInput = document.getElementById('search');
  const table = document.getElementById('wishlist-table');
  const noResults = document.getElementById('no-results');

  if (searchInput && table) {
    const rows = Array.from(table.querySelectorAll('tbody tr'));
    searchInput.addEventListener('input', () => {
      const query = searchInput.value.trim().toLowerCase();
      let visibleCount = 0;
      rows.forEach(row => {
        const match = row.dataset.title.includes(query);
        row.style.display = match ? '' : 'none';
        if (match) visibleCount++;
      });
      if (noResults) {
        noResults.style.display = visibleCount === 0 ? 'block' : 'none';
      }
    });
  }
</script>
</body>
</html>
