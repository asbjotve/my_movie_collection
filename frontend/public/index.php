<?php
// Overview page listing all solutions/prototypes found under frontend/public,
// with links to each version's entry point.

$solutions = [
    'add_to_wishlist' => [
        'title' => 'Add to Wishlist',
        'desc'  => 'Upload a movie cover and add it to the fixed "Wishlist" custom list.',
        'entries' => [
            'v1' => 'v1/bildopp.php',
            'v2' => 'v2/bildopp.php',
            'v3' => 'v3/bildopp.php',
            'v4' => 'v4/bildopp.php',
        ],
    ],
    'bulk_add_movies_form' => [
        'title' => 'Bulk Add Movies Form',
        'desc'  => 'Add many movies at once, with TMDB lookup support in later versions.',
        'entries' => [
            'v1' => 'v1/index.php', 'v2' => 'v2/index.php', 'v3' => 'v3/index.php',
            'v4' => 'v4/index.php', 'v5' => 'v5/index.php', 'v6' => 'v6/index.php',
            'v7' => 'v7/index.php', 'v8' => 'v8/index.php', 'v9' => 'v9/index.php',
            'v10' => 'v10/index.php', 'v11' => 'v11/index.php', 'v12' => 'v12/index.php',
            'v13' => 'v13/index.php',
        ],
    ],
    'custom_list_manager' => [
        'title' => 'Custom List Manager',
        'desc'  => 'Create new custom lists and add items to them via a dropdown (excludes the Wishlist).',
        'entries' => [
            'v1' => 'v1/index.php',
            'v2' => 'v2/index.php',
            'v3' => 'v3/index.php',
        ],
    ],
    'wishlist_view' => [
        'title' => 'Wishlist View',
        'desc'  => 'Mobile-friendly table listing all movies added to the wishlist.',
        'entries' => [
            'v1' => 'v1/index.php',
        ],
    ],
    'my_movie_list' => [
        'title' => 'My Movie List',
        'desc'  => 'A simple searchable table of movies (title, IMDb/TMDB/TVDB IDs).',
        'entries' => [
            'v1' => 'v1/index.php',
        ],
    ],
    'temp_add_movie_barcode' => [
        'title' => 'Add Movie via Barcode (temp)',
        'desc'  => 'Prototype for adding a movie by scanning/entering a barcode.',
        'entries' => [
            'v1' => 'v1/index.html',
        ],
    ],
    'tmdb_live_search' => [
        'title' => 'TMDB Live Search',
        'desc'  => 'Live search against TMDB while typing a title.',
        'entries' => [
            'v1' => 'v1/index.php',
            'v2' => 'v2/live_search.php', 'v3' => 'v3/live_search.php',
            'v4' => 'v4/live_search.php', 'v5' => 'v5/live_search.php',
            'v6' => 'v6/live_search.php', 'v7' => 'v7/live_search.php',
        ],
    ],
    'tvdb_live_search' => [
        'title' => 'TVDB Live Search',
        'desc'  => 'Live search against TheTVDB while typing a title.',
        'entries' => [
            'v1' => 'v1/tvdb_search.php', 'v2' => 'v2/tvdb_search.php',
            'v3' => 'v3/tvdb_search.php', 'v4' => 'v4/tvdb_search.php',
        ],
    ],
    'website_template_example' => [
        'title' => 'Website Template Example',
        'desc'  => 'Design/layout prototypes for the overall site theme.',
        'entries' => [
            'v1' => 'v1/index.html', 'v2' => 'v2/index.html', 'v3' => 'v3/index.html',
            'v4' => 'v4/index.html', 'v5' => 'v5/index.html', 'v6' => 'v6/index.html',
            'v7' => 'v7/index.html', 'v8' => 'v8/index.html', 'v9' => 'v9/index.html',
            'v10' => 'v10/index.html', 'v11' => 'v11/index.html', 'v12' => 'v12/index.html',
            'v13' => 'v13/index.html', 'v14' => 'v14/index.php',
            'v15' => 'v15/index.php', 'v16' => 'v16/index.php',
        ],
    ],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Solutions Overview – My Movie Collection</title>
<style>
  :root {
    --bg:#0b1020; --panel:#121a33; --panel2:#0f1730; --text:#e8ecff;
    --muted:#a8b2d8; --line:#253057; --accent:#7aa2ff; --good:#3ddc97;
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
  main { max-width: 1100px; margin: 0 auto; padding: 28px 20px 60px; }
  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 18px;
  }
  .card {
    background: var(--panel);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 18px 18px 14px;
  }
  .card h2 { margin: 0 0 6px; font-size: 1.1rem; color: var(--text); }
  .card p.desc { margin: 0 0 14px; color: var(--muted); font-size: 0.85rem; line-height: 1.4; }
  .versions { display: flex; flex-wrap: wrap; gap: 8px; }
  .versions a {
    display: inline-block;
    text-decoration: none;
    color: var(--text);
    background: var(--panel2);
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 0.85rem;
    transition: border-color .15s, color .15s;
  }
  .versions a:hover { border-color: var(--accent); color: var(--accent); }
  .versions a.latest { border-color: var(--good); color: var(--good); }
  footer { text-align: center; color: var(--muted); font-size: 0.8rem; padding: 20px; }
</style>
</head>
<body>
<header>
  <h1>Solutions Overview</h1>
  <p>All prototypes/solutions available under <code>frontend/public</code>, with links to each version.</p>
</header>
<main>
  <div class="grid">
  <?php foreach ($solutions as $folder => $s):
      $versionKeys = array_keys($s['entries']);
      $latestKey = end($versionKeys);
  ?>
    <div class="card">
      <h2><?= htmlspecialchars($s['title']) ?></h2>
      <p class="desc"><?= htmlspecialchars($s['desc']) ?></p>
      <div class="versions">
        <?php foreach ($s['entries'] as $ver => $path): ?>
          <a class="<?= $ver === $latestKey ? 'latest' : '' ?>"
             href="<?= htmlspecialchars($folder . '/' . $path) ?>">
             <?= htmlspecialchars($ver) ?><?= $ver === $latestKey ? ' ★' : '' ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
</main>
<footer>★ = latest version</footer>
</body>
</html>
