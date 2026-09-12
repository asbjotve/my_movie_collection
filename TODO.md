# Project TODO / Roadmap

Ideas and suggested improvements for `my_movie_collection`, gathered
during development discussions. Not prioritized or scheduled - just a
backlog to pick from.

## Database / migrations

- [x] Set up Alembic for `mmc_userdb` (users, section_access,
      app_settings) - done on `feature/alembic-migrations`.
- [ ] Model the `db_mediearkiv` tables (content, movie_group, disc,
      physical_collection, etc. - 20 tables total) as SQLAlchemy ORM
      classes, so Alembic can manage that schema too (currently only
      handled via manual SQL files in `backend/db_backups/`).
- [ ] Automated DB backups (e.g. a cron job running `mysqldump` to a
      file or off-site storage), instead of relying on manual backups.

## Data quality / catalog maintenance

- [ ] "Health check" view that flags content rows with missing cover
      image, overview, runtime, or invalid/missing TMDB/TVDB IDs.
- [ ] Batch/bulk "refresh from TMDB/TVDB" for many items at once,
      instead of one at a time (now that the single-item timeout issue
      is understood).
- [ ] Duplicate detection (same TMDB/TVDB ID registered more than
      once, e.g. from a bad import).
- [ ] Background job queue (Celery, RQ, or FastAPI `BackgroundTasks`)
      for TMDB/TVDB imports, so large imports don't depend on
      ever-increasing proxy/timeout settings.

## Search / browsing

- [ ] Full-text / faceted search across the whole collection (title,
      cast, genre, year), not just per-list browsing.
- [ ] Fix TMDB search failing for purely numeric titles (e.g. "1917")
      in `tmdb_live_search` - the year-extraction regex currently
      strips the entire query when the title itself is just a year.
      Needs a fallback: if stripping the year leaves an empty search
      string, keep the original query instead.
- [ ] "What should I watch tonight?" - random suggestion button,
      optionally filterable (e.g. unwatched, recently added).

## Statistics / reporting

- [ ] Stats page: number of movies per decade/genre/format
      (DVD/Blu-ray/4K), total count, most-added groups, etc.
- [ ] CSV/Excel export of the collection (useful for insurance
      purposes, since the physical collection has real value).

## Physical collection features

- [ ] "Loaned out to" tracking for physical discs (easy to lose track
      of who borrowed what).

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
- [ ] Lazy-load cover images (`loading="lazy"` on `<img>`, or
      `content-visibility: auto` on off-screen cards) - covers are
      currently rendered as CSS `background-image` on divs, which
      loads them all eagerly regardless of scroll position.
- [ ] Switch cover rendering from CSS `background-image` to real
      `<img alt="{title}">` tags for accessibility (screen readers get
      nothing from a background-image) and so lazy-loading above is
      possible in the first place.
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

## Backend / security hardening

- [ ] Rate-limiting on `/auth/login` (and `/auth/login/2fa`) to guard
      against brute-force password/2FA guessing.
- [ ] Refresh tokens, so users don't need to log in again every 30
      minutes (current `ACCESS_TOKEN_EXPIRE_MINUTES`).
- [ ] Review remaining raw-SQL call sites for proper parameterization
      (avoid SQL injection risk in code paths outside the ORM).

## Technical debt / cleanup

- [ ] Consolidate/retire old versioned folders (`v3` ... `v19`, etc.)
      once a given app is confirmed stable on its latest version, to
      reduce confusion about which one is actually "live".
- [ ] Add automated tests (no `tests/` directory currently exists) -
      even minimal coverage for critical flows (auth, physical
      collection import) would catch regressions early.
