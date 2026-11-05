<?php
declare(strict_types=1);

/*
 * Creates a SQL backup of the clinic database.
 *
 *   php bin/backup.php                 -> storage/backups/<db>-YYYYMMDD-HHMMSS.sql
 *   php bin/backup.php --out=my.sql    -> custom file name
 *   php bin/backup.php --db=other_db   -> another database (default: DB_NAME from .env)
 *
 * It uses the "mysqldump" program that ships with MySQL/MariaDB (XAMPP: C:\xampp\mysql\bin).
 * If mysqldump is not on your PATH, set MYSQLDUMP_PATH in .env to its full path.
 * The password is handed over through an environment variable, so it never appears in the process list or in logs.
 * Backups contain patient data: store them somewhere private and encrypted.
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

$options = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--(\w+)=(.+)$/', $arg, $m) === 1) {
        $options[$m[1]] = $m[2];
    }
}

$db = Config::get('database');
$name = $options['db'] ?? (string) $db['name'];
if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
    fail("Invalid database name.\n");
}
$file = $options['out'] ?? BASE_PATH . '/storage/backups/' . $name . '-' . date('Ymd-His') . '.sql';

$binary = Env::get('MYSQLDUMP_PATH');
if ($binary === null || $binary === '') {
    $xampp = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
    $binary = DIRECTORY_SEPARATOR === '\\' && is_file($xampp) ? $xampp : 'mysqldump';
}

$command = [
    $binary, '--host=' . $db['host'], '--port=' . $db['port'], '--user=' . $db['user'],
    '--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4',
    '--result-file=' . $file, $name,
];
$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv(), ['MYSQL_PWD' => (string) $db['pass']]));
if (!is_resource($process)) {
    fail("Could not start mysqldump. Is it installed and on your PATH (or set MYSQLDUMP_PATH in .env)?\n");
}
stream_get_contents($pipes[1]);
$errors = stream_get_contents($pipes[2]);
if (proc_close($process) !== 0 || !is_file($file) || filesize($file) === 0) {
    fwrite(STDERR, "Backup failed: " . trim($errors) . "\n");
    exit(1);
}

echo 'Backup written to ' . $file . ' (' . number_format((int) filesize($file)) . " bytes)\n";
