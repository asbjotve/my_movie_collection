<?php
declare(strict_types=1);

/**
 * tvdb_api.php v2
 * Støtter:
 *   GET ?action=search&query=...&type=movie|series|both
 */

error_reporting(E_ALL);
ini_set('display_errors', '1'); // midlertidig på = 1 for debugging

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    require_once __DIR__ . '/config_tvdb.php';

    $action = $_GET['action'] ?? 'search';

    if ($action === 'search') {
        $query = trim((string)($_GET['query'] ?? ''));
        $type  = trim((string)($_GET['type'] ?? 'both')); // movie | series | both

        if ($query === '') {
            http_response_code(400);
            echo json_encode(
                ['error' => 'Søketekst mangler'],
                JSON_UNESCAPED_UNICODE
            );
            exit;
        }

        $params = [
            'query' => $query,
            'limit' => 50,
        ];

        if ($type === 'movie' || $type === 'series') {
            $params['type'] = $type;
        }

        $url = TVDB_BASE_URL . '/search?' . http_build_query($params);

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
    curl_close($ch);

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
