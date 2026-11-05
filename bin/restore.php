<?php
declare(strict_types=1);

/*
 * Restores a backup created by bin/backup.php.
 *
 *   php bin/restore.php storage/backups/clinic_crm-20261031-101500.sql
 *   php bin/restore.php backup.sql --db=clinic_crm_copy      (restore into another database)
 *   php bin/restore.php backup.sql --force                   (replace an existing, non-empty database)
 *
 * Restoring into a database that already holds data would mix old and new rows, so the script refuses
 * unless the target is empty or you pass --force (which DROPS the database first, after you type its name).
 */

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Config;
use App\Core\Env;

/** Prints an error to STDERR and stops with a non-zero exit code, so shell scripts can detect the failure. */
function fail(string $message): never
{
    fwrite(STDERR, rtrim($message) . PHP_EOL);
    exit(1);
}

if (PHP_SAPI !== 'cli') {
    fail('Run this script from the command line.');
}
$args = array_slice($argv, 1);
$file = $args[0] ?? '';
if ($file === '' || !is_file($file)) {
    fail("Usage: php bin/restore.php <backup.sql> [--db=name] [--force]\n");
}
$options = [];
foreach ($args as $arg) {
    if (preg_match('/^--(\w+)=(.+)$/', $arg, $m) === 1) {
        $options[$m[1]] = $m[2];
    }
}
$force = in_array('--force', $args, true);

$db = Config::get('database');
$name = $options['db'] ?? (string) $db['name'];
if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
    fail("Invalid database name.\n");
}

$server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']), $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$exists = (int) $server->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = " . $server->quote($name))->fetchColumn() > 0;
$tables = $exists ? (int) $server->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $server->quote($name))->fetchColumn() : 0;

if ($tables > 0) {
    if (!$force) {
        fail("The database \"$name\" already contains $tables tables. Use --force to replace it, or --db=other_name to restore elsewhere.\n");
    }
    echo "This will DELETE the database \"$name\" and replace it with the backup. Type the database name to confirm: ";
    if (trim((string) fgets(STDIN)) !== $name) {
        fail("Cancelled.");
    }
    $server->exec("DROP DATABASE `$name`");
    $exists = false;
}
if (!$exists) {
    $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
}

$binary = Env::get('MYSQL_PATH');
if ($binary === null || $binary === '') {
    $xampp = 'C:\\xampp\\mysql\\bin\\mysql.exe';
    $binary = DIRECTORY_SEPARATOR === '\\' && is_file($xampp) ? $xampp : 'mysql';
}
$process = proc_open(
    [$binary, '--host=' . $db['host'], '--port=' . $db['port'], '--user=' . $db['user'], '--default-character-set=utf8mb4', $name],
    [0 => ['file', $file, 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    null,
    array_merge(getenv(), ['MYSQL_PWD' => (string) $db['pass']])
);
if (!is_resource($process)) {
    fail("Could not start the mysql client. Is it on your PATH (or set MYSQL_PATH in .env)?\n");
}
stream_get_contents($pipes[1]);
$errors = stream_get_contents($pipes[2]);
if (proc_close($process) !== 0) {
    fwrite(STDERR, "Restore failed: " . trim($errors) . "\n");
    exit(1);
}
echo "Restored \"$file\" into database \"$name\".\n";
