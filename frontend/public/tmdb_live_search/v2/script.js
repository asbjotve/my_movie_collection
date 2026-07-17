// Alle API-kall går nå gjennom vår PHP backend
const API_ENDPOINT = 'api.php';
const IMAGE_BASE_URL = 'https://image.tmdb.org/t/p/w500';

let searchTimeout;

// Hent elementer
const searchInput = document.getElementById('searchInput');
const dropdownResults = document.getElementById('dropdownResults');
const loadingSpinner = document.getElementById('loadingSpinner');
const searchStatus = document.getElementById('searchStatus');
const selectedItem = document.getElementById('selectedItem');

// Lytt til søkeinput
searchInput.addEventListener('input', (e) => {
    const query = e.target.value.trim();
    
    // Clear tidligere timeout
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
        return;
    }
    
    // Vent 500ms før søk (debounce)
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

// Søk etter filmer og TV-serier
async function searchMovies(query) {
    try {
        // Vis laster
        loadingSpinner.style.display = 'block';
        searchStatus.textContent = 'Søker...';
        dropdownResults.innerHTML = '';
        
        const response = await fetch(`${API_ENDPOINT}?action=search&query=${encodeURIComponent(query)}`);
        
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Noe gikk galt med API-kallet');
        }
        
        const data = await response.json();
        
        // Skjul laster
        loadingSpinner.style.display = 'none';
        
        if (!data.results || data.results.length === 0) {
            searchStatus.textContent = 'Ingen resultater funnet';
            dropdownResults.classList.remove('show');
            return;
        }
        
        searchStatus.textContent = `Fant ${data.results.length} resultat(er)`;
        displayDropdown(data.results);
        
    } catch (error) {
        console.error('Feil:', error);
        loadingSpinner.style.display = 'none';
        searchStatus.textContent = `En feil oppstod: ${error.message}`;
        searchStatus.classList.add('text-danger');
    }
}

// Vis dropdown med resultater
function displayDropdown(results) {
    dropdownResults.innerHTML = '';
    
    results.forEach(item => {
        const dropdownItem = createDropdownItem(item);
        dropdownResults.appendChild(dropdownItem);
    });
    
    dropdownResults.classList.add('show');
}

// Lag dropdown element
function createDropdownItem(item) {
    const div = document.createElement('div');
    div.className = 'dropdown-item-custom';
    
    const posterPath = item.poster_path 
        ? `${IMAGE_BASE_URL}${item.poster_path}` 
        : null;
    
    const title = item.media_type === 'tv' ? (item.name || item.title) : item.title;
    const releaseDate = item.media_type === 'tv' ? item.first_air_date : item.release_date;
    const releaseYear = releaseDate ? new Date(releaseDate).getFullYear() : 'Ukjent';
    const rating = item.vote_average ? item.vote_average.toFixed(1) : 'N/A';
    const mediaType = item.media_type === 'tv' ? 'TV-serie' : 'Film';
    const badgeClass = item.media_type === 'tv' ? 'badge-tv' : 'badge-movie';
    
    div.innerHTML = `
        ${posterPath 
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
    
    // Klikk-event
    div.addEventListener('click', () => {
        selectItem(item);
        dropdownResults.classList.remove('show');
    });
    
    return div;
}

// Velg et element og vis detaljer
async function selectItem(item) {
    try {
        searchStatus.textContent = 'Henter detaljer...';
        
        const id = item.id;
        const type = item.media_type;
        
        const response = await fetch(`${API_ENDPOINT}?action=details&id=${id}&type=${type}`);
        
        if (!response.ok) {
            throw new Error('Kunne ikke hente detaljer');
        }
        
        const details = await response.json();
        
        searchStatus.textContent = '';
        displaySelectedItem(details, type);
        
    } catch (error) {
        console.error('Feil:', error);
        searchStatus.textContent = `Kunne ikke hente detaljer: ${error.message}`;
    }
}

// Vis valgt element med full info
function displaySelectedItem(details, type) {
    const posterPath = details.poster_path 
        ? `${IMAGE_BASE_URL}${details.poster_path}` 
        : null;
    
    const title = type === 'tv' ? (details.name || details.title) : details.title;
    const releaseDate = type === 'tv' ? details.first_air_date : details.release_date;
    const releaseYear = releaseDate ? new Date(releaseDate).getFullYear() : 'Ukjent';
    const rating = details.vote_average ? details.vote_average.toFixed(1) : 'N/A';
    const imdbId = details.external_ids?.imdb_id || null;
    const mediaType = type === 'tv' ? 'TV-serie' : 'Film';
    const overview = details.overview || 'Ingen beskrivelse tilgjengelig';
    
    // Bygg HTML
    let html = `
        <div class="selected-item">
            <div class="row">
                <div class="col-md-4 text-center">
                    ${posterPath 
                        ? `<img src="${posterPath}" class="selected-poster" alt="${title}">` 
                        : `<div class="alert alert-secondary">Ingen plakat</div>`
                    }
                </div>
                <div class="col-md-8">
                    <h2>${title}</h2>
                    <p class="lead">
                        <span class="badge ${type === 'tv' ? 'bg-purple' : 'bg-primary'}">${mediaType}</span>
                        <span class="ms-2">⭐ ${rating}/10</span>
                    </p>
                    <p><strong>Utgivelsesår:</strong> ${releaseYear}</p>
                    ${imdbId ? `
                        <p>
                            <strong>IMDB ID:</strong> 
                            <code>${imdbId}</code>
                            <a href="https://www.imdb.com/title/${imdbId}/" target="_blank" class="imdb-link">
                                Åpne på IMDB
                            </a>
                        </p>
                    ` : '<p><strong>IMDB ID:</strong> Ikke tilgjengelig</p>'}
                    <p><strong>TMDB ID:</strong> <code>${details.id}</code></p>
                    <hr>
                    <h5>Beskrivelse</h5>
                    <p>${overview}</p>
                </div>
            </div>
        </div>
    `;
    
    selectedItem.innerHTML = html;
}
