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
        .search-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            margin-bottom: 30px;
            position: relative;
        }
        .search-wrapper {
            position: relative;
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
            max-height: 500px;
            overflow-y: auto;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
        }
        .dropdown-results.show {
            display: block;
        }
        .dropdown-item-custom {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .dropdown-item-custom:hover {
            background: #f8f9fa;
        }
        .dropdown-item-custom:last-child {
            border-bottom: none;
        }
        .dropdown-poster {
            width: 60px;
            height: 90px;
            object-fit: cover;
            border-radius: 5px;
            flex-shrink: 0;
        }
        .dropdown-no-poster {
            width: 60px;
            height: 90px;
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
            margin-bottom: 5px;
            color: #333;
        }
        .media-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 8px;
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
            font-size: 13px;
            color: #666;
            margin-bottom: 3px;
        }
        .imdb-id {
            background: #f5c518;
            color: #000;
            padding: 2px 8px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: bold;
            font-family: monospace;
            display: inline-block;
            margin-top: 3px;
        }
        .imdb-id-missing {
            background: #e0e0e0;
            color: #666;
            padding: 2px 8px;
            border-radius: 5px;
            font-size: 11px;
            font-style: italic;
            display: inline-block;
            margin-top: 3px;
        }
        .rating-small {
            background: #4caf50;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
            display: inline-block;
        }
        .selected-item {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .selected-poster {
            width: 100%;
            max-width: 300px;
            border-radius: 10px;
        }
        .imdb-link {
            display: inline-block;
            background: #f5c518;
            color: #000;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 10px;
        }
        .imdb-link:hover {
            background: #e6b800;
            color: #000;
        }
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.15em;
        }
        .search-hint {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Søkeseksjon -->
        <div class="search-container">
            <h1 class="text-center mb-4">
                🎬 TMDB Søk
            </h1>
            <div class="row justify-content-center">
                <div class="col-md-8">
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
                    
                    <div id="searchStatus" class="text-center mt-3 text-muted"></div>
                </div>
            </div>
            
            <!-- Valgt element -->
            <div id="selectedItem"></div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Vår script -->
    <script src="script.js"></script>
</body>
</html>
