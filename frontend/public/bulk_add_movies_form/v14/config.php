<?php
// Last inn Composer autoloader
require_once __DIR__ . '/../../../vendor/autoload.php';

// Bruk vlucas/phpdotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();

// Hent API-nøkkel
define('TMDB_API_KEY', $_ENV['TMDB_API_KEY']);
define('TMDB_BASE_URL', 'https://api.themoviedb.org/3');

// Valider at API-nøkkelen er satt
if (!TMDB_API_KEY) {
    throw new Exception('TMDB_API_KEY er ikke satt i .env filen');
}

// Intern API-nøkkel (samme .env-verdi som backend sin INTERNAL_API_KEY -
// se app/api_key.py::require_api_key()), brukt for rene lese-kall mot
// FastAPI-backendet (f.eks. GET /media/owners for eier-autofullføring)
// som IKKE krever innlogget bruker/JWT, i motsetning til selve
// import-endepunktet (se "submit"-handlingen i api.php).
define('INTERNAL_API_KEY', $_ENV['INTERNAL_API_KEY'] ?? '');
?>
