# Project TODO / Roadmap

Ideas and suggested improvements for `my_movie_collection`, gathered
during development discussions. Not prioritized or scheduled - just a
backlog to pick from.

## Database / migrations

- [x] Set up Alembic for `mmc_userdb` (users, section_access,
      app_settings) - done on `feature/alembic-migrations`.
- [x] Model the `db_mediearkiv` tables (content, movie_group, disc,
      physical_collection, etc. - 20 tables total) as SQLAlchemy ORM
      classes, so Alembic can manage that schema too (currently only
      handled via manual SQL files in `backend/db_backups/`).
      - [x] Batch 1: `movie_group`, `owner`, `store`, `storage`,
            `physical_collection`.
      - [x] Batch 2: `content`, `physical_copy`, `disc`, `wishlist`,
            `custom_lists`.
      - [x] Batch 3: `content_external_source`,
            `content_group_membership`,
            `content_in_physical_collection`, `disc_related_content`,
            `list_items`.
      - [x] Batch 4: `disc_bonus_item`, `disc_in_storage`, `disc_in`,
            `custom_list_entries` - all "real" tables now modeled.
- [x] Investigate/remove the `list` table in `db_mediearkiv` - confirmed
      by the user to be an early, superseded prototype of what became
      custom_lists/list_items/custom_list_entries. Was empty (0 rows)
      and had no foreign-key references. Dropped via Alembic migration
      `1b2e23711528` on `feature/alembic-migrations`.
- [ ] Automated DB backups (e.g. a cron job running `mysqldump` to a
      file or off-site storage), instead of relying on manual backups.
- [ ] Periodically test that a backup can actually be restored (a
      backup that's never been restore-tested is not a verified
      backup).
- [x] Create tables to support adding TV series (currently the schema
      is movie-oriented only - e.g. no season/episode modeling), so
      TV shows/box sets can be catalogued alongside movies. Added
      `season`, `episode`, `disc_contains_episode` on
      `feature/tv-series-schema` (migration `ca92db17e42f`).

## DevOps / CI / deployment

- [ ] A CI pipeline (e.g. GitHub Actions) that runs on pull requests:
      Python syntax/import checks, `pip-audit`/`composer audit` for
      known vulnerable dependencies, and any tests added per the
      "automated tests" item below.
- [ ] Dockerize the stack (backend + MySQL/MariaDB, optionally the PHP
      frontend) for a reproducible local dev setup and easier
      onboarding if someone else ever wants to run the project.
- [ ] Structured (JSON) logging for the backend instead of plain-text
      log lines, making the existing `fastapi-out.log`/
      `fastapi-error.log` easier to search/filter (e.g. by request
      path or status code) when debugging issues like the ones found
      in this project before.
- [ ] Alerting on backend errors (e.g. a webhook/email notification on
      unhandled 500s) instead of only noticing them by manually
      tailing logs after the fact.
- [ ] A secrets-scanning check in CI (e.g. gitleaks) to catch an
      accidentally committed `.env`/API key before it reaches GitHub.

## Data quality / catalog maintenance

- [x] "Health check" view that flags content rows with missing cover
      image, overview, runtime, or invalid/missing TMDB/TVDB IDs. Built
      as `health_check.php` on `feature/data-health-check`
      (`GET /media/health-check`).
- [x] Batch/bulk "refresh from TMDB/TVDB" for many items at once,
      instead of one at a time (now that the single-item timeout issue
      is understood). Built as
      `bulk_refresh_tmdb_for_flagged_content()` on
      `feature/bulk-refresh-tmdb`
      (`POST /media/health-check/bulk-refresh-tmdb`, "Oppdater
      flaggede fra TMDB"-knapp on `health_check.php`). TMDB only for
      now, rate-limited to `TMDB_MAX_REQUESTS_PER_SECOND`; TVDB was
      explicitly deferred (needs `fetch_tvdb_details()` refactored to
      cache one login token per batch run instead of logging in per
      call, and its rate limit isn't clearly documented) - see
      separate TODO item below.
- [ ] Batch/bulk "refresh from TVDB" - same idea as the TMDB bulk
      refresh above, but blocked on refactoring `fetch_tvdb_details()`
      to reuse a single login token per batch (it currently logs in
      to TVDB on every call) and picking a conservative rate limit
      since TVDB's isn't clearly documented.
- [ ] Duplicate detection (same TMDB/TVDB ID registered more than
      once, e.g. from a bad import).
- [ ] Background job queue (Celery, RQ, or FastAPI `BackgroundTasks`)
      for TMDB/TVDB imports, so large imports don't depend on
      ever-increasing proxy/timeout settings.

## Search / browsing

- [x] Full-text / faceted search across the whole collection (title,
      cast, genre, year), not just per-list browsing.
- [ ] Fix TMDB search failing for purely numeric titles (e.g. "1917")
      in `tmdb_live_search` - the year-extraction regex currently
      strips the entire query when the title itself is just a year.
      Needs a fallback: if stripping the year leaves an empty search
      string, keep the original query instead.
- [ ] "What should I watch tonight?" - random suggestion button,
      optionally filterable (e.g. unwatched, recently added).

## Statistics / reporting

- [x] Stats page: number of movies per decade/genre/format
      (DVD/Blu-ray/4K), total count, most-added groups, etc.
- [ ] CSV/Excel export of the collection (useful for insurance
      purposes, since the physical collection has real value).

## Physical collection features

- [ ] "Loaned out to" tracking for physical discs (easy to lose track
      of who borrowed what).
- [ ] Ability to change the owner of one or more movies/copies via
      the frontend/admin UI (currently only settable directly in the
      database - see the owner facet added in feature/faceted-search,
      which reads owners but has no way to assign/reassign them).
- [ ] Ability to add new owners/users via the frontend/admin UI
      (currently only the `owner` table row itself can be added
      directly in the database; the owner facet in "Mine filmer" will
      automatically pick up any new owner once they exist and are
      linked to physical copies).
- [ ] Ability to specify owner when adding movies via
      `bulk_add_movies_form` (doesn't exist today - this is one of
      the reasons owner_id was NULL on every physical_copy row until
      it was manually backfilled).
- [ ] Ability to "import" a single movie into `bulk_add_movies_form`
      (e.g. from search results elsewhere in the app) instead of only
      manual bulk entry.
- [ ] Ability to add a single physical copy, as an alternative to
      `bulk_add_movies_form` for the common one-at-a-time case.

## Frontend / UX

- [ ] Mobile-responsive layout for `website_template_example`
      (`index.php` currently has no `@media` breakpoints, unlike
      `detail.php` which already has two). Concrete starting points:
      - Add a `max-width: 480px` breakpoint for the movie card grid
        (currently `grid-template-columns: repeat(auto-fill,
        minmax(200px, 1fr))`).
      - Reduce `main` padding on small screens.
      - Stack the filter bar vertically instead of `flex-wrap` on
        narrow viewports.
      - Verify the edit modal doesn't cause horizontal overflow on
        mobile widths (375px/390px).
- [ ] Barcode scanning from a phone camera (e.g. the `BarcodeDetector`
      web API) as a faster alternative to manual entry in
      `temp_add_movie_barcode` (keep that page as-is otherwise - it's
      intentionally a quick "add while out shopping" tool, not meant
      to be replaced).
- [ ] Multi-user support: separate wishlists/preferences per logged-in
      user, not just a single shared admin login (role infrastructure
      for this already exists via `require_role()` in
      `app/security.py`, just not used for more than one role yet).
- [x] Lazy-load cover images (`loading="lazy"` on `<img>`, or
      `content-visibility: auto` on off-screen cards) - covers are
      currently rendered as CSS `background-image` on divs, which
      loads them all eagerly regardless of scroll position. Done
      together with the item below on `feature/lazy-load-covers`
      (main "Mine filmer" grid in `index.php` and the group-movies
      strip in `detail.php`; the small "Bytt cover" poster-picker
      modal in `detail.php` was left as `background-image` - it's a
      short on-demand list, not worth the churn).
- [x] Switch cover rendering from CSS `background-image` to real
      `<img alt="{title}">` tags for accessibility (screen readers get
      nothing from a background-image) and so lazy-loading above is
      possible in the first place.
- [ ] Client-side render pagination / "load more" (or virtual
      scrolling) for the main "Mine filmer" grid in `index.php` -
      `list_content()` sends the entire catalog in one JSON response
      and faceted search/filtering is done fully client-side over
      that array (by design - see docstring), so real server-side
      pagination would require moving filtering/facet-counting to the
      backend too (a bigger rewrite). A lighter first step: keep
      fetching the full list as today (facets keep working unchanged),
      but only render e.g. 40-60 cards at a time, rendering more as the
      user scrolls/clicks "last flere" - avoids building hundreds of
      DOM nodes up front, which is likely the main remaining slowness
      now that cover images are lazy-loaded.
- [ ] Self-host Bootstrap (via Composer/npm) instead of loading it
      from `cdn.jsdelivr.net`, so the site still works if that CDN is
      blocked or unreachable on a given network.
- [ ] Move the edit-mode/lock-mode toggle (currently `localStorage`,
      shared browser-wide) to a per-user, server-side preference -
      right now it "leaks" between different people sharing the same
      browser/machine.
- [ ] Loading skeleton/spinner while a page or panel is fetching data,
      instead of only a plain status text line.
- [ ] Dark/light theme toggle based on `prefers-color-scheme` - low
      effort since the CSS already uses variables (`var(--accent)`,
      `var(--muted)`, etc.).
- [ ] Reflect the current search/filter state in the URL (query
      params), so a filtered view can be bookmarked/shared/refreshed
      without losing it - today only the `panel` parameter is synced
      to the URL.
- [ ] Toast/notification component for success/error messages instead
      of plain inline status text, for more consistent feedback across
      pages.
- [ ] Pagination or infinite scroll for the movie list once the
      collection grows large, instead of rendering everything at
      once.
- [ ] "Similar movies" suggestions based on shared genre/group data
      already stored, shown on the detail page.
- [ ] Printable/print-friendly view of the full collection (useful for
      insurance documentation, alongside the CSV/Excel export idea
      above).
- [ ] Keyboard shortcut to focus the search field (e.g. `/`), and
      verify all interactive elements have visible focus indicators
      for keyboard-only navigation.
- [ ] CSRF protection for state-changing POST requests (login, edit,
      delete, group management, etc.) - none of the PHP endpoints
      currently issue/check a CSRF token; session-cookie auth alone is
      vulnerable to cross-site request forgery from another tab.
- [ ] Basic security response headers (`Content-Security-Policy`,
      `X-Frame-Options`/`frame-ancestors`, `X-Content-Type-Options:
      nosniff`) - not currently set anywhere in the PHP apps or Apache
      vhost config.
- [ ] Consistent network-error handling for `fetch()` calls (e.g. show
      a clear "connection lost" message and a retry button) - several
      places assume the request either succeeds or returns JSON with
      an `error` field, without handling outright network failures.
- [ ] Undo/confirmation before destructive actions everywhere, not
      just group removal - e.g. deleting a movie or a custom list
      currently may not prompt for confirmation the same way
      `groups_remove_confirm` does in detail.php.
- [ ] Export/import a custom list (`custom_list_manager`) as
      JSON/CSV, so lists can be backed up or shared outside the app.
- [ ] Consolidate `bulk_add_movies_form` (currently 14 parallel
      versions, `v1`-`v14`) down to the one actually in use, same idea
      as the general "retire old versioned folders" cleanup item
      above, called out separately since it's the most extreme case.
- [ ] Client-side form validation feedback (e.g. highlighting the
      specific invalid field) instead of only a generic status-line
      message like "name is required".
- [x] Session-expiry handling for `tv_series_add_form/v1`: instead of
      a visible "you'll be logged out soon" warning, an automatic,
      silent re-login was built - see the "Refresh tokens" item below
      for the implementation. A user-visible warning banner is no
      longer needed for this form since the 401 is now recovered from
      transparently; still worth considering for other tools that
      don't yet use the shared `auth_call_with_retry()` helper.
- [ ] Show a diff/preview of what would actually change before
      applying a TMDB/TVDB "refresh + merge" (currently it fetches and
      merges immediately; a preview would let the user catch an
      unwanted overwrite before it happens, complementing the existing
      per-field `locked_fields` protection).
- [ ] Debounce the free-text search/filter input on `index.php`
      (`mineFilmerSearch` currently re-renders on every keystroke) -
      not an issue yet, but worth doing before/alongside the
      pagination item above as the collection grows.
- [ ] Bulk actions on the movie list (e.g. select several movies and
      add them all to a group/list at once), instead of one at a time.
- [ ] A `CHANGELOG.md` documenting notable changes per release/version,
      given how many versioned folders (`v1`...`v19`) already exist
      across the different apps.
- [ ] Explicit `Secure`, `HttpOnly`, and `SameSite` cookie flags for
      the PHP session cookie (verify current
      `session_set_cookie_params()` call in `_shared/auth.php` sets
      all three, since the site is already served over HTTPS).
- [ ] "Skip to content" link and other basic accessibility landmarks
      (`<main>`, `<nav>` roles) for keyboard/screen-reader users.
- [ ] A simple `/health` endpoint on the backend (DB connection check,
      uptime) that could be polled by an external monitor or a cron
      job, so a crashed/stuck PM2 process is noticed automatically
      instead of only when someone tries to use the site.

## Backend / security hardening

- [ ] Rate-limiting on `/auth/login` (and `/auth/login/2fa`) to guard
      against brute-force password/2FA guessing.
- [x] Refresh tokens, so users don't need to log in again every 30
      minutes (current `ACCESS_TOKEN_EXPIRE_MINUTES`). Implemented as
      a new `"refresh"` JWT type (`REFRESH_TOKEN_EXPIRE_MINUTES`, 7
      days) issued alongside the access token on `/auth/login` and
      `/auth/login/2fa`, plus a new `POST /auth/refresh` endpoint that
      exchanges a valid refresh token for a fresh access token without
      a password. `frontend/public/_shared/auth.php` stores the
      refresh token in the PHP session and exposes
      `auth_refresh_access_token()` + a generic
      `auth_call_with_retry()` wrapper that retries a failed backend
      call exactly once after a silent refresh if it first gets a 401.
      Wired into `tv_series_add_form/v1/api.php`'s submit action so a
      user mid-form never sees "Kunne ikke validere token" unless the
      refresh token itself has also expired/is missing (genuinely
      logged out). Verified end-to-end against the live backend/DB
      with forged expired/valid tokens (confirmed: expired access +
      valid refresh transparently recovers and the session's stored
      access token is replaced; expired access + no refresh token
      still correctly surfaces a 401). Not yet adopted by the other
      tools sharing `auth.php` - see the separate rollout item below.
- [ ] Roll out the automatic re-login (`auth_call_with_retry()`, see
      the "Refresh tokens" item above) to the other tools that share
      `auth.php` but still only use `auth_bearer_header()` /
      `auth_api_authenticated()` directly and so still show a raw 401
      ("Kunne ikke validere token") once their access token expires.
      Each one just needs its curl call wrapped the same way
      `tv_series_add_form/v1/api.php`'s submit action was:
      - `frontend/public/bulk_add_movies_form/v14/api.php`
      - `frontend/public/temp_add_movie_barcode/v1/submit.php`
      - `frontend/public/website_template_example/v18/api.php`
      - `frontend/public/website_template_example/v19/api.php`
      - `frontend/public/custom_list_manager/v3/index.php` and
        `v4/index.php`
      - `frontend/public/add_to_wishlist/v4/bildopp.php`
      Worth doing incrementally (one tool at a time, verified live like
      the first rollout) rather than all at once, since each file's
      curl setup differs slightly (some use multipart uploads, not
      plain JSON POSTs).
- [ ] Review remaining raw-SQL call sites for proper parameterization
      (avoid SQL injection risk in code paths outside the ORM).
- [ ] General API rate-limiting (not just `/auth/login`) to guard
      against accidental or malicious abuse of the write endpoints.
- [ ] A consistent error-response shape across the PHP proxy layer and
      the FastAPI backend (e.g. always `{"error": "..."}` with the
      same keys), so frontend error handling doesn't need to guess
      between `error`/`detail`/plain-text bodies.

## New features

- [ ] Form for registering physical copies of TV series box sets
      (depends on the TV series table support above). v1 form +
      backend `tv_series_boxset` import endpoint built and working
      (`frontend/public/tv_series_add_form/v1`,
      `import_tv_series_boxset_payload` in
      `backend/app/services/add_data/physical_collection_import.py`),
      not yet merged to `develop`. Status of previously open issues:
      - [x] `inner_case_ean` display bug fixed: the per-season inner
        case collections were correctly imported, but the detail page
        read path (`_load_physical_copies` in
        `backend/app/services/media_catalog.py`) treated the outer
        box collection as its own (empty, confusing) "physical copy"
        entry instead of recognizing it as a pure container, because
        its "is this a container collection" check only looked for
        collections shared by multiple *different* titles - a TV
        box's outer collection only ever has one title (the series
        itself) shared across its own per-season rows, so it slipped
        through. Added a second container rule (a barcode-less
        collection whose content also has a sibling collection with a
        barcode is a container) plus season labelling: each
        `physical_copies` entry now gets `season_number`/
        `season_title` derived from `disc_contains_episode` ->
        `episode` -> `season` when all its discs belong to one
        season. Verified via live DB test (2-season box, no spurious
        empty entry, correct season labels) and cleaned up afterwards.
      - [x] Mixed boxset support (multiple series + standalone TV
        movies sharing discs in one physical box, e.g. a box with 2
        series + 2-3 TV movies) implemented as a new `mixed_boxset`
        payload `kind` - see `import_mixed_boxset_payload` in
        `backend/app/services/add_data/physical_collection_import.py`
        and the matching schemas in
        `backend/app/schemas/physical_collection_import.py`.
        References series/movies from discs by their 0-based position
        in the payload's `series`/`movies` lists. No new frontend
        needed yet - the existing "paste finished JSON payload" box in
        `frontend/public/tv_series_add_form/v1` already posts to the
        same generic `/import/physical-collection` endpoint. Verified
        via live DB test + full cleanup.
      - [ ] `_load_box_set_items` (used only when `box_set_barcode` is
        set on the outer box - not the typical case for TV payloads so
        far) still has the same "one collection = one distinct title"
        assumption and would misbehave for a TV series with per-season
        inner cases if `box_set_barcode` were ever set. Not an active
        bug today since TV payloads currently leave `box_set_barcode`
        unset, but worth revisiting if that changes.
      - [ ] No detail-page frontend template renders season/episode
        structure yet (season/episode tables have zero references in
        `media_catalog.py`'s templates/consumers beyond the new
        `season_number`/`season_title` fields added above) - the data
        is now available via the API, but UI work to actually display
        it (e.g. grouping discs under season headings) is still open.
      - [x] Guided (non-paste-JSON) frontend fields for `mixed_boxset`
        added to `frontend/public/tv_series_add_form/v1`: a "Type
        registrering" mode toggle switches the form between single-
        series (`tv_series_boxset`) and mixed-box (`mixed_boxset`)
        entry, with repeatable "series in box" and "standalone movies
        in box" cards (each series block has its own seasons/episodes
        + its own TVDB search) and a discs card with multi-select
        content/episode references. Verified via a jsdom-driven
        simulation of the real `script.js` producing a schema-valid
        payload, live-imported, and checked via SQL, then cleaned up.
        The "Boksen" card was also moved earlier in the page and the
        step numbering made consistent across both modes after initial
        user feedback that it wasn't clear where to add movies.
- [ ] Dedicated API endpoint(s) for a local FileMaker database to
      connect directly against (rather than a one-off CSV export) -
      likely needs its own export-oriented endpoint(s), separate from
      `/media/content`, since FileMaker consumes flat/typed data
      (e.g. hex-encoded IDs instead of raw binary, no nested JSON
      blobs) and may need per-table access rather than one big
      flattened payload.
- [ ] Read-only share link for a custom list (e.g. a public,
      unguessable URL) so a list can be shared with friends/family
      without giving them a login.
- [ ] Price/availability tracking for wishlist items (e.g. periodic
      check against a shop/price API), to get notified when a wanted
      title becomes available or drops in price.
      USSER NOTE: Can be hard, if there are no API available

## Technical debt / cleanup

- [ ] Consolidate/retire old versioned folders (`v3` ... `v19`, etc.)
      once a given app is confirmed stable on its latest version, to
      reduce confusion about which one is actually "live".
- [ ] Add automated tests (no `tests/` directory currently exists) -
      even minimal coverage for critical flows (auth, physical
      collection import) would catch regressions early.
