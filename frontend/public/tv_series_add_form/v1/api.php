<?php
declare(strict_types=1);

/**
 * api.php - tv_series_add_form v1
 *
 * Supports:
 *   GET  ?action=search_tvdb&query=...&type=series|movie
 *   POST ?action=submit  (body: the tv_series_boxset JSON payload)
 *
 * search_tvdb is used by the "Søk TVDB" button next to the series
 * fields (title/imdb_id/tvdb_id). Pattern copied from
 * bulk_add_movies_form/v14's search_tvdb action - see that file for
 * the original. TVDB v4's /search endpoint already returns a
 * "remote_ids" array per result that includes the IMDb id
 * (sourceName "IMDB") when TVDB has it linked, so a single search
 * covers both tvdb_id and imdb_id - no separate "details" lookup is
 * needed for this form's purposes.
 *
 * submit forwards the built payload to POST /import/physical-
 * collection on the backend (same endpoint bulk_add_movies_form uses,
 * see app/schemas/physical_collection_import.py's tv_series_boxset
 * variant and app/services/add_data/physical_collection_import.py's
 * import_tv_series_boxset_payload()).
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

const INTERNAL_API_BASE_URL = 'http://172.19.0.1:9500';

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
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? ($method === 'POST' ? 'submit' : 'search_tvdb');

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

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => INTERNAL_API_BASE_URL . '/import/physical-collection',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', auth_bearer_header()],
            CURLOPT_POSTFIELDS     => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT        => 20,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log('tv_series_add_form submit cURL-feil: ' . $curlError);
            http_response_code(502);
            echo json_encode(['error' => 'Kunne ikke kontakte backend (nettverksfeil).'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code($httpCode > 0 ? $httpCode : 502);
        echo $response;
        exit;
    }

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
