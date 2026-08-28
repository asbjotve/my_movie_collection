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

// Filbasert cache for TVDB-token på tvers av forespørsler. TVDB v4-tokens er
// gyldige i ca. 1 måned, så det er unødvendig (og tregt) å logge inn på nytt
// for hvert eneste søk/detalj-kall. Uten denne cachen gjorde hvert eneste
// TVDB-kall (search/details) en full ekstra login-runde til TVDB først,
// noe som nesten doblet responstiden og gjorde søk i f.eks.
// custom_list_manager føles trege/upålitelige.
const TVDB_TOKEN_CACHE_FILE = '/tmp/mmc_tvdb_v4_token_cache.json'; // webroot er ikke skrivbar for www-data
const TVDB_TOKEN_CACHE_TTL_SECONDS = 20 * 60 * 60; // 20 timer - god margin under TVDBs ~1 måneds levetid.

/**
 * Logger inn mot TVDB og returnerer bearer-token.
 *
 * Cacher token i en fil på tvers av requests (se TVDB_TOKEN_CACHE_FILE).
 * Sett $forceRefresh=true for å ignorere cachen og hente en helt ny token
 * (brukes som fallback dersom et kall mot TVDB feiler med 401, altså at den
 * cachede tokenen har blitt ugyldig av en eller annen grunn).
 */
function getTvdbToken(bool $forceRefresh = false): string
{
    static $cachedToken = null;

    if (!$forceRefresh && $cachedToken !== null) {
        return $cachedToken;
    }

    if (!$forceRefresh && is_file(TVDB_TOKEN_CACHE_FILE)) {
        $cached = json_decode((string) file_get_contents(TVDB_TOKEN_CACHE_FILE), true);
        if (
            is_array($cached)
            && !empty($cached['token'])
            && !empty($cached['expires_at'])
            && (int) $cached['expires_at'] > time()
        ) {
            $cachedToken = (string) $cached['token'];
            return $cachedToken;
        }
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

    @file_put_contents(
        TVDB_TOKEN_CACHE_FILE,
        json_encode([
            'token'      => $token,
            'expires_at' => time() + TVDB_TOKEN_CACHE_TTL_SECONDS,
        ], JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );

    return $token;
}

define('TVDB_TOKEN', getTvdbToken());
