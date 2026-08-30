<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

const INTERNAL_API_BASE_URL = 'http://172.19.0.1:9500';

/**
 * Utfører en GET mot TVDB v4 med Bearer-token, og prøver på nytt én gang
 * med tvunget token-refresh dersom TVDB svarer 401 (utløpt/ugyldig token).
 *
 * @return array{0: string, 1: int} [json-body, http-status]
 */
function makeTvdbRequest(string $url, bool $forceRefresh = false): array
{
    $token = getTvdbToken($forceRefresh);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return [json_encode(['error' => 'TVDB cURL-feil: ' . $err], JSON_UNESCAPED_UNICODE), 502];
    }

    if ($httpCode === 401 && !$forceRefresh) {
        return makeTvdbRequest($url, true);
    }

    return [$response, $httpCode];
}

try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/config_tvdb.php';

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? ($method === 'POST' ? 'submit' : 'search');

    if ($method === 'POST' && $action === 'submit') {
        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || trim($rawBody) === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Tom payload'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            http_response_code(400);
            echo json_encode(['error' => 'Payload må være gyldig JSON'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        [$response, $httpCode] = makeRequest(
            INTERNAL_API_BASE_URL . '/import/physical-collection',
            'POST',
            ['Content-Type: application/json'],
            json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        http_response_code($httpCode);
        echo $response;
        exit;
    }

    // TVDB-søk: kun brukt til å hente en tvdb_id (bulk_add_movies_form
    // henter selv IKKE fulle detaljer - det gjør backend server-side,
    // se PATCH /media/external-source/tvdb/{id} i media_catalog_route.py).
    if ($action === 'search_tvdb') {
        $tvdbQuery = isset($_GET['query']) ? trim((string) $_GET['query']) : '';
        if ($tvdbQuery === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Søkeparameter mangler'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $tvdbUrl = TVDB_BASE_URL . '/search?' . http_build_query([
            'query' => $tvdbQuery,
            'type'  => 'movie', // bulk_add_movies_form håndterer kun filmer
            'limit' => 50,
        ]);

        [$tvdbJson, $tvdbCode] = makeTvdbRequest($tvdbUrl);

        http_response_code($tvdbCode);
        echo $tvdbJson;
        exit;
    }

    if (!defined('TMDB_API_KEY') || empty(TMDB_API_KEY)) {
        throw new RuntimeException('TMDB_API_KEY er ikke konfigurert. Sjekk .env og config.php');
    }

    $query = isset($_GET['query']) ? trim((string) $_GET['query']) : '';

    if ($action === 'search') {
        if ($query === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Søkeparameter mangler'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $year = null;
        $searchQuery = $query;

        if (preg_match('/\b(19\d{2}|20\d{2})\b/', $query, $matches)) {
            $year = $matches[1];
            $searchQuery = trim((string) preg_replace('/\b(19\d{2}|20\d{2})\b/', '', $query));
        }

        $movieParams = [
            'api_key' => TMDB_API_KEY,
            'language' => 'no-NO',
            'query' => $searchQuery,
            'page' => 1,
        ];
        if ($year !== null) {
            $movieParams['year'] = $year;
        }

        $tvParams = [
            'api_key' => TMDB_API_KEY,
            'language' => 'no-NO',
            'query' => $searchQuery,
            'page' => 1,
        ];
        if ($year !== null) {
            $tvParams['first_air_date_year'] = $year;
        }

        [$movieJson, $movieCode] = makeRequest(TMDB_BASE_URL . '/search/movie?' . http_build_query($movieParams));
        [$tvJson, $tvCode] = makeRequest(TMDB_BASE_URL . '/search/tv?' . http_build_query($tvParams));

        if ($movieCode !== 200 || $tvCode !== 200) {
            throw new RuntimeException('TMDB svarte med uventet statuskode');
        }

        $movieData = json_decode($movieJson, true);
        $tvData = json_decode($tvJson, true);

        if (!is_array($movieData) || !is_array($tvData)) {
            throw new RuntimeException('Kunne ikke parse JSON fra TMDB');
        }

        $combined = [];

        if (!empty($movieData['results']) && is_array($movieData['results'])) {
            foreach ($movieData['results'] as $movie) {
                if (!is_array($movie)) {
                    continue;
                }
                $movie['media_type'] = 'movie';
                $combined[] = $movie;
            }
        }

        if (!empty($tvData['results']) && is_array($tvData['results'])) {
            foreach ($tvData['results'] as $tv) {
                if (!is_array($tv)) {
                    continue;
                }
                $tv['media_type'] = 'tv';
                $combined[] = $tv;
            }
        }

        usort($combined, static function (array $a, array $b): int {
            return ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0);
        });

        echo json_encode([
            'results' => $combined,
            'search_year' => $year,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'details') {
        $id = $_GET['id'] ?? '';
        $type = $_GET['type'] ?? 'movie';

        if ($id === '') {
            http_response_code(400);
            echo json_encode(['error' => 'ID mangler'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($type !== 'movie' && $type !== 'tv') {
            http_response_code(400);
            echo json_encode(['error' => 'Ugyldig type, må være movie eller tv'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $detailsUrl = TMDB_BASE_URL . '/' . $type . '/' . rawurlencode((string) $id) . '?' . http_build_query([
            'api_key' => TMDB_API_KEY,
            'language' => 'no-NO',
            'append_to_response' => 'external_ids',
        ]);

        [$json, $httpCode] = makeRequest($detailsUrl);
        http_response_code($httpCode);
        echo $json;
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Ugyldig action-parameter'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function makeRequest(string $url, string $method = 'GET', array $headers = [], ?string $body = null): array
{
    $ch = curl_init($url);

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $method,
    ];

    if ($headers !== []) {
        $options[CURLOPT_HTTPHEADER] = $headers;
    }

    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('cURL-feil: ' . $curlError . ' (URL: ' . $url . ')');
    }

    return [$response, $httpCode > 0 ? $httpCode : 502];
}
