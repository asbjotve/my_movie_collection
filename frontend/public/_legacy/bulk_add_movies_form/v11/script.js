// Bulk-add TMDB search modal script
// - Uses PHP backend: api.php
// - Writes results into the currently "active" row fields:
//   window.__bulkActiveImdbInput (visible input)
//   window.__bulkActiveTmdbInput (hidden input)

const API_ENDPOINT = 'api.php';
const IMAGE_BASE_URL = 'https://image.tmdb.org/t/p/w500';

let searchTimeout;

// Modal elements
const searchInput = document.getElementById('searchInput');
const dropdownResults = document.getElementById('dropdownResults');
const loadingSpinner = document.getElementById('loadingSpinner');
const searchStatus = document.getElementById('searchStatus');
const searchModal = document.getElementById('searchModal');
const selectedItemContainer = document.getElementById('selectedItem');

// Active targets (set by index.php when user clicks 🔍 on a specific row)
window.__bulkActiveImdbInput = window.__bulkActiveImdbInput || null;
window.__bulkActiveTmdbInput = window.__bulkActiveTmdbInput || null;

// When modal opens: focus search
searchModal.addEventListener('shown.bs.modal', function () {
  searchInput.focus();
});

// When modal closes: cleanup
searchModal.addEventListener('hidden.bs.modal', function () {
  searchInput.value = '';
  dropdownResults.classList.remove('show');
  dropdownResults.innerHTML = '';
  searchStatus.textContent = '';
  searchStatus.classList.remove('text-danger');
  selectedItemContainer.innerHTML = '';

  // keep active targets (so reopening from same row still works if user doesn’t click another 🔍)
});

// Input listener
searchInput.addEventListener('input', (e) => {
  const query = e.target.value.trim();

  clearTimeout(searchTimeout);

  if (query.length === 0) {
    dropdownResults.classList.remove('show');
    dropdownResults.innerHTML = '';
    searchStatus.textContent = '';
    searchStatus.classList.remove('text-danger');
    selectedItemContainer.innerHTML = '';
    return;
  }

  if (query.length < 2) {
    searchStatus.textContent = 'Skriv minst 2 tegn for å søke...';
    searchStatus.classList.remove('text-danger');
    dropdownResults.classList.remove('show');
    dropdownResults.innerHTML = '';
    selectedItemContainer.innerHTML = '';
    return;
  }

  searchTimeout = setTimeout(() => {
    searchMovies(query);
  }, 500);
});

// Hide dropdown when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.search-wrapper')) {
    dropdownResults.classList.remove('show');
  }
});

// Search TMDB (multi: movies + tv)
async function searchMovies(query) {
  try {
    loadingSpinner.style.display = 'block';
    searchStatus.textContent = 'Søker...';
    searchStatus.classList.remove('text-danger');
    dropdownResults.innerHTML = '';
    selectedItemContainer.innerHTML = '';

    const response = await fetch(
      `${API_ENDPOINT}?action=search&query=${encodeURIComponent(query)}`
    );

    if (!response.ok) {
      const text = await response.text();
      let msg = 'Noe gikk galt med API-kallet';
      try {
        const data = JSON.parse(text);
        msg = data.error || msg;
      } catch {
        msg = text || msg;
      }
      throw new Error(msg);
    }

    const data = await response.json();

    loadingSpinner.style.display = 'none';

    if (!data.results || data.results.length === 0) {
      searchStatus.textContent = 'Ingen resultater funnet';
      dropdownResults.classList.remove('show');
      return;
    }

    let statusText = `Fant ${data.results.length} resultat(er)`;
    if (data.search_year) statusText += ` for år ${data.search_year}`;
    searchStatus.textContent = statusText;

    displayDropdown(data.results);
  } catch (error) {
    console.error('Feil:', error);
    loadingSpinner.style.display = 'none';
    searchStatus.textContent = `En feil oppstod: ${error.message}`;
    searchStatus.classList.add('text-danger');
  }
}

// Build dropdown
function displayDropdown(results) {
  dropdownResults.innerHTML = '';

  results.forEach((item) => {
    const dropdownItem = createDropdownItem(item);
    dropdownResults.appendChild(dropdownItem);
  });

  dropdownResults.classList.add('show');
}

// Create one dropdown entry
function createDropdownItem(item) {
  const div = document.createElement('div');
  div.className = 'dropdown-item-custom';

  const posterPath = item.poster_path ? `${IMAGE_BASE_URL}${item.poster_path}` : null;

  const title = item.media_type === 'tv' ? (item.name || item.title) : item.title;
  const releaseDate = item.media_type === 'tv' ? item.first_air_date : item.release_date;
  const releaseYear = releaseDate ? new Date(releaseDate).getFullYear() : 'Ukjent';
  const rating = item.vote_average ? item.vote_average.toFixed(1) : 'N/A';
  const mediaType = item.media_type === 'tv' ? 'TV-serie' : 'Film';
  const badgeClass = item.media_type === 'tv' ? 'badge-tv' : 'badge-movie';

  div.innerHTML = `
    ${
      posterPath
        ? `<img src="${posterPath}" class="dropdown-poster" alt="${escapeHtml(title)}">`
        : `<div class="dropdown-no-poster">Ingen bilde</div>`
    }
    <div class="dropdown-info">
      <div class="dropdown-title">
        ${escapeHtml(title)}
        <span class="media-badge ${badgeClass}">${mediaType}</span>
      </div>
      <div class="dropdown-meta">
        📅 ${escapeHtml(releaseYear)} | ⭐ ${escapeHtml(rating)}
      </div>
    </div>
  `;

  // Click: open details page inside modal
  div.addEventListener('click', () => {
    showDetails(item);
    dropdownResults.classList.remove('show');
  });

  return div;
}

// Fetch details and show info page
async function showDetails(item) {
  try {
    searchStatus.textContent = 'Henter detaljer...';
    searchStatus.classList.remove('text-danger');

    const id = item.id;
    const type = item.media_type;

    const response = await fetch(
      `${API_ENDPOINT}?action=details&id=${id}&type=${type}`
    );

    if (!response.ok) {
      const text = await response.text();
      let msg = 'Kunne ikke hente detaljer';
      try {
        const data = JSON.parse(text);
        msg = data.error || msg;
      } catch {
        msg = text || msg;
      }
      throw new Error(msg);
    }

    const details = await response.json();

    searchStatus.textContent = '';
    displayInfoPage(details, type);
  } catch (error) {
    console.error('Feil:', error);
    searchStatus.textContent = `Kunne ikke hente detaljer: ${error.message}`;
    searchStatus.classList.add('text-danger');
  }
}

// Render info page with button that fills the active row fields
function displayInfoPage(details, type) {
  const posterPath = details.poster_path ? `${IMAGE_BASE_URL}${details.poster_path}` : null;

  const title = type === 'tv' ? (details.name || details.title) : details.title;
  const releaseDate = type === 'tv' ? details.first_air_date : details.release_date;
  const releaseYear = releaseDate ? new Date(releaseDate).getFullYear() : 'Ukjent';
  const rating = details.vote_average ? details.vote_average.toFixed(1) : 'N/A';

  const imdbId = details.external_ids?.imdb_id || '';
  const tmdbId = details.id?.toString() || '';

  const mediaType = type === 'tv' ? 'TV-serie' : 'Film';
  const overview = details.overview || 'Ingen beskrivelse tilgjengelig';

  selectedItemContainer.innerHTML = `
    <div class="selected-item mt-3">
      <div class="text-center mb-3">
        ${
          posterPath
            ? `<img src="${posterPath}" class="selected-poster" style="max-width: 200px; border-radius: 10px;" alt="${escapeHtml(title)}">`
            : `<div class="alert alert-secondary d-inline-block">Ingen plakat</div>`
        }
      </div>

      <h4 class="mb-1">${escapeHtml(title || '')}</h4>

      <p class="mb-2">
        <span class="badge ${type === 'tv' ? 'bg-purple' : 'bg-primary'}">${escapeHtml(mediaType)}</span>
        <span class="ms-2">⭐ ${escapeHtml(rating)}/10</span>
        <span class="ms-2">📅 ${escapeHtml(releaseYear)}</span>
      </p>

      <p class="mb-1"><strong>TMDB ID:</strong> <code>${escapeHtml(tmdbId)}</code></p>

      <p class="mb-2">
        <strong>IMDB ID:</strong> ${
          imdbId
            ? `<code>${escapeHtml(imdbId)}</code> <a class="imdb-link ms-1" href="https://www.imdb.com/title/${encodeURIComponent(imdbId)}/" target="_blank" rel="noopener">Åpne på IMDB</a>`
            : 'Ikke tilgjengelig'
        }
      </p>

      <hr>
      <p class="small">${escapeHtml(overview)}</p>

      <div class="mt-3 text-end">
        <button
          type="button"
          class="btn btn-success"
          onclick="applyIdsToBulkRow('${escapeAttr(tmdbId)}','${escapeAttr(imdbId)}')"
        >
          Bruk disse ID-ene i skjemaet
        </button>
      </div>
    </div>
  `;
}

// Fill active row + close modal
window.applyIdsToBulkRow = function applyIdsToBulkRow(tmdbId, imdbId) {
  const imdbTarget = window.__bulkActiveImdbInput;
  const tmdbTarget = window.__bulkActiveTmdbInput;

  if (tmdbTarget && tmdbId) {
    tmdbTarget.value = tmdbId;
    tmdbTarget.dispatchEvent(new Event('input', { bubbles: true }));
    tmdbTarget.dispatchEvent(new Event('change', { bubbles: true }));
  }

  if (imdbTarget && imdbId) {
    imdbTarget.value = imdbId;
    imdbTarget.dispatchEvent(new Event('input', { bubbles: true }));
    imdbTarget.dispatchEvent(new Event('change', { bubbles: true }));
  }

  searchStatus.textContent = 'ID-er er overført til skjemaet.';
  searchStatus.classList.remove('text-danger');

  const modalInstance = bootstrap.Modal.getInstance(searchModal);
  if (modalInstance) modalInstance.hide();
};

// Helpers: escape for HTML and attribute contexts
function escapeHtml(s) {
  return String(s)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

// For putting values inside single-quoted onclick args
function escapeAttr(s) {
  return String(s)
    .replaceAll('\\', '\\\\')
    .replaceAll("'", "\\'")
    .replaceAll('\n', ' ')
    .replaceAll('\r', ' ');
}
