<!DOCTYPE html>
<html lang="no">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TMDB Filmsøk</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .main-container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px 25px 35px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .main-title {
            color: #333;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .main-subtitle {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 25px;
        }
        .open-search-btn {
            width: 100%;
        }

        /* Modal / søkestil */
        .modal-content {
            border-radius: 15px;
            border: none;
        }
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            border: none;
        }
        .modal-body {
            padding: 25px;
        }
        .search-wrapper {
            position: relative;
            margin-bottom: 10px;
        }
        .dropdown-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 10px 10px;
            max-height: 400px;
            overflow-y: auto;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
        }
        .dropdown-results.show {
            display: block;
        }
        .dropdown-item-custom {
            padding: 12px 14px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .dropdown-item-custom:hover {
            background: #f8f9fa;
        }
        .dropdown-item-custom:last-child {
            border-bottom: none;
        }
        .dropdown-poster {
            width: 50px;
            height: 75px;
            object-fit: cover;
            border-radius: 5px;
            flex-shrink: 0;
        }
        .dropdown-no-poster {
            width: 50px;
            height: 75px;
            background: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            font-size: 10px;
            color: #999;
            flex-shrink: 0;
        }
        .dropdown-info {
            flex: 1;
            min-width: 0;
        }
        .dropdown-title {
            font-weight: 600;
            margin-bottom: 3px;
            color: #333;
            font-size: 0.95rem;
        }
        .media-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 6px;
        }
        .badge-movie {
            background: #e3f2fd;
            color: #1976d2;
        }
        .badge-tv {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        .dropdown-meta {
            font-size: 12px;
            color: #666;
        }
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.15em;
        }
        .search-hint {
            font-size: 12px;
            color: #666;
            margin-top: 6px;
        }
        .selected-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        .selected-poster {
            max-width: 200px;
            border-radius: 10px;
        }
        .imdb-link {
            display: inline-block;
            background: #f5c518;
            color: #000;
            padding: 4px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .imdb-link:hover {
            background: #e6b800;
            color: #000;
        }
        .bg-purple {
            background-color: #6f42c1 !important;
        }
    </style>
</head>
<body>
    <!-- Hovedinnhold med skjema -->
    <div class="container">
        <div class="main-container mt-4">
            <h1 class="main-title">TMDB / IMDB-registrering</h1>
            <p class="main-subtitle">
                Fyll ut ID-er manuelt, eller bruk søkeknappen for å hente dem fra TMDB.
            </p>

            <form>
                <div class="mb-3">
                    <label for="imdbIdField" class="form-label">IMDB-ID</label>
                    <input type="text" class="form-control" id="imdbIdField" name="imdb-id" placeholder="tt1234567">
                </div>
                <div class="mb-3">
                    <label for="tmdbIdField" class="form-label">TMDB-ID</label>
                    <input type="text" class="form-control" id="tmdbIdField" name="tmdb-id" placeholder="12345">
                </div>

                <div class="mb-3">
                    <button type="button" class="btn btn-primary open-search-btn" data-bs-toggle="modal" data-bs-target="#searchModal">
                        🔍 Søk i TMDB
                    </button>
                </div>

                <!-- Her kan du evt. ha en "Lagre"-knapp -->
                <!-- <button type="submit" class="btn btn-success">Lagre</button> -->
            </form>
        </div>
    </div>

    <!-- Søkemodal -->
    <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="searchModalLabel">
                        🔍 Søk etter filmer og TV-serier
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Lukk"></button>
                </div>
                <div class="modal-body">
                    <div class="search-wrapper">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                                    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                                </svg>
                            </span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Søk etter filmer eller TV-serier..." autocomplete="off">
                            <span class="input-group-text" id="loadingSpinner" style="display: none;">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
                                    <span class="visually-hidden">Søker...</span>
                                </div>
                            </span>
                        </div>
                        <div class="search-hint">
                            💡 Tips: Søk med årstall, f.eks. "Titanic 1997" eller "Breaking Bad 2008"
                        </div>

                        <!-- Dropdown resultater -->
                        <div id="dropdownResults" class="dropdown-results"></div>
                    </div>

                    <div id="searchStatus" class="text-center mt-2 text-muted" style="min-height: 24px;"></div>

                    <!-- INFOSIDE: detaljer om valgt film/serie -->
                    <div id="selectedItem" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Vår script -->
    <script src="script.js"></script>
</body>
</html>
