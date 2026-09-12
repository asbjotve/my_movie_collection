<?php
declare(strict_types=1);

/**
 * api.php - tv_series_add_form v1
 *
 * Only supports one action so far:
 *   GET ?action=search_tvdb&query=...&type=series|movie
 *
 * Used by the "Søk TVDB" button next to the series fields (title/
 * imdb_id/tvdb_id). Pattern copied from bulk_add_movies_form/v14's
 * search_tvdb action - see that file for the original. TVDB v4's
 * /search endpoint already returns a "remote_ids" array per result
 * that includes the IMDb id (sourceName "IMDB") when TVDB has it
 * linked, so a single search covers both tvdb_id and imdb_id - no
 * separate "details" lookup is needed for this form's purposes.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/_shared/auth.php';
require_login_or_json_401();

require_once __DIR__ . '/config_tvdb.php';

/**
 * Performs a GET against TVDB v4 with a Bearer token, retrying once
 * with a forced token refresh if TVDB responds 401 (expired/invalid
 * token).
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
    $action = $_GET['action'] ?? 'search_tvdb';

    if ($action !== 'search_tvdb') {
        http_response_code(400);
        echo json_encode(['error' => 'Ugyldig action-parameter'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $query = isset($_GET['query']) ? trim((string) $_GET['query']) : '';
    if ($query === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Søkeparameter mangler'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $type = $_GET['type'] ?? 'series';
    if (!in_array($type, ['movie', 'series'], true)) {
        $type = 'series';
    }

    $url = TVDB_BASE_URL . '/search?' . http_build_query([
        'query' => $query,
        'type'  => $type,
        'limit' => 25,
    ]);

    [$json, $httpCode] = makeTvdbRequest($url);

    http_response_code($httpCode);
    echo $json;
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
