<?php
// Last inn Composer autoloader
require_once __DIR__ . '/../../vendor/autoload.php';

// Bruk vlucas/phpdotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Hent API-nøkkel
define('TMDB_API_KEY', $_ENV['TMDB_API_KEY']);
define('TMDB_BASE_URL', 'https://api.themoviedb.org/3');

// Valider at API-nøkkelen er satt
if (!TMDB_API_KEY) {
    throw new Exception('TMDB_API_KEY er ikke satt i .env filen');
}
?>
