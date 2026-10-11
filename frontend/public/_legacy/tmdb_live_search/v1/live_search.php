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
        }
        .movie-card {
            transition: transform 0.3s ease;
            cursor: pointer;
            margin-bottom: 20px;
        }
        .movie-card:hover {
            transform: translateY(-5px);
        }
        .movie-poster {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 10px;
        }
        .rating {
            background: #ffc107;
            color: #000;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
        }
        .no-poster {
            width: 100%;
            height: 400px;
            background: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Søkeseksjon -->
        <div class="search-container">
            <h1 class="text-center mb-4">
                <i class="bi bi-film"></i> TMDB Filmsøk
            </h1>
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-search" viewBox="0 0 16 16">
                                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                            </svg>
                        </span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Søk etter filmer..." autocomplete="off">
                    </div>
                    <div id="searchStatus" class="text-center mt-3 text-muted"></div>
                </div>
            </div>
        </div>

        <!-- Resultater -->
        <div id="results" class="row"></div>
        
        <!-- Laster indikator -->
        <div id="loading" class="text-center d-none">
            <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Laster...</span>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Vår script -->
    <script src="script.js"></script>
</body>
</html>
