<?php

declare(strict_types=1);

// POST /import/physical-collection er et skrive-endepunkt og krever nå en
// innlogget bruker (JWT) - se app/security.py::get_current_user. Bruker
// samme PHP-sesjon som website_template_example/v18 (samme domene).
require_once __DIR__ . '/../../website_template_example/v18/auth.php';

// Kaller backend internt (server-til-server), ikke via det offentlige
// domenet - unngår en unødvendig runde ut på internett og en ekstern
// avhengighet til domenenavnet. Base-URL-en er konfigurerbar via
// MEDIA_API_BASE_URL i .env (samme mønster som INTERNAL_API_KEY under),
// slik at prosjektet kan flyttes til en annen server/vert uten å måtte
// endre denne filen - se frontend/.env.example.
require_once __DIR__ . '/../../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();

$mediaApiBaseUrl = $_ENV['MEDIA_API_BASE_URL'] ?? '';
if (!$mediaApiBaseUrl) {
    throw new Exception('MEDIA_API_BASE_URL er ikke satt i .env-filen');
}

$apiUrl = rtrim($mediaApiBaseUrl, '/') . '/import/physical-collection';

header('Content-Type: application/json');

require_login_or_json_401();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');

if ($rawBody === false || trim($rawBody) === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Request body is required']);
    exit;
}

json_decode($rawBody, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL extension is not available']);
    exit;
}

$ch = curl_init($apiUrl);

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        auth_bearer_header(),
    ],
    CURLOPT_POSTFIELDS => $rawBody,
    CURLOPT_TIMEOUT => 30,
]);

$responseBody = curl_exec($ch);

if ($responseBody === false) {
    $errorMessage = curl_error($ch);
    curl_close($ch);

    http_response_code(502);
    echo json_encode(['error' => $errorMessage !== '' ? $errorMessage : 'Upstream request failed']);
    exit;
}

$statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if (is_string($contentType) && $contentType !== '') {
    header('Content-Type: ' . $contentType);
}

http_response_code($statusCode > 0 ? $statusCode : 502);
echo $responseBody;
