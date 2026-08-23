<?php
declare(strict_types=1);

/**
 * config.php – database-tilkobling for website_template_example v17.
 *
 * ============================================================
 *  VIL DU ENDRE TILKOBLINGSDETALJER (host/bruker/passord/db-navn)?
 *  Rediger MEDIA_DB_* i .env-filen (frontend/.env), IKKE her.
 *  Se frontend/.env.example for hvilke nøkler som finnes.
 * ============================================================
 */

// Last inn Composer autoloader (samme mønster som custom_list_manager)
require_once __DIR__ . '/../../../vendor/autoload.php';

// Bruk vlucas/phpdotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();

define('MEDIA_DB_HOST', $_ENV['MEDIA_DB_HOST'] ?? '127.0.0.1');
define('MEDIA_DB_PORT', $_ENV['MEDIA_DB_PORT'] ?? '3306');
define('MEDIA_DB_NAME', $_ENV['MEDIA_DB_NAME'] ?? '');
define('MEDIA_DB_USER', $_ENV['MEDIA_DB_USER'] ?? '');
define('MEDIA_DB_PASSWORD', $_ENV['MEDIA_DB_PASSWORD'] ?? '');

if (!MEDIA_DB_NAME || !MEDIA_DB_USER) {
    throw new Exception('MEDIA_DB_* er ikke satt i .env-filen');
}

/**
 * Åpner en PDO-tilkobling til media-databasen (db_mediearkiv).
 * Kalles fra api.php - hold denne funksjonen enkel, slik at det er
 * lett å bytte ut/utvide spørringene i api.php senere.
 */
function get_media_db(): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        MEDIA_DB_HOST,
        MEDIA_DB_PORT,
        MEDIA_DB_NAME
    );

    return new PDO(
        $dsn,
        MEDIA_DB_USER,
        MEDIA_DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}
