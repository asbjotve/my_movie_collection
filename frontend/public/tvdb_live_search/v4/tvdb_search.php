<!DOCTYPE html>
<html lang="no" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <title>TVDB Live-søk v4 – med detaljer</title>

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
            cursor: pointer;
        }
        .result-item:hover {
            background-color: rgba(255,255,255,0.08);
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

        /* Detaljmodal */
        .detail-poster {
            max-width: 200px;
            border-radius: .75rem;
        }
        .detail-meta {
            font-size: .9rem;
            color: #ccc;
        }
        .tag-pill {
            display: inline-block;
            padding: .1rem .5rem;
            border-radius: 999px;
            background-color: rgba(255,255,255,0.06);
            font-size: .75rem;
            margin-right: .25rem;
            margin-bottom: .25rem;
        }
    </style>
</head>
<body>
<div class="container px-3">
    <div class="card search-card">
        <div class="card-header search-header py-3">
            <h1 class="h4 mb-0 d-flex align-items-center">
                <span class="me-2">🔎</span> Live-søk fra TheTVDB (v4 – med detaljer)
            </h1>
            <p class="mb-0 small text-light opacity-75">
                Søk etter enten filmer eller TV-serier direkte fra TVDB (API v4), og se detaljer i modal.
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
                           placeholder="Skriv tittel (f.eks. 'Stargate', 'The Matrix')"
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

            <!-- Type-filter: film / serie -->
            <div class="mb-3">
                <label class="form-label">Type</label>
                <div class="d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="typeRadios"
                               id="typeSeries" value="series" checked>
                        <label class="form-check-label" for="typeSeries">
                            TV-serier
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="typeRadios"
                               id="typeMovies" value="movie">
                        <label class="form-check-label" for="typeMovies">
                            Filmer
                        </label>
                    </div>
                </div>
            </div>

            <!-- Statuslinje -->
            <div id="searchStatus" class="text-muted mb-3" style="min-height: 1.2rem;"></div>

            <!-- Resultatliste -->
            <div id="results" class="vstack gap-2"></div>
        </div>
    </div>
</div>

<!-- Detaljmodal -->
<div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title" id="detailModalLabel">Detaljer</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Lukk"></button>
      </div>
      <div class="modal-body">
        <div id="detailContent">Laster...</div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
const API_ENDPOINT = 'tvdb_api.php';

const searchInput    = document.getElementById('searchInput');
const loadingSpinner = document.getElementById('loadingSpinner');
const searchStatus   = document.getElementById('searchStatus');
const resultsDiv     = document.getElementById('results');
const typeRadios     = document.querySelectorAll('input[name="typeRadios"]');

const detailModalEl  = document.getElementById('detailModal');
const detailModal    = new bootstrap.Modal(detailModalEl);
const detailContent  = document.getElementById('detailContent');

let searchTimeout = null;

function getSelectedType() {
    const checked = document.querySelector('input[name="typeRadios"]:checked');
    return checked ? checked.value : 'series';
}

// Re-søk når type endres
typeRadios.forEach(radio => {
    radio.addEventListener('change', () => {
        const q = searchInput.value.trim();
        if (q.length >= 2) {
            searchTvdb(q, getSelectedType());
        }
    });
});

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
        searchTvdb(query, getSelectedType());
    }, 400);
});

async function searchTvdb(query, type) {
    try {
        loadingSpinner.style.display = 'inline-flex';
        searchStatus.textContent = 'Søker...';
        resultsDiv.innerHTML = '';

        const url = `${API_ENDPOINT}?action=search&query=${encodeURIComponent(query)}&type=${encodeURIComponent(type)}`;
        const response = await fetch(url);

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
        console.log('TVDB search response:', data);

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

        searchStatus.textContent = `Fant ${items.length} resultat(er). Klikk på en rad for detaljer.`;
        renderResults(items, type);
    } catch (err) {
        console.error(err);
        loadingSpinner.style.display = 'none';
        searchStatus.textContent = `En feil oppstod: ${err.message}`;
    }
}

function renderResults(items, forcedType) {
    resultsDiv.innerHTML = '';

    items.forEach(item => {
        const container = document.createElement('div');
        container.className = 'result-item';

        const type = item.type || forcedType || 'unknown';

        const name =
            item.name ||
            item.title ||
            item.slug ||
            `(uten tittel – id ${item.tvdb_id || item.id || '?'})`;

        const year    = item.year || '';
        const network = item.network || (Array.isArray(item.companies) ? item.companies[0] : '');
        const id      = item.tvdb_id || item.id || '';
        const overview = item.overview || '';

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
                            ? `<div class="small-label">Network/Studio: ${escapeHtml(network)}</div>`
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
                              overview.length > 200
                                ? overview.substring(0, 197) + '…'
                                : overview
                          )
                      }</p>`
                    : ''}
            </div>
        `;

        // Klikk = hent detaljer og åpne modal
        container.addEventListener('click', () => {
            if (!id) return;
            fetchDetails(type, id, name);
        });

        resultsDiv.appendChild(container);
    });
}

async function fetchDetails(type, tvdbId, fallbackName) {
    try {
        detailContent.innerHTML = 'Laster detaljer...';
        detailModal.show();

        const url = `${API_ENDPOINT}?action=details&type=${encodeURIComponent(type)}&id=${encodeURIComponent(tvdbId)}`;
        const response = await fetch(url);

        if (!response.ok) {
            const text = await response.text();
            let msg = 'Kunne ikke hente detaljer fra TVDB';
            try {
                const data = JSON.parse(text);
                msg = data.error || msg;
            } catch {
                msg = text || msg;
            }
            throw new Error(msg);
        }

        const data = await response.json();
        console.log('TVDB details response:', data);

        if (data.status !== 'success') {
            detailContent.innerHTML = `<div class="alert alert-warning mb-0">TVDB svarte: ${escapeHtml(data.status || 'ukjent status')}</div>`;
            return;
        }

        const rec = data.data || {};
        renderDetails(rec, type, tvdbId, fallbackName);
    } catch (err) {
        console.error(err);
        detailContent.innerHTML = `<div class="alert alert-danger mb-0">En feil oppstod: ${escapeHtml(err.message)}</div>`;
    }
}

function renderDetails(rec, type, tvdbId, fallbackName) {
    // Forskjell på movie/series felter:
    const isSeries = type === 'series';

    const title = rec.name || fallbackName || '(uten tittel)';
    const year  = rec.year || rec.firstAired?.slice(0,4) || '';
    const image = rec.image || null;

    const overview =
        rec.overview ||
        (Array.isArray(rec.overviewTranslations) && rec.overviewTranslations[0]?.overview) ||
        '';

    let genres = [];
    if (Array.isArray(rec.genres)) {
        genres = rec.genres.map(g => g.name).filter(Boolean);
    }

    let country = '';
    if (isSeries) {
        country = rec.country || rec.originalCountry || '';
    } else {
        country = rec.originalCountry || '';
    }

    let language = '';
    if (isSeries) {
        language = rec.originalLanguage || '';
    } else {
        language = rec.originalLanguage || '';
    }

    let runtime = '';
    if (!isSeries && rec.runtime) {
        runtime = rec.runtime + ' min';
    } else if (isSeries && rec.averageRuntime) {
        runtime = rec.averageRuntime + ' min (snitt)';
    }

    detailModalEl.querySelector('.modal-title').textContent =
        (isSeries ? 'Serie: ' : 'Film: ') + title;

    detailContent.innerHTML = `
        <div class="row g-3">
            <div class="col-md-4 text-center">
                ${image
                    ? `<img src="${image}" alt="${escapeHtml(title)}" class="detail-poster mb-3">`
                    : `<div class="alert alert-secondary">Ingen plakat tilgjengelig</div>`}
                <div class="detail-meta text-start small">
                    <div><strong>Type:</strong> ${isSeries ? 'Serie' : 'Film'}</div>
                    <div><strong>TVDB ID:</strong> <code>${escapeHtml(tvdbId.toString())}</code></div>
                    ${year ? `<div><strong>År:</strong> ${escapeHtml(year)}</div>` : ''}
                    ${runtime ? `<div><strong>Spilletid:</strong> ${escapeHtml(runtime)}</div>` : ''}
                    ${country ? `<div><strong>Land:</strong> ${escapeHtml(country)}</div>` : ''}
                    ${language ? `<div><strong>Språk:</strong> ${escapeHtml(language)}</div>` : ''}
                </div>
            </div>
            <div class="col-md-8">
                ${genres.length
                    ? `<div class="mb-2">
                           <strong>Sjangere:</strong><br>
                           ${genres.map(g => `<span class="tag-pill">${escapeHtml(g)}</span>`).join(' ')}
                       </div>`
                    : ''}
                ${overview
                    ? `<p class="mb-2">${escapeHtml(overview)}</p>`
                    : '<p class="text-muted">Ingen beskrivelse tilgjengelig.</p>'}
            </div>
        </div>
    `;
}

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
