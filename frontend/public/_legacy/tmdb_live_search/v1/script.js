// Alle API-kall går nå gjennom vår PHP backend
const API_ENDPOINT = 'api.php';
const IMAGE_BASE_URL = 'https://image.tmdb.org/t/p/w500';

let searchTimeout;

// Hent elementer
const searchInput = document.getElementById('searchInput');
const resultsContainer = document.getElementById('results');
const loadingIndicator = document.getElementById('loading');
const searchStatus = document.getElementById('searchStatus');

// Lytt til søkeinput
searchInput.addEventListener('input', (e) => {
    const query = e.target.value.trim();
    
    // Clear tidligere timeout
    clearTimeout(searchTimeout);
    
    if (query.length === 0) {
        resultsContainer.innerHTML = '';
        searchStatus.textContent = '';
        return;
    }
    
    if (query.length < 2) {
        searchStatus.textContent = 'Skriv minst 2 tegn for å søke...';
        return;
    }
    
    // Vent 500ms før søk (debounce)
    searchTimeout = setTimeout(() => {
        searchMovies(query);
    }, 500);
});

// Søk etter filmer via PHP backend
async function searchMovies(query) {
    try {
        // Vis laster
        loadingIndicator.classList.remove('d-none');
        searchStatus.textContent = 'Søker...';
        resultsContainer.innerHTML = '';
        
        const response = await fetch(`${API_ENDPOINT}?query=${encodeURIComponent(query)}`);
        
        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.error || 'Noe gikk galt med API-kallet');
        }
        
        const data = await response.json();
        
        // Skjul laster
        loadingIndicator.classList.add('d-none');
        
        if (data.results.length === 0) {
            searchStatus.textContent = 'Ingen filmer funnet';
            return;
        }
        
        searchStatus.textContent = `Fant ${data.results.length} film(er)`;
        displayMovies(data.results);
        
    } catch (error) {
        console.error('Feil:', error);
        loadingIndicator.classList.add('d-none');
        searchStatus.textContent = `En feil oppstod: ${error.message}`;
        searchStatus.classList.add('text-danger');
    }
}

// Vis filmer
function displayMovies(movies) {
    resultsContainer.innerHTML = '';
    
    movies.forEach(movie => {
        const movieCard = createMovieCard(movie);
        resultsContainer.appendChild(movieCard);
    });
}

// Lag filmkort
function createMovieCard(movie) {
    const col = document.createElement('div');
    col.className = 'col-md-4 col-lg-3';
    
    const posterPath = movie.poster_path 
        ? `${IMAGE_BASE_URL}${movie.poster_path}` 
        : null;
    
    const rating = movie.vote_average ? movie.vote_average.toFixed(1) : 'N/A';
    const releaseYear = movie.release_date ? new Date(movie.release_date).getFullYear() : 'Ukjent';
    
    col.innerHTML = `
        <div class="card movie-card h-100 shadow">
            ${posterPath 
                ? `<img src="${posterPath}" class="card-img-top movie-poster" alt="${movie.title}">` 
                : `<div class="no-poster"><span>Ingen plakat</span></div>`
            }
            <div class="card-body">
                <h5 class="card-title">${movie.title}</h5>
                <p class="card-text text-muted">${releaseYear}</p>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="rating">⭐ ${rating}</span>
                    <small class="text-muted">${movie.vote_count} stemmer</small>
                </div>
            </div>
        </div>
    `;
    
    // Legg til klikk-event for mer info
    col.addEventListener('click', () => {
        showMovieDetails(movie);
    });
    
    return col;
}

// Vis filmdetaljer
function showMovieDetails(movie) {
    alert(`Filmtittel: ${movie.title}\n\nBeskrivelse: ${movie.overview || 'Ingen beskrivelse tilgjengelig'}`);
}
