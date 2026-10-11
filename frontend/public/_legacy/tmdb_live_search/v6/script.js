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

// Skjemafelter på hovedsiden
const imdbIdField = document.getElementById('imdbIdField');
const tmdbIdField = document.getElementById('tmdbIdField');

// Fokuser søkefelt når modal åpnes
searchModal.addEventListener('shown.bs.modal', function () {
    searchInput.focus();
});

// Rydd opp når modal lukkes
searchModal.addEventListener('hidden.bs.modal', function () {
    searchInput.value = '';
    dropdownResults.classList.remove('show');
    dropdownResults.innerHTML = '';
    searchStatus.textContent = '';
});

// Input-lytter
searchInput.addEventListener('input', (e) => {
    const query = e.target.value.trim();

    clearTimeout(searchTimeout);

    if (query.length === 0) {
        dropdownResults.classList.remove('show');
        dropdownResults.innerHTML = '';
        searchStatus.textContent = '';
        return;
    }

    if (query.length < 2) {
        searchStatus.textContent = 'Skriv minst 2 tegn for å søke...';
        dropdownResults.classList.remove('show');
        dropdownResults.innerHTML = '';
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

// S��k i TMDB (filmer + TV)
async function searchMovies(query) {
    try {
        loadingSpinner.style.display = 'block';
        searchStatus.textContent = 'Søker...';
        dropdownResults.innerHTML = '';

        const response = await fetch(
            `${API_ENDPOINT}?action=search&query=${encodeURIComponent(query)}`
        );

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || 'Noe gikk galt med API-kallet');
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

    // Klikk på et resultat:
    div.addEventListener('click', () => {
        handleResultClick(item);
    });

    return div;
}

// Når bruker klikker på et resultat:
// 1) Hent detaljer (for å få IMDB-ID)
// 2) Fyll ut skjemafeltene på hovedsiden
// 3) Lukk modal
async function handleResultClick(item) {
    try {
        searchStatus.textContent = 'Henter detaljer...';

        const id = item.id;
        const type = item.media_type;

        const response = await fetch(
            `${API_ENDPOINT}?action=details&id=${id}&type=${type}`
        );

        if (!response.ok) {
            throw new Error('Kunne ikke hente detaljer');
        }

        const details = await response.json();

        const imdbId = details.external_ids?.imdb_id || '';
        const tmdbId = details.id?.toString() || '';

        // Fyll ut skjemafeltene
        imdbIdField.value = imdbId;
        tmdbIdField.value = tmdbId;

        searchStatus.textContent = 'Valg overført til skjemaet.';

        // Lukk modal
        const modalInstance = bootstrap.Modal.getInstance(searchModal);
        modalInstance.hide();
    } catch (error) {
        console.error('Feil:', error);
        searchStatus.textContent = `Kunne ikke hente detaljer: ${error.message}`;
    }
}
