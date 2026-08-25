# website_template_example – version overview

This document gives a short overview of what differs between the
various versions of `frontend/public/website_template_example/`. All
versions can be reached from the overview page at
`frontend/public/index.php`.

Versions 1–13 are static HTML/CSS/JS design experiments with
hardcoded demo data (the same 2–3 titles, e.g. "The Lord of the
Rings: The Fellowship of the Ring", "Blade Runner", "Hans Zimmer:
Live in Prague"), created to explore different layout/UX concepts for
a future detail/catalog page. Version 14 onward is PHP and gradually
moves toward a real database connection and a more app-like structure
with a top navigation menu.

## v1 – Library + detail view
A single content row can be linked to several physical editions, but
the detail view keeps the focus on the title first.

## v2 – Hero-style detail page
A large hero section and calmer subsections make the page feel more
like a detail page than a register/list.

## v3 – Fixed library on the left
App-like layout where the library stays fixed on the left and the
detail view updates on the right.

## v4 – Large top area, card-based rest
The top area is the main focus: a single selected title gets a large
visual space, while the rest of the information sits in smaller cards
below.

## v5 – Readable catalog overview
Prioritizes a readable, table/list-style catalog overview over large
visual cards.

## v6 – Tabs instead of long pages
Focuses on a single selected title and switches detail content via
tabs instead of long pages.

## v7 – Movie-poster oriented
A more movie-poster-oriented front page where the overall impression
matters more than table structure.

## v8 – Workspace with fixed filters
More of a workspace than a public-facing page, with filters always
visible on the left.

## v9 – Collection overview before details
Starts with a collection overview/dashboard before the selected title
gets its own detail space.

## v10 – Minimalist
Strips away everything unnecessary and lets the selected title stay
in the center – a calm, easy-to-read starting point for further UI
work.

## v11 – Database structure visible in the details
A TMDB-like top area for the title itself, but the underlying
database structure drives the details further down: a single content
row can have several physical editions, several copies per edition,
discs linked to each copy, and bonus content per disc.

## v12 – Classic movie detail page + separate tab for copies
Keeps the main area close to a classic movie detail page (hero,
synopsis, key facts, sources), while information about owned
copies/digital editions is moved into its own tab.

## v13 – Three tabs (movie / cast / collection)
Splits the content into three clear tabs: movie details, cast/crew,
and what you own physically or have available digitally.

## v14 – First PHP version, hardcoded "database-shaped" data
A self-contained, dark-themed page with search/filter/detail modal.
The JS `data` array is hardcoded, but shaped as if it came from a
join of `content` + `content_in_physical_collection` +
`physical_collection` + `content_external_source` – groundwork for
v15.

## v15 – First version with a real database connection
Based on v14, but now fetches data live from `db_mediearkiv` via
PHP/PDO:
- `config.php`: PDO connection using `MEDIA_DB_*` environment
  variables (phpdotenv).
- `api.php`: three separate, readable SQL queries (content,
  collections, sources), grouped in PHP into the same JSON shape as
  v14's demo data.
- `index.php`: fetches data via `fetch('api.php')` instead of a
  hardcoded array, with null-safe rendering for fields that can be
  missing in real data (`runtime`, `age_restriction`, `first_release`).

## v16 – New structure: top navigation (no database)
Built completely from scratch (not based on v14/v15). A sticky top
navbar with four items: **Mine filmer** (My movies), **Ønskeliste**
(Wishlist), **Andre lister** (Other lists), **Administrering**
(Administration) – switches the visible panel via JS without a page
reload. A pure frontend prototype with hardcoded demo data, no
database connection. Includes:
- A "Not logged in" indicator in the top bar, in preparation for
  future login functionality.
- Configurable access per menu item via the `$sectionAccess` array at
  the top of `index.php` (true/false per item = locked/open), with a
  lock icon and explanatory note boxes in the panels.
- A comment block at the top showing exactly where a future
  `require_login()` check should be wired in.

## v17 – Top navigation + real data + grid/list + filtering
Builds on v16's top navigation structure, but "Mine filmer" now fetches
real data from the database (the same `config.php`/`api.php` pattern
as v15, in its own `v17` files, with `imdb_id` added to the API
response). New in this version:
- **Grid/list toggle**: a button lets you switch between card view
  (cover-art placeholder, as in v15) and a simple table view showing
  only **title, original title, release year and IMDb id** (linked to
  imdb.com), with no cover art – useful once the collection is large.
- **Text filtering** (same pattern as v15): a search box
  (title/original title), type chips (built dynamically from the
  actual `content_type` values in the data), and an "only unwatched"
  checkbox. Filtering applies to both views (grid and list).
- Ønskeliste/Andre lister/Administrering remain unchanged demo panels
  from v16, including the configurable lock/access state.

## v18 – First version with data via an API (not direct MySQL)
Identical to v17 in appearance/functionality (top navigation,
grid/list toggle, text filtering), but database access has been moved
out of PHP:
- New FastAPI endpoint `GET /media/content`
  (`backend/app/routes/media_catalog_route.py` +
  `backend/app/services/media_catalog.py`) now runs the actual SQL
  queries against `db_mediearkiv` (content + physical_collection +
  content_external_source), and returns the same data structure that
  v15/v17's `api.php` used to.
- v18's own `api.php` runs no SQL at all – it is now just a thin
  server-side proxy (`curl` against
  `http://172.19.0.1:9500/media/content`) that forwards the JSON
  response to the browser. This avoids CORS (everything is still
  served from the same origin) and requires no changes to the
  Apache/edge setup.
- `imdb_id` is now read directly from the `content.imdb_id` column
  instead of via `content_external_source`, since that column turned
  out to already exist and be partially populated.
- This is deliberately the start of a larger transition: the goal is
  to move more and more of the media-catalog logic (collections,
  sources, cast, etc.) into this API over time, so the PHP pages
  remain thin display layers without their own SQL/duplicated
  database logic.

---

*Last updated as part of the v18 work. Update this document whenever
new versions are added under
`frontend/public/website_template_example/`.*
