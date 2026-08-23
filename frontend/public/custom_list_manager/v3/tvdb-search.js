// TVDB search modal script for custom_list_manager (v3)
// - Reuses the existing backend proxy in frontend/public/tvdb_live_search/v4/tvdb_api.php
//   (no TVDB credentials/config duplicated here – same pattern as tvdb_live_search itself).
// - Writes the result directly into the "add item" form fields:
//   #title, #original_title, #first_release_year, #imdb_id, #tvdb_id
// - All user-facing text comes from window.CLM_I18N (set in index.php from the
//   central language files in lang/no.php / lang/en.php).

const TVDB_API_ENDPOINT = '../../tvdb_live_search/v4/tvdb_api.php';

const I18N_COMMON = window.CLM_I18N?.common ?? {};
const I18N_TVDB = window.CLM_I18N?.tvdb ?? {};

// Simple sprintf-style formatter, supports %s and %d placeholders.
function clmFormat(template, ...args) {
  if (!template) return '';
  let i = 0;
  return template.replace(/%[sd]/g, () => (i < args.length ? args[i++] : ''));
}

let tvdbSearchTimeout;

// Modal elements
const tvdbSearchInput = document.getElementById('tvdbSearchInput');
const tvdbDropdownResults = document.getElementById('tvdbDropdownResults');
const tvdbLoadingSpinner = document.getElementById('tvdbLoadingSpinner');
const tvdbSearchStatus = document.getElementById('tvdbSearchStatus');
const tvdbSearchModal = document.getElementById('tvdbSearchModal');
const tvdbSelectedItemContainer = document.getElementById('tvdbSelectedItem');
const tvdbTypeRadios = document.querySelectorAll('input[name="tvdbTypeRadios"]');

function getTvdbSelectedType() {
  const checked = document.querySelector('input[name="tvdbTypeRadios"]:checked');
  return checked ? checked.value : 'series';
}

// When modal opens: focus search
tvdbSearchModal.addEventListener('shown.bs.modal', function () {
  tvdbSearchInput.focus();
});

// When modal closes: cleanup
tvdbSearchModal.addEventListener('hidden.bs.modal', function () {
  tvdbSearchInput.value = '';
  tvdbDropdownResults.classList.remove('show');
  tvdbDropdownResults.innerHTML = '';
  tvdbSearchStatus.textContent = '';
  tvdbSearchStatus.classList.remove('text-danger');
  tvdbSelectedItemContainer.innerHTML = '';
});

// Re-søk når type endres
tvdbTypeRadios.forEach((radio) => {
  radio.addEventListener('change', () => {
    const q = tvdbSearchInput.value.trim();
    if (q.length >= 2) {
      searchTvdb(q, getTvdbSelectedType());
    }
  });
});

// Input listener
tvdbSearchInput.addEventListener('input', (e) => {
  const query = e.target.value.trim();

  clearTimeout(tvdbSearchTimeout);

  if (query.length === 0) {
    tvdbDropdownResults.classList.remove('show');
    tvdbDropdownResults.innerHTML = '';
    tvdbSearchStatus.textContent = '';
    tvdbSearchStatus.classList.remove('text-danger');
    tvdbSelectedItemContainer.innerHTML = '';
    return;
  }

  if (query.length < 2) {
    tvdbSearchStatus.textContent = I18N_COMMON.min_chars_hint;
    tvdbSearchStatus.classList.remove('text-danger');
    tvdbDropdownResults.classList.remove('show');
    tvdbDropdownResults.innerHTML = '';
    tvdbSelectedItemContainer.innerHTML = '';
    return;
  }

  tvdbSearchTimeout = setTimeout(() => {
    searchTvdb(query, getTvdbSelectedType());
  }, 500);
});

// Hide dropdown when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('#tvdbSearchModal .search-wrapper')) {
    tvdbDropdownResults.classList.remove('show');
  }
});

async function searchTvdb(query, type) {
  try {
    tvdbLoadingSpinner.style.display = 'block';
    tvdbSearchStatus.textContent = I18N_COMMON.searching;
    tvdbSearchStatus.classList.remove('text-danger');
    tvdbDropdownResults.innerHTML = '';
    tvdbSelectedItemContainer.innerHTML = '';

    const url = `${TVDB_API_ENDPOINT}?action=search&query=${encodeURIComponent(query)}&type=${encodeURIComponent(type)}`;
    const response = await fetch(url);

    if (!response.ok) {
      const text = await response.text();
      let msg = I18N_TVDB.search_error_generic;
      try {
        const data = JSON.parse(text);
        msg = data.error || msg;
      } catch {
        msg = text || msg;
      }
      throw new Error(msg);
    }

    const data = await response.json();

    tvdbLoadingSpinner.style.display = 'none';

    if (data.status !== 'success') {
      tvdbSearchStatus.textContent = clmFormat(I18N_TVDB.status_prefix, data.status || I18N_TVDB.unknown_status);
      tvdbDropdownResults.classList.remove('show');
      return;
    }

    const items = Array.isArray(data.data) ? data.data : [];

    if (items.length === 0) {
      tvdbSearchStatus.textContent = I18N_COMMON.no_results;
      tvdbDropdownResults.classList.remove('show');
      return;
    }

    tvdbSearchStatus.textContent = clmFormat(I18N_COMMON.found_results, items.length);
    displayTvdbDropdown(items, type);
  } catch (error) {
    console.error('Error:', error);
    tvdbLoadingSpinner.style.display = 'none';
    tvdbSearchStatus.textContent = clmFormat(I18N_COMMON.error_prefix, error.message);
    tvdbSearchStatus.classList.add('text-danger');
  }
}

function displayTvdbDropdown(items, forcedType) {
  tvdbDropdownResults.innerHTML = '';

  items.forEach((item) => {
    const dropdownItem = createTvdbDropdownItem(item, forcedType);
    tvdbDropdownResults.appendChild(dropdownItem);
  });

  tvdbDropdownResults.classList.add('show');
}

function createTvdbDropdownItem(item, forcedType) {
  const div = document.createElement('div');
  div.className = 'dropdown-item-custom';

  const type = item.type || forcedType || 'series';
  const name = item.name || item.title || item.slug || clmFormat(I18N_COMMON.no_title_fallback, item.tvdb_id || item.id || '?');
  const year = item.year || '';
  const id = item.tvdb_id || item.id || '';

  const posterUrl = item.image_url || item.poster || item.thumbnail || null;
  const mediaType = type === 'series' ? I18N_COMMON.media_tv : I18N_COMMON.media_movie;
  const badgeClass = type === 'series' ? 'badge-tv' : 'badge-movie';

  div.innerHTML = `
    ${
      posterUrl
        ? `<img src="${posterUrl}" class="dropdown-poster" alt="${escapeHtml(name)}">`
        : `<div class="dropdown-no-poster">${escapeHtml(I18N_COMMON.no_poster_small)}</div>`
    }
    <div class="dropdown-info">
      <div class="dropdown-title">
        ${escapeHtml(name)}
        <span class="media-badge ${badgeClass}">${mediaType}</span>
      </div>
      <div class="dropdown-meta">
        📅 ${escapeHtml(year || I18N_COMMON.unknown_year)} | ${escapeHtml(I18N_TVDB.id_label)} ${escapeHtml(id.toString())}
      </div>
    </div>
  `;

  div.addEventListener('click', () => {
    if (!id) return;
    fetchTvdbDetails(type, id, name);
    tvdbDropdownResults.classList.remove('show');
  });

  return div;
}

async function fetchTvdbDetails(type, tvdbId, fallbackName) {
  try {
    tvdbSearchStatus.textContent = I18N_COMMON.fetching_details;
    tvdbSearchStatus.classList.remove('text-danger');

    const url = `${TVDB_API_ENDPOINT}?action=details&type=${encodeURIComponent(type)}&id=${encodeURIComponent(tvdbId)}`;
    const response = await fetch(url);

    if (!response.ok) {
      const text = await response.text();
      let msg = I18N_TVDB.details_error_generic;
      try {
        const data = JSON.parse(text);
        msg = data.error || msg;
      } catch {
        msg = text || msg;
      }
      throw new Error(msg);
    }

    const data = await response.json();

    if (data.status !== 'success') {
      tvdbSearchStatus.textContent = clmFormat(I18N_TVDB.status_prefix, data.status || I18N_TVDB.unknown_status);
      tvdbSearchStatus.classList.add('text-danger');
      return;
    }

    tvdbSearchStatus.textContent = '';
    displayTvdbInfoPage(data.data || {}, type, tvdbId, fallbackName);
  } catch (error) {
    console.error('Error:', error);
    tvdbSearchStatus.textContent = clmFormat(I18N_COMMON.details_error_prefix, error.message);
    tvdbSearchStatus.classList.add('text-danger');
  }
}

// Holder unna siste hentede detaljer, slik at "Bruk disse dataene"-knappen
// slipper å sende data via onclick-attributter (unngår escaping-problemer).
let lastTvdbPayload = null;

function displayTvdbInfoPage(rec, type, tvdbId, fallbackName) {
  const isSeries = type === 'series';
  const title = rec.name || fallbackName || '';
  const year = rec.year || (rec.first_release?.date || '').slice(0, 4) || '';
  const posterUrl = rec.image || null;

  const overview =
    rec.overview ||
    (Array.isArray(rec.overviewTranslations) && rec.overviewTranslations[0]?.overview) ||
    '';

  // remoteIds inneholder bl.a. IMDB (sourceName === 'IMDB')
  let imdbId = '';
  if (Array.isArray(rec.remoteIds)) {
    const imdbEntry = rec.remoteIds.find((r) => r.sourceName === 'IMDB');
    if (imdbEntry) imdbId = imdbEntry.id;
  }

  lastTvdbPayload = {
    title: title || '',
    releaseYear: year ? Number(year) : null,
    imdbId,
    tvdbId: tvdbId ? tvdbId.toString() : '',
  };

  const mediaType = isSeries ? I18N_COMMON.media_tv : I18N_COMMON.media_movie;

  tvdbSelectedItemContainer.innerHTML = `
    <div class="selected-item mt-3">
      <div class="text-center mb-3">
        ${
          posterUrl
            ? `<img src="${posterUrl}" class="selected-poster" style="max-width: 200px; border-radius: 10px;" alt="${escapeHtml(title)}">`
            : `<div class="alert alert-secondary d-inline-block">${escapeHtml(I18N_COMMON.no_poster_large)}</div>`
        }
      </div>

      <h4 class="mb-1">${escapeHtml(title)}</h4>

      <p class="mb-2">
        <span class="badge ${isSeries ? 'bg-purple' : 'bg-primary'}">${escapeHtml(mediaType)}</span>
        <span class="ms-2">📅 ${escapeHtml(year || I18N_COMMON.unknown_year)}</span>
      </p>

      <p class="mb-1"><strong>${escapeHtml(I18N_TVDB.id_label)}</strong> <code>${escapeHtml(tvdbId.toString())}</code></p>

      <p class="mb-2">
        <strong>${escapeHtml(I18N_COMMON.imdb_label)}</strong> ${
          imdbId
            ? `<code>${escapeHtml(imdbId)}</code> <a class="imdb-link ms-1" href="https://www.imdb.com/title/${encodeURIComponent(imdbId)}/" target="_blank" rel="noopener">${escapeHtml(I18N_COMMON.imdb_open_link)}</a>`
            : escapeHtml(I18N_COMMON.imdb_not_available)
        }
      </p>

      <hr>
      <p class="small">${escapeHtml(overview || I18N_COMMON.no_overview)}</p>

      <div class="mt-3 text-end">
        <button type="button" class="btn btn-success" id="applyTvdbDetailsBtn">
          ${escapeHtml(I18N_COMMON.apply_button)}
        </button>
      </div>
    </div>
  `;

  document.getElementById('applyTvdbDetailsBtn')?.addEventListener('click', () => {
    if (lastTvdbPayload) applyTvdbDetailsToForm(lastTvdbPayload);
  });
}

// Fill add-item form fields + close modal
function applyTvdbDetailsToForm(payload) {
  const fields = {
    title: document.getElementById('title'),
    first_release_year: document.getElementById('first_release_year'),
    imdb_id: document.getElementById('imdb_id'),
    tvdb_id: document.getElementById('tvdb_id'),
  };

  if (fields.title && payload.title) {
    fields.title.value = payload.title;
  }
  if (fields.first_release_year && payload.releaseYear) {
    fields.first_release_year.value = payload.releaseYear;
  }
  if (fields.imdb_id && payload.imdbId) {
    fields.imdb_id.value = payload.imdbId;
  }
  if (fields.tvdb_id && payload.tvdbId) {
    fields.tvdb_id.value = payload.tvdbId;
  }

  tvdbSearchStatus.textContent = I18N_COMMON.applied_message;
  tvdbSearchStatus.classList.remove('text-danger');

  const modalInstance = bootstrap.Modal.getInstance(tvdbSearchModal);
  if (modalInstance) modalInstance.hide();
}

// Helpers: escape for HTML contexts
function escapeHtml(s) {
  return String(s)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}
