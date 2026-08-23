<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php'; // juster hvis nødvendig

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();

$apiKey = $_ENV['TVDB_API_KEY'] ?? '';
$pin    = $_ENV['TVDB_PIN'] ?? '';

if (!$apiKey) {
    throw new RuntimeException('TVDB_API_KEY mangler i .env');
}
if (!$pin) {
    throw new RuntimeException('TVDB_PIN mangler i .env');
}

define('TVDB_BASE_URL', 'https://api4.thetvdb.com/v4');

/**
 * Logger inn mot TVDB og returnerer bearer-token.
 * Kalles typisk én gang per request (caches i static-variabel).
 */
function getTvdbToken(): string
{
    static $cachedToken = null;
    if ($cachedToken !== null) {
        return $cachedToken;
    }

    $apiKey = $_ENV['TVDB_API_KEY'] ?? '';
    $pin    = $_ENV['TVDB_PIN'] ?? '';

    $url = TVDB_BASE_URL . '/login';

    // Variant 1: bare apikey + pin, som v4-dok sier.
    $payload = [
        'apikey' => $apiKey,
        'pin'    => $pin,
    ];

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);

    if ($response === false) {
        throw new RuntimeException('TVDB login cURL-feil: ' . $err);
    }

    // For debugging: logg det vi sendte + det vi fikk
    // (fjern dette når du er fornøyd)
    // error_log('TVDB login request body: ' . $body);
    // error_log('TVDB login response: ' . $response);

    if ($httpCode !== 200) {
        throw new RuntimeException(
            'TVDB login feilet (' . $httpCode . '): ' . $response .
            ' | body: ' . $body
        );
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('TVDB login: ugyldig JSON: ' . json_last_error_msg());
    }

    $token = $data['data']['token'] ?? null;
    if (!$token) {
        throw new RuntimeException('TVDB login: token mangler i responsen: ' . $response);
    }

    $cachedToken = $token;
    return $token;
}

define('TVDB_TOKEN', getTvdbToken());
