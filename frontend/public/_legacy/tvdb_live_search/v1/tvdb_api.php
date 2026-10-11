<?php
declare(strict_types=1);

/**
 * tvdb_api.php
 *
 * Enkel backend-proxy mot TheTVDB API v4.
 * Brukes av tvdb_search.php via fetch:
 *   GET tvdb_api.php?action=search&query=The%20Matrix
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    // Viktig: sti må peke riktig i forhold til hvor denne fila ligger.
    // Juster om nødvendig.
    require_once __DIR__ . '/config_tvdb.php';

    $action = $_GET['action'] ?? 'search';

    if ($action === 'search') {
        $query = trim((string)($_GET['query'] ?? ''));

        if ($query === '') {
            http_response_code(400);
            echo json_encode(
                ['error' => 'Søketekst mangler'],
                JSON_UNESCAPED_UNICODE
            );
            exit;
        }

        // Bruker /search slik v4-dokumentasjonen beskriver.
        // Vi sender bare "query" og en fornuftig limit.
        // Hvis du vil snevre inn til bare "movie" eller "series",
        // kan du legge til 'type' => 'movie' osv.
        $url = TVDB_BASE_URL . '/search?' . http_build_query([
            'query' => $query,
            'limit' => 50,
        ]);

        $result = tvdbRequest($url);

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(
        ['error' => 'Ugyldig action-parameter'],
        JSON_UNESCAPED_UNICODE
    );
    exit;

} catch (Throwable $e) {
    // Global feilfanger – gir HTTP 500 og feilmelding i JSON
    http_response_code(500);
    echo json_encode(
        [
            'error' => $e->getMessage(),
            'file'  => basename($e->getFile()),
            'line'  => $e->getLine(),
        ],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/**
 * Utfører GET-kall mot TheTVDB v4 med bearer-token.
 * Returnerer dekodet JSON som array.
 *
 * @throws RuntimeException
 */
function tvdbRequest(string $url): array
{
    $token = TVDB_TOKEN; // definert i config_tvdb.php

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);

    if ($response === false) {
        throw new RuntimeException('cURL-feil mot TVDB: ' . $err);
    }

    if ($httpCode !== 200) {
        throw new RuntimeException(
            'TVDB API-feil (' . $httpCode . '): ' . mb_substr($response, 0, 500)
        );
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('TVDB API: ugyldig JSON: ' . json_last_error_msg());
    }

    return $data;
}
?>
