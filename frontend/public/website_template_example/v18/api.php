<?php
declare(strict_types=1);

/**
 * api.php – tynn proxy mot FastAPI-backend (v18).
 *
 * ============================================================
 *  ARKITEKTUR-ENDRING FRA v15/v17
 *  v15/v17 kobler PHP direkte til MySQL (se deres config.php/api.php).
 *  Her i v18 gjør PHP ingen SQL selv - all databaselogikk bor nå i
 *  backend (FastAPI), i:
 *      backend/app/services/media_catalog.py
 *      backend/app/routes/media_catalog_route.py  (GET /media/content)
 *  Denne filen henter bare JSON fra det endepunktet server-side (så
 *  nettleseren slipper CORS, siden alt fortsatt serveres fra samme
 *  origin: mmc.plexcity.net) og sender det videre uendret til JS.
 *
 *  VIL DU ENDRE HVILKE FELTER/DATA SOM HENTES?
 *  Gjør det i backend (media_catalog.py), IKKE her.
 * ============================================================
 */

header('Content-Type: application/json; charset=utf-8');

// Intern adresse til FastAPI/uvicorn (samme vert som resten av prosjektet bruker).
const MEDIA_API_BASE_URL = 'http://172.19.0.1:9500';

// Uten ?id=... hentes hele listen (GET /media/content), som før.
// Med ?id=<hex content_id> hentes én enkelt rad for detaljsiden
// (GET /media/content/{id}), brukt av detail.php.
$contentId = $_GET['id'] ?? null;
if ($contentId !== null && !preg_match('/^[0-9a-fA-F]{32}$/', $contentId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Ugyldig id-parameter']);
    exit;
}

$url = $contentId !== null
    ? MEDIA_API_BASE_URL . '/media/content/' . $contentId
    : MEDIA_API_BASE_URL . '/media/content';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => 'Kunne ikke nå API-et: ' . $curlError]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode ?: 502);
    echo json_encode(['error' => 'API-et svarte med feilkode ' . $httpCode, 'body' => $response]);
    exit;
}

// Send JSON-responsen fra API-et videre uendret.
echo $response;
