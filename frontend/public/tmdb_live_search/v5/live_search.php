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
            text-align: center;
            padding: 50px 20px;
        }
        .main-title {
            color: white;
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .main-subtitle {
            color: rgba(255,255,255,0.9);
            font-size: 1.2rem;
            margin-bottom: 30px;
        }
        .open-search-btn {
            padding: 15px 40px;
            font-size: 1.3rem;
            border-radius: 50px;
            background: white;
            color: #667eea;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            transition: all 0.3s;
        }
        .open-search-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
            background: #f8f9fa;
        }
        
        /* Modal styling */
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
            padding: 30px;
        }
        .search-wrapper {
            position: relative;
            margin-bottom: 20px;
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
        .selected-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        .selected-poster {
            width: 100%;
            max-width: 200px;
            border-radius: 10px;
            margin-bottom: 15px;
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
        .result-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Hovedside -->
    <div class="container main-container">
        <h1 class="main-title">🎬 TMDB Filmsøk</h1>
        <p class="main-subtitle">Søk etter dine favorittfilmer og TV-serier</p>
        <button class="btn open-search-btn" data-bs-toggle="modal" data-bs-target="#searchModal">
            🔍 Åpne søk
        </button>
        
        <!-- Valgt element vises her -->
        <div id="selectedItemMain"></div>
    </div>

    <!-- Søkemodal -->
    <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="searchModalLabel">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-search me-2" viewBox="0 0 16 16">
                            <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                        </svg>
                        Søk etter filmer og TV-serier
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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
                    
                    <div id="searchStatus" class="text-center mt-3 text-muted"></div>
                    
                    <!-- Valgt element i modal -->
                    <div id="selectedItem"></div>
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
