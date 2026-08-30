<?php
declare(strict_types=1);

/**
 * config_tvdb.php - TVDB-oppsett for bulk_add_movies_form v14.
 *
 * Speiler frontend/public/tvdb_live_search/v4/config_tvdb.php (samme
 * token-cache-fil i /tmp, siden det er samme TVDB-konto - da slipper
 * vi doble innlogginger på tvers av funksjonene i appen).
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

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

if (!defined('TVDB_BASE_URL')) {
    define('TVDB_BASE_URL', 'https://api4.thetvdb.com/v4');
}

// Samme cache-fil som tvdb_live_search/v4 bruker - webroot er ikke
// skrivbar for www-data, derfor /tmp.
const TVDB_TOKEN_CACHE_FILE = '/tmp/mmc_tvdb_v4_token_cache.json';
const TVDB_TOKEN_CACHE_TTL_SECONDS = 20 * 60 * 60;

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

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => TVDB_BASE_URL . '/login',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode(['apikey' => $apiKey, 'pin' => $pin], JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT        => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);

    if ($response === false) {
        throw new RuntimeException('TVDB login cURL-feil: ' . $err);
    }
    if ($httpCode !== 200) {
        throw new RuntimeException('TVDB login feilet (' . $httpCode . '): ' . $response);
    }

    $data = json_decode($response, true);
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
