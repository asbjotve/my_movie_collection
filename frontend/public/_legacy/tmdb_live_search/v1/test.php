<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing .env oppsett</h2>";

// Sjekk om vendor/autoload.php finnes
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "✅ vendor/autoload.php finnes<br>";
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    die("❌ vendor/autoload.php finnes IKKE. Kjør 'composer install'<br>");
}

// Sjekk om .env finnes
if (file_exists(__DIR__ . '/../.env')) {
    echo "✅ .env fil finnes<br>";
} else {
    die("❌ .env fil finnes IKKE<br>");
}

// Last .env
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
    echo "✅ .env lastet<br>";
} catch (Exception $e) {
    die("❌ Feil ved lasting av .env: " . $e->getMessage() . "<br>");
}

// Sjekk API-nøkkel
if (isset($_ENV['TMDB_API_KEY']) && !empty($_ENV['TMDB_API_KEY'])) {
    echo "✅ TMDB_API_KEY er satt: " . substr($_ENV['TMDB_API_KEY'], 0, 10) . "...<br>";
} else {
    die("❌ TMDB_API_KEY er IKKE satt i .env<br>");
}

echo "<br><strong>Alt ser bra ut! 🎉</strong>";
?>
