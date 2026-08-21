<?php
/**
 * api.php – proxy mot TMDB
 *
 * Endepunkter:
 *  - ?action=search&query=Titanic  (søker film + tv, støtter årstall i query)
 *  - ?action=details&id=123&type=movie|tv  (detaljer + external_ids)
 *
 * Kopiert/tilpasset fra bulk_add_movies_form (samme mønster).
 */

declare(strict_types=1);

// *** DEBUG-INNSTILLINGER ***
// Sett til 0 i produksjon hvis du ikke vil vise PHP-errors i output
error_reporting(E_ALL);
ini_set('display_errors', '0');

// HTTP-headere
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    // 1. Last config og API-nøkkel
    require_once __DIR__ . '/config.php';

    if (!defined('TMDB_API_KEY') || empty(TMDB_API_KEY)) {
        throw new RuntimeException('TMDB_API_KEY er ikke konfigurert. Sjekk .env og config.php');
    }

    // 2. Les query-parametere
    $action = $_GET['action'] ?? 'search';
    $query  = isset($_GET['query']) ? trim((string)$_GET['query']) : '';

    if ($action === 'search') {
        if ($query === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Søkeparameter mangler'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // --- Årstallslogikk: "Titanic 1997" -> søketekst + year=1997 ---
        $year        = null;
        $searchQuery = $query;

        if (preg_match('/\b(19\d{2}|20\d{2})\b/', $query, $matches)) {
            $year        = $matches[1];
            $searchQuery = trim(preg_replace('/\b(19\d{2}|20\d{2})\b/', '', $query));
        }

        // --- Bygg URL for filmsøk ---
        $movieParams = [
            'api_key'  => TMDB_API_KEY,
            'language' => 'no-NO',
            'query'    => $searchQuery,
            'page'     => 1,
        ];
        if ($year !== null) {
            $movieParams['year'] = $year;
        }
        $movieUrl = TMDB_BASE_URL . '/search/movie?' . http_build_query($movieParams);

        // --- Bygg URL for TV-søk ---
        $tvParams = [
            'api_key'  => TMDB_API_KEY,
            'language' => 'no-NO',
            'query'    => $searchQuery,
            'page'     => 1,
        ];
        if ($year !== null) {
            $tvParams['first_air_date_year'] = $year;
        }
        $tvUrl = TMDB_BASE_URL . '/search/tv?' . http_build_query($tvParams);

        // --- Kall TMDB ---
        $movieJson = makeRequest($movieUrl);
        $tvJson    = makeRequest($tvUrl);

        $movieData = json_decode($movieJson, true);
        $tvData    = json_decode($tvJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Kunne ikke parse JSON fra TMDB (film). Feil: ' . json_last_error_msg());
        }
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Kunne ikke parse JSON fra TMDB (tv). Feil: ' . json_last_error_msg());
        }

        // --- Kombiner resultater ---
        $combined = [];

        if (!empty($movieData['results']) && is_array($movieData['results'])) {
            foreach ($movieData['results'] as $m) {
                $m['media_type'] = 'movie';
                $combined[]      = $m;
            }
        }

        if (!empty($tvData['results']) && is_array($tvData['results'])) {
            foreach ($tvData['results'] as $t) {
                $t['media_type'] = 'tv';
                $combined[]      = $t;
            }
        }

        // Sorter etter popularitet
        usort($combined, static function (array $a, array $b): int {
            return ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0);
        });

        echo json_encode(
            [
                'results'     => $combined,
                'search_year' => $year,
            ],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    if ($action === 'details') {
        $id   = $_GET['id']   ?? '';
        $type = $_GET['type'] ?? 'movie'; // 'movie' eller 'tv'

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

        $detailsUrl = TMDB_BASE_URL . '/' . $type . '/' . rawurlencode($id) . '?' . http_build_query([
            'api_key'            => TMDB_API_KEY,
            'language'           => 'no-NO',
            'append_to_response' => 'external_ids',
        ]);

        $json = makeRequest($detailsUrl);

        // Vi antar at TMDB returnerer gyldig JSON her, men vi kan verifisere:
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Kunne ikke parse JSON fra TMDB (details). Feil: ' . json_last_error_msg());
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Hvis action er noe annet:
    http_response_code(400);
    echo json_encode(['error' => 'Ugyldig action-parameter'], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    // Global feilfanger – dette er det FE faktisk vil vise
    http_response_code(500);
    echo json_encode(
        [
            'error'   => $e->getMessage(),
            'file'    => basename($e->getFile()),
            'line'    => $e->getLine(),
            // debugfelt – kommenter ut i produksjon hvis du vil
            'trace'   => $e->getTraceAsString(),
        ],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/**
 * Utfører HTTP-kall mot TMDB og gir tydelige feil ved problemer.
 *
 * @throws RuntimeException
 */
function makeRequest(string $url): string
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('cURL-feil: ' . $curlError . ' (URL: ' . $url . ')');
    }

    if ($httpCode !== 200) {
        // Ta med bruddstykke av responsen for debugging
        $short = mb_substr($response, 0, 300);
        throw new RuntimeException(
            'TMDB API-feil (' . $httpCode . '). URL: ' . $url . ' Respons: ' . $short
        );
    }

    return $response;
}
