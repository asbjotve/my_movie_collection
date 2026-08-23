<?php
declare(strict_types=1);

/**
 * tvdb_api.php v4
 *
 * Støtter:
 *   GET ?action=search&query=...&type=movie|series
 *   GET ?action=details&type=movie|series&id=12345
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    require_once __DIR__ . '/config_tvdb.php';

    $action = $_GET['action'] ?? 'search';

    if ($action === 'search') {
        handleSearch();
        exit;
    }

    if ($action === 'details') {
        handleDetails();
        exit;
    }

    http_response_code(400);
    echo json_encode(
        ['error' => 'Ugyldig action-parameter'],
        JSON_UNESCAPED_UNICODE
    );
    exit;

} catch (Throwable $e) {
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
 * /search-wrapper
 */
function handleSearch(): void
{
    $query = trim((string)($_GET['query'] ?? ''));
    $type  = trim((string)($_GET['type'] ?? 'series')); // default series

    if ($query === '') {
        http_response_code(400);
        echo json_encode(
            ['error' => 'Søketekst mangler'],
            JSON_UNESCAPED_UNICODE
        );
        return;
    }

    if ($type !== 'movie' && $type !== 'series') {
        http_response_code(400);
        echo json_encode(
            ['error' => 'Ugyldig type. Må være "movie" eller "series".'],
            JSON_UNESCAPED_UNICODE
        );
        return;
    }

    $params = [
        'query' => $query,
        'limit' => 50,
        'type'  => $type,
    ];

    $url = TVDB_BASE_URL . '/search?' . http_build_query($params);

    $result = tvdbRequest($url);

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}

/**
 * /details-wrapper
 */
function handleDetails(): void
{
    $type = trim((string)($_GET['type'] ?? ''));
    $id   = (string)($_GET['id'] ?? '');

    if ($id === '' || !ctype_digit($id)) {
        http_response_code(400);
        echo json_encode(
            ['error' => 'Ugyldig eller manglende id'],
            JSON_UNESCAPED_UNICODE
        );
        return;
    }

    $idInt = (int)$id;

    if ($type === 'movie') {
        $url = TVDB_BASE_URL . '/movies/' . $idInt . '/extended?short=true';
    } elseif ($type === 'series') {
        $url = TVDB_BASE_URL . '/series/' . $idInt . '/extended?short=true';
    } else {
        http_response_code(400);
        echo json_encode(
            ['error' => 'Ugyldig type for details. Må være "movie" eller "series".'],
            JSON_UNESCAPED_UNICODE
        );
        return;
    }

    $result = tvdbRequest($url);

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
}

/**
 * Utfører GET-kall mot TVDB v4.
 */
function tvdbRequest(string $url): array
{
    $token = TVDB_TOKEN;

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
