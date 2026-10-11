<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

// Sjekk at API-nøkkel er satt
if (!TMDB_API_KEY) {
    http_response_code(500);
    echo json_encode(['error' => 'API-nøkkel ikke konfigurert']);
    exit;
}

// Hent søkeparameter
$query = isset($_GET['query']) ? trim($_GET['query']) : '';

if (empty($query)) {
    http_response_code(400);
    echo json_encode(['error' => 'Søkeparameter mangler']);
    exit;
}

// Bygg TMDB API URL
$url = TMDB_BASE_URL . '/search/movie?' . http_build_query([
    'api_key' => TMDB_API_KEY,
    'language' => 'no-NO',
    'query' => $query,
    'page' => 1
]);

// Utfør API-kall
$response = file_get_contents($url);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Kunne ikke hente data fra TMDB']);
    exit;
}

// Returner resultatet
echo $response;
?>
