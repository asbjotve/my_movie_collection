<?php
// Aktiver feilmeldinger for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Sett headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    // Last config
    require_once __DIR__ . '/config.php';
    
    // Sjekk at API-nøkkel er satt
    if (!defined('TMDB_API_KEY') || empty(TMDB_API_KEY)) {
        throw new Exception('API-nøkkel ikke konfigurert');
    }
    
    // Hent søkeparameter
    $query = isset($_GET['query']) ? trim($_GET['query']) : '';
    $action = isset($_GET['action']) ? $_GET['action'] : 'search';
    
    if (empty($query) && $action === 'search') {
        http_response_code(400);
        echo json_encode(['error' => 'Søkeparameter mangler'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Håndter forskjellige handlinger
    if ($action === 'search') {
        // Søk etter både filmer og TV-serier
        $movieUrl = TMDB_BASE_URL . '/search/movie?' . http_build_query([
            'api_key' => TMDB_API_KEY,
            'language' => 'no-NO',
            'query' => $query,
            'page' => 1
        ]);
        
        $tvUrl = TMDB_BASE_URL . '/search/tv?' . http_build_query([
            'api_key' => TMDB_API_KEY,
            'language' => 'no-NO',
            'query' => $query,
            'page' => 1
        ]);
        
        // Hent filmer
        $movieResults = makeRequest($movieUrl);
        $moviesData = json_decode($movieResults, true);
        
        // Hent TV-serier
        $tvResults = makeRequest($tvUrl);
        $tvData = json_decode($tvResults, true);
        
        // Kombiner resultater
        $combined = [];
        
        if (isset($moviesData['results'])) {
            foreach ($moviesData['results'] as $movie) {
                $movie['media_type'] = 'movie';
                $combined[] = $movie;
            }
        }
        
        if (isset($tvData['results'])) {
            foreach ($tvData['results'] as $tv) {
                $tv['media_type'] = 'tv';
                $combined[] = $tv;
            }
        }
        
        // Sorter etter popularitet
        usort($combined, function($a, $b) {
            return ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0);
        });
        
        echo json_encode(['results' => $combined], JSON_UNESCAPED_UNICODE);
        
    } elseif ($action === 'details') {
        // Hent detaljer inkludert IMDB-ID
        $id = isset($_GET['id']) ? $_GET['id'] : '';
        $type = isset($_GET['type']) ? $_GET['type'] : 'movie';
        
        if (empty($id)) {
            throw new Exception('ID mangler');
        }
        
        $detailsUrl = TMDB_BASE_URL . '/' . $type . '/' . $id . '?' . http_build_query([
            'api_key' => TMDB_API_KEY,
            'language' => 'no-NO',
            'append_to_response' => 'external_ids'
        ]);
        
        $response = makeRequest($detailsUrl);
        echo $response;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

function makeRequest($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        throw new Exception('cURL feil: ' . $curlError);
    }
    
    if ($httpCode !== 200) {
        throw new Exception('TMDB API returnerte feil: ' . $httpCode);
    }
    
    return $response;
}
?>
