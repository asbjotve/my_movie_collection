// Alle API-kall går nå gjennom vår PHP backend
const API_ENDPOINT = 'api.php';
const IMAGE_BASE_URL = 'https://image.tmdb.org/t/p/w500';

let searchTimeout;

// Elementer i modal
const searchInput = document.getElementById('searchInput');
const dropdownResults = document.getElementById('dropdownResults');
const loadingSpinner = document.getElementById('loadingSpinner');
const searchStatus = document.getElementById('searchStatus');
const searchModal = document.getElementById('searchModal');
const selectedItemContainer = document.getElementById('selectedItem'); // INFOSIDE i modal

// Skjemafelter på hovedsiden
const imdbIdField = document.getElementById('imdbIdField');
const tmdbIdField = document.getElementById('tmdbIdField');

// Når modal åpnes: fokuser på søkefelt
searchModal.addEventListener('shown.bs.modal', function () {
    searchInput.focus();
});

// Når modal lukkes: rydd opp
searchModal.addEventListener('hidden.bs.modal', function () {
    searchInput.value = '';
    dropdownResults.classList.remove('show');
    dropdownResults.innerHTML = '';
    searchStatus.textContent = '';
    selectedItemContainer.innerHTML = '';
});

// Input-lytter
searchInput.addEventListener('input', (e) => {
    const query = e.target.value.trim();

    clearTimeout(searchTimeout);

    if (query.length === 0) {
        dropdownResults.classList.remove('show');
        dropdownResults.innerHTML = '';
        searchStatus.textContent = '';
        selectedItemContainer.innerHTML = '';
        return;
    }

    if (query.length < 2) {
        searchStatus.textContent = 'Skriv minst 2 tegn for å søke...';
        dropdownResults.classList.remove('show');
        dropdownResults.innerHTML = '';
        selectedItemContainer.innerHTML = '';
        return;
    }

    searchTimeout = setTimeout(() => {
        searchMovies(query);
    }, 500);
});

// Skjul dropdown når man klikker utenfor
document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-wrapper')) {
        dropdownResults.classList.remove('show');
    }
});

// Søk i TMDB (filmer + TV)
async function searchMovies(query) {
    try {
        loadingSpinner.style.display = 'block';
        searchStatus.textContent = 'Søker...';
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
        if (data.search_year) {
            statusText += ` for år ${data.search_year}`;
        }
        searchStatus.textContent = statusText;

        displayDropdown(data.results);
    } catch (error) {
        console.error('Feil:', error);
        loadingSpinner.style.display = 'none';
        searchStatus.textContent = `En feil oppstod: ${error.message}`;
        searchStatus.classList.add('text-danger');
    }
}

// Bygg dropdown
function displayDropdown(results) {
    dropdownResults.innerHTML = '';

    results.forEach((item) => {
        const dropdownItem = createDropdownItem(item);
        dropdownResults.appendChild(dropdownItem);
    });

    dropdownResults.classList.add('show');
}

// Ett resultat i dropdown
function createDropdownItem(item) {
    const div = document.createElement('div');
    div.className = 'dropdown-item-custom';

    const posterPath = item.poster_path
        ? `${IMAGE_BASE_URL}${item.poster_path}`
        : null;

    const title = item.media_type === 'tv' ? (item.name || item.title) : item.title;
    const releaseDate =
        item.media_type === 'tv' ? item.first_air_date : item.release_date;
    const releaseYear = releaseDate ? new Date(releaseDate).getFullYear() : 'Ukjent';
    const rating = item.vote_average ? item.vote_average.toFixed(1) : 'N/A';
    const mediaType = item.media_type === 'tv' ? 'TV-serie' : 'Film';
    const badgeClass = item.media_type === 'tv' ? 'badge-tv' : 'badge-movie';

    div.innerHTML = `
        ${
            posterPath
                ? `<img src="${posterPath}" class="dropdown-poster" alt="${title}">`
                : `<div class="dropdown-no-poster">Ingen bilde</div>`
        }
        <div class="dropdown-info">
            <div class="dropdown-title">
                ${title}
                <span class="media-badge ${badgeClass}">${mediaType}</span>
            </div>
            <div class="dropdown-meta">
                📅 ${releaseYear} | ⭐ ${rating}
            </div>
        </div>
    `;

    // Klikk: vis INFOSIDE, ikke fyll skjema direkte
    div.addEventListener('click', () => {
        showDetails(item);
        dropdownResults.classList.remove('show');
    });

    return div;
}

// Hent detaljer og vis infoside i modal
async function showDetails(item) {
    try {
        searchStatus.textContent = 'Henter detaljer...';

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
    }
}

// Vis infoside i modal med knapp som fyller skjemaet
function displayInfoPage(details, type) {
    const posterPath = details.poster_path
        ? `${IMAGE_BASE_URL}${details.poster_path}`
        : null;

    const title = type === 'tv' ? (details.name || details.title) : details.title;
    const releaseDate =
        type === 'tv' ? details.first_air_date : details.release_date;
    const releaseYear = releaseDate ? new Date(releaseDate).getFullYear() : 'Ukjent';
    const rating = details.vote_average ? details.vote_average.toFixed(1) : 'N/A';
    const imdbId = details.external_ids?.imdb_id || '';
    const mediaType = type === 'tv' ? 'TV-serie' : 'Film';
    const overview = details.overview || 'Ingen beskrivelse tilgjengelig';
    const tmdbId = details.id?.toString() || '';

    selectedItemContainer.innerHTML = `
        <div class="selected-item mt-3">
            <div class="text-center mb-3">
                ${
                    posterPath
                        ? `<img src="${posterPath}" class="selected-poster" style="max-width: 200px; border-radius: 10px;" alt="${title}">`
                        : `<div class="alert alert-secondary d-inline-block">Ingen plakat</div>`
                }
            </div>
            <h4 class="mb-1">${title}</h4>
            <p class="mb-2">
                <span class="badge ${type === 'tv' ? 'bg-purple' : 'bg-primary'}">${mediaType}</span>
                <span class="ms-2">⭐ ${rating}/10</span>
                <span class="ms-2">📅 ${releaseYear}</span>
            </p>
            <p class="mb-1"><strong>TMDB ID:</strong> <code>${tmdbId}</code></p>
            <p class="mb-2">
                <strong>IMDB ID:</strong> ${
                    imdbId
                        ? `<code>${imdbId}</code> <a class="imdb-link ms-1" href="https://www.imdb.com/title/${imdbId}/" target="_blank">Åpne på IMDB</a>`
                        : 'Ikke tilgjengelig'
                }
            </p>
            <hr>
            <p class="small">${overview}</p>
            <div class="mt-3 text-end">
                <button
                    type="button"
                    class="btn btn-success"
                    onclick="applyIdsToForm('${tmdbId}', '${imdbId}')"
                >
                    Bruk disse ID-ene i skjemaet
                </button>
            </div>
        </div>
    `;
}

// Knappen på infosiden: fyll skjema og (valgfritt) lukk modal
function applyIdsToForm(tmdbId, imdbId) {
 //   if (tmdbId) {
   //     tmdbIdField.value = tmdbId;
   // }
    if (imdbId) {
        imdbIdField.value = imdbId;
    }

    // Gi litt tilbakemelding i modal
    searchStatus.textContent = 'ID-er er overført til skjemaet.';

    // Lukk modal automatisk (valgfritt – kommenter ut hvis du vil beholde den åpen)
    const modalInstance = bootstrap.Modal.getInstance(searchModal);
    if (modalInstance) {
        modalInstance.hide();
    }
}
