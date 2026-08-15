# add_to_wishlist – Version Overview

A bullet-point overview of what changed or differs between the versions in `frontend/public/add_to_wishlist/`.

## v1

- First version of the form for adding a movie to the wishlist.
- Fields: `title`, `original_title`, `first_release_year`, `cover_image` (cover image upload).
- Light theme (light background, white cards).
- Submits the form via `curl` (`CURLFile`) to the `POST /wishlist/movies` endpoint.
- Shows the result after submission: title, `content_id`, link to the cover image.
- Simple JavaScript image preview before upload.

## v2

- Based on v1, same light theme and layout.
- **New:** three optional external ID fields – `imdb_id`, `tmdb_id`, `tvdb_id` – grouped in their own `<fieldset>` ("External IDs (optional)").
- These fields are only included in the request to the API if they are actually filled in.
- The result view after submission was updated to show `list_item_id` (instead of the deprecated `content_id`) along with the three new ID fields, matching the updated API response.

## v3

- Same form functionality as v2 (title, original_title, first_release_year, imdb_id/tmdb_id/tvdb_id, cover upload).
- **New:** visual redesign in a dark theme, matching the style of `website_template_example` (v14) – radial gradient background, sticky header with a "frosted glass" effect, same color palette (`--bg`, `--panel`, `--accent`, `--good`, `--muted`).
- The result panel is now built as a key/value grid (`kv`), matching the pattern used in the template's modal panels.
- No changes to backend calls or form logic – purely a visual evolution of v2.

## v4

- Based on v3 (same dark theme and form fields).
- **New:** a "🔍 Fetch from TMDB" button next to the title field, which opens a search modal (Bootstrap) to look up movies/series directly from TMDB.
- New files introduced in this version (taken/adapted from `bulk_add_movies_form`):
  - `config.php` – loads `TMDB_API_KEY` from `.env` via Dotenv.
  - `api.php` – proxy for TMDB (`?action=search`, `?action=details`), combines movie and TV search, supports a year in the search text.
  - `script.js` – handles searching, displays results in a dropdown, fetches details, and auto-fills the form (`title`, `original_title`, `first_release_year`, `imdb_id`, `tmdb_id`) via the "Use this data in the form" button.
- Bootstrap 5 (CSS + JS via CDN) was added, used only for the modal component – the rest of the page still uses its own CSS in the same style as v3.
- The modal and dropdown results are re-styled in dark theme to match the rest of the page.
- Requires `TMDB_API_KEY` to be set in `.env`, and `vendor/autoload.php` (Composer) to exist in the `frontend/` root.

## Background: endpoint and database changes (applies to v1 → v4)

- The backend endpoint `POST /wishlist/movies` (`backend/app/routes/add_data/wishlist_movie_cover_route.py` and `backend/app/services/add_data/wishlist_movie_cover.py`) changed over time from writing to a simple `wishlist` table to using the new `list_items` and `custom_lists`/`custom_list_entries` tables.
- All submitted movies are automatically linked to a fixed list identified by `list_name = 'Wishlist'` in `custom_lists` – the list is created automatically the first time the endpoint is called, if it doesn't already exist.
- `original_title` and `first_release_year` were, for a while, validated in the code but never actually persisted to the database (a bug) – this has been fixed so both fields are now stored correctly in `list_items`.
