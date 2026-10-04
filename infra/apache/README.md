# infra/apache

Tracked reference copies of Apache vhost configs that live on the
production host (`/etc/apache2/sites-available/`), kept here for
version control and visibility - they are NOT deployed automatically
from this repo. Apache itself is not managed by any deploy script in
this project (unlike `backend/deploy_backend.sh`).

To apply a change made here to production:

```bash
sudo cp infra/apache/mmc.plexcity.net.conf /etc/apache2/sites-available/mmc.plexcity.net.conf
sudo apache2ctl configtest   # verify syntax before reloading
sudo systemctl reload apache2
```

Note: `systemctl reload apache2` (not `restart`) applies config
changes with a graceful reload instead of a full restart, so it
doesn't interrupt other vhosts on the same shared Apache instance
(`blogg.plexcity.net`, `plexcity.net`, `oslomet.plexcityhub.net`,
etc. - see `apache2ctl -S`).

If a `Header` directive (e.g. the security headers below) is added
for the first time, the `headers` module must be enabled once:

```bash
sudo a2enmod headers
sudo systemctl restart apache2   # a2enmod requires a full restart, not just reload
```

## mmc.plexcity.net.conf

Adds basic security response headers (see TODO.md "Basic security
response headers") on every request to this vhost - i.e. every
tool/version under `frontend/public/`, old and new, with a single
change here instead of touching each PHP app individually:

- `X-Content-Type-Options: nosniff` - stops browsers from
  MIME-sniffing a response into a different content type than the
  server declared.
- `X-Frame-Options: SAMEORIGIN` (+ `frame-ancestors 'self'` in the CSP
  below) - stops the site being embedded in an `<iframe>` on another
  origin (clickjacking protection). Nothing in this project uses
  `<iframe>`, so this is safe.
- `Content-Security-Policy` - restricts which origins scripts,
  styles, and images may load from. Built from an audit of every
  external URL referenced across `frontend/public/` (see git history
  for the commit that added this):
  - `https://cdn.jsdelivr.net` - Bootstrap CSS/JS used by
    `website_template_example/v19`.
  - `https://image.tmdb.org` - used directly (not proxied) by the
    TMDB/TVDB live-search preview UIs before an item is actually
    saved (`bulk_add_movies_form`, `tmdb_live_search`,
    `custom_list_manager`, `add_to_wishlist`).
  - `https://tmdb.media.plexcity.net` - the backend's own proxy for
    already-saved TMDB cover images (see
    `_to_proxied_cover_image()` in
    `backend/app/services/media_catalog.py`).
  - `https://*.thetvdb.com` - TVDB artwork URLs, returned as-is from
    the TVDB API (not proxied).
  - `'unsafe-inline'` is needed for both `script-src` and `style-src`
    because every page in this project uses inline `<script>`/
    `<style>` blocks rather than external files with a nonce - a
    stricter nonce-based CSP would be a good future follow-up (see
    TODO.md) but requires touching every page.

  If a new external resource is added to any app under this vhost in
  the future, the CSP here must be updated to match, or that
  resource will silently fail to load (check the browser console for
  "Refused to load/connect..." errors).
