<!DOCTYPE html>
<html lang="no" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>TVDB Live-søk</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            min-height: 100vh;
            background: radial-gradient(circle at top, #343a40 0, #000 60%);
            padding: 2rem 0;
        }
        .search-card {
            max-width: 900px;
            margin: 0 auto;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0,0,0,.6);
        }
        .search-header {
            border-radius: 1rem 1rem 0 0;
            background: linear-gradient(120deg, #0d6efd, #6610f2);
            color: #fff;
        }
        .result-item {
            border-radius: .75rem;
            background-color: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.06);
            padding: .75rem;
            display: flex;
            gap: .75rem;
            align-items: flex-start;
        }
        .result-item:hover {
            background-color: rgba(255,255,255,0.06);
        }
        .poster {
            width: 70px;
            height: 105px;
            border-radius: .5rem;
            object-fit: cover;
            flex-shrink: 0;
        }
        .poster-placeholder {
            width: 70px;
            height: 105px;
            border-radius: .5rem;
            background: #444;
            color: #aaa;
            font-size: .7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .badge-type {
            font-size: .7rem;
            border-radius: 999px;
        }
        .badge-series {
            background-color: rgba(13,110,253,0.15);
            color: #9bc2ff;
        }
        .badge-movie {
            background-color: rgba(220,53,69,0.15);
            color: #ffb3bd;
        }
        .small-label {
            font-size: .75rem;
            color: #aaa;
        }
        .overview {
            font-size: .85rem;
            color: #ccc;
        }
    </style>
</head>
<body>
<div class="container px-3">
    <div class="card search-card">
        <div class="card-header search-header py-3">
            <h1 class="h4 mb-0 d-flex align-items-center">
                <span class="me-2">🔎</span> Live-søk fra TheTVDB
            </h1>
            <p class="mb-0 small text-light opacity-75">
                Søk etter serier og filmer direkte fra TVDB (API v4).
            </p>
        </div>

        <div class="card-body">
            <!-- Søkeinput -->
            <div class="mb-3">
                <label for="searchInput" class="form-label">Søk</label>
                <div class="input-group input-group-lg">
                    <span class="input-group-text">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text"
                           id="searchInput"
                           class="form-control"
                           placeholder="Skriv tittel (f.eks. 'Breaking Bad', 'The Matrix')"
                           autocomplete="off">
                    <span class="input-group-text" id="loadingSpinner" style="display:none;">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Søker...</span>
                        </div>
                    </span>
                </div>
                <div class="form-text text-secondary">
                    Resultater oppdateres fortløpende mens du skriver. Minst 2 tegn.
                </div>
            </div>

            <!-- Statuslinje -->
            <div id="searchStatus" class="text-muted mb-3" style="min-height: 1.2rem;"></div>

            <!-- Resultatliste -->
            <div id="results" class="vstack gap-2"></div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const API_ENDPOINT = 'tvdb_api.php';

const searchInput    = document.getElementById('searchInput');
const loadingSpinner = document.getElementById('loadingSpinner');
const searchStatus   = document.getElementById('searchStatus');
const resultsDiv     = document.getElementById('results');

let searchTimeout = null;

// Debounced søk
searchInput.addEventListener('input', (e) => {
    const query = e.target.value.trim();
    clearTimeout(searchTimeout);

    if (query.length === 0) {
        resultsDiv.innerHTML = '';
        searchStatus.textContent = '';
        return;
    }

    if (query.length < 2) {
        resultsDiv.innerHTML = '';
        searchStatus.textContent = 'Skriv minst 2 tegn for å søke...';
        return;
    }

    searchTimeout = setTimeout(() => {
        searchTvdb(query);
    }, 400);
});

async function searchTvdb(query) {
    try {
        loadingSpinner.style.display = 'inline-flex';
        searchStatus.textContent = 'Søker...';
        resultsDiv.innerHTML = '';

        const response = await fetch(
            `${API_ENDPOINT}?action=search&query=${encodeURIComponent(query)}`
        );

        if (!response.ok) {
            const text = await response.text();
            let msg = 'Noe gikk galt med TVDB-APIet';
            try {
                const data = JSON.parse(text);
                msg = data.error || msg;
            } catch {
                msg = text || msg;
            }
            throw new Error(msg);
        }

        const data = await response.json();
        console.log('TVDB raw response:', data); // debug

        loadingSpinner.style.display = 'none';

        if (data.status !== 'success') {
            searchStatus.textContent = `TVDB svarte: ${data.status || 'ukjent status'}`;
            return;
        }

        const items = Array.isArray(data.data) ? data.data : [];
        if (items.length === 0) {
            searchStatus.textContent = 'Ingen resultater funnet.';
            return;
        }

        searchStatus.textContent = `Fant ${items.length} resultat(er).`;
        renderResults(items);
    } catch (err) {
        console.error(err);
        loadingSpinner.style.display = 'none';
        searchStatus.textContent = `En feil oppstod: ${err.message}`;
    }
}

function renderResults(items) {
    resultsDiv.innerHTML = '';

    items.forEach(item => {
        // item er et SearchResult (jfr. OpenAPI)
        const container = document.createElement('div');
        container.className = 'result-item';

        const type = item.type || 'unknown';

        // Tittel: prøv name, deretter title, til slutt slug/id
        const name =
            item.name ||
            item.title ||
            item.slug ||
            `(uten tittel – id ${item.tvdb_id || item.id || '?'})`;

        const year    = item.year || '';
        const network = item.network || (Array.isArray(item.companies) ? item.companies[0] : '');
        const id      = item.tvdb_id || item.id || '';
        const overview = item.overview || '';

        // Bilde: image_url, så poster, så thumbnail
        const posterUrl =
            item.image_url ||
            item.poster ||
            item.thumbnail ||
            null;

        const isSeries = type === 'series';
        const isMovie  = type === 'movie';

        container.innerHTML = `
            ${posterUrl
                ? `<img src="${posterUrl}" class="poster" alt="${escapeHtml(name)}">`
                : `<div class="poster-placeholder">Ingen<br>plakat</div>`}
            <div class="flex-grow-1">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="fw-semibold">${escapeHtml(name)}</div>
                        ${year
                            ? `<div class="small-label">År: ${escapeHtml(year)}</div>`
                            : ''}
                        ${network
                            ? `<div class="small-label">Network: ${escapeHtml(network)}</div>`
                            : ''}
                    </div>
                    <div class="text-end">
                        <span class="badge badge-type ${
                            isSeries ? 'badge-series' :
                            isMovie  ? 'badge-movie'  :
                                       'bg-secondary'
                        }">
                            ${isSeries ? 'Serie' : isMovie ? 'Film' : escapeHtml(type)}
                        </span>
                        ${id
                            ? `<div class="small-label mt-1">TVDB ID: <code>${escapeHtml(id.toString())}</code></div>`
                            : ''}
                    </div>
                </div>
                ${overview
                    ? `<p class="overview mt-2 mb-0">${
                          escapeHtml(
                              overview.length > 280
                                ? overview.substring(0, 277) + '…'
                                : overview
                          )
                      }</p>`
                    : ''}
            </div>
        `;

        resultsDiv.appendChild(container);
    });
}

// Enkel HTML-escaping
function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[c]));
}
</script>

</body>
</html>
