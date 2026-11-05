<?php
declare(strict_types=1);

/*
 * Command-line helper that builds the database for you.
 *
 *   php bin/install-db.php             create tables + reference data in an EMPTY database (reads .env)
 *   php bin/install-db.php --seed      the same, then load the fictional demo data (development only)
 *   php bin/install-db.php --fresh     DROP and recreate the database first (asks for confirmation)
 *
 * phpMyAdmin users can instead import database/schema.sql by hand; the demo data is loaded by this script (--seed).
 */

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Config;
use App\Core\SqlScriptRunner;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script from the command line.\n");
    exit(1);
}

$options = array_slice($argv, 1);
$fresh = in_array('--fresh', $options, true);
$seed = in_array('--seed', $options, true);

$db = Config::get('database');
$dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']);

try {
    $server = new PDO($dsn, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    fwrite(STDERR, "Cannot connect to MySQL: " . $e->getMessage() . "\nCheck that MySQL is running and DB_HOST / DB_USER / DB_PASS in .env are correct.\n");
    exit(1);
}

$name = (string) $db['name'];
if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
    fwrite(STDERR, "DB_NAME may contain only letters, numbers and underscores.\n");
    exit(1);
}

if ($fresh) {
    echo "This will DELETE the database \"$name\" and everything in it. Type the database name to confirm: ";
    if (trim((string) fgets(STDIN)) !== $name) {
        echo "Cancelled.\n";
        exit(1);
    }
    $server->exec("DROP DATABASE IF EXISTS `$name`");
}

$server->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$server->exec("USE `$name`");

$tables = (int) $server->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
if ($tables > 0) {
    fwrite(STDERR, "The database \"$name\" is not empty ($tables tables). Use --fresh to rebuild it, or choose an empty database.\n");
    exit(1);
}

$count = SqlScriptRunner::run($server, BASE_PATH . '/database/schema.sql');
echo "Schema installed ($count statements).\n";

if ($seed) {
    require BASE_PATH . '/database/seed.php';
    $made = seedDemoData();
    echo 'Demo data loaded: ' . json_encode($made) . "\nDEVELOPMENT ONLY - see docs/setup.md for the demo accounts.\n";
}
echo "Done.\n";
