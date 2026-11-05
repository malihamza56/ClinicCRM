<?php
declare(strict_types=1);

/*
 * Test runner:   php tests/run.php [--unit] [--integration] [--http]
 *
 * SAFETY: integration and HTTP tests use a SEPARATE database (DB_TEST_NAME in .env, default "clinic_crm_test").
 * That database is DROPPED and recreated on every run. The runner refuses to start if it is the same
 * as the real DB_NAME.
 */

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\SqlScriptRunner;
use App\Models\Setting;
use Tests\Support\Tester;

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Tests\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 6)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

error_reporting(E_ALL);
$only = array_values(array_intersect(['--unit', '--integration', '--http'], array_slice($argv, 1)));
$runUnit = $only === [] || in_array('--unit', $only, true);
$runIntegration = $only === [] || in_array('--integration', $only, true);
$runHttp = $only === [] || in_array('--http', $only, true);

$tester = new Tester();
$load = static function (string $dir) use ($tester): void {
    foreach (glob(__DIR__ . "/$dir/*Test.php") ?: [] as $file) {
        (require $file)($tester);
    }
};

if ($runUnit) {
    $load('Unit');
}

if ($runIntegration || $runHttp) {
    $realName = (string) Config::get('database.name');
    $testName = (string) (Env::get('DB_TEST_NAME') ?? 'clinic_crm_test');
    if ($testName === $realName || !preg_match('/^[A-Za-z0-9_]+$/', $testName)) {
        fwrite(STDERR, "Refusing to run: DB_TEST_NAME must be different from DB_NAME and contain only letters, numbers and underscores.\n");
        exit(2);
    }
    $db = Config::get('database');
    try {
        $server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']), $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        fwrite(STDERR, "Cannot connect to MySQL (" . $e->getMessage() . "). Start MySQL and check .env.\n");
        exit(2);
    }
    $server->exec("DROP DATABASE IF EXISTS `$testName`");
    $server->exec("CREATE DATABASE `$testName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $server->exec("USE `$testName`");
    SqlScriptRunner::run($server, BASE_PATH . '/database/schema.sql');

    Config::set('database.name', $testName);
    Database::disconnect();
}

if ($runIntegration) {
    $beforeEach = static function (): void {
        Database::pdo()->beginTransaction();
        Auth::reset();
        Setting::forget();
    };
    $afterEach = static function (): void {
        if (Database::pdo()->inTransaction()) {
            Database::pdo()->rollBack();
        }
        Auth::reset();
        Setting::forget();
    };
    $GLOBALS['__test_hooks'] = [$beforeEach, $afterEach];
    $load('Integration');
}

$serverProcess = null;
if ($runHttp) {
    if (!function_exists('curl_init')) {
        echo "\nSkipping HTTP tests: the PHP curl extension is not enabled.\n";
    } else {
        $port = random_int(18000, 18999);
        $env = array_merge(getenv(), ['PHP_CLI_SERVER_WORKERS' => '6', 'DB_NAME' => $testName, 'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'LOGIN_MAX_ATTEMPTS' => '5', 'LOGIN_LOCKOUT_MINUTES' => '15']);
        $serverProcess = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', BASE_PATH . '/public', BASE_PATH . '/public/router.php'],
            [0 => ['pipe', 'r'], 1 => ['file', sys_get_temp_dir() . '/crm-test-server.log', 'a'], 2 => ['file', sys_get_temp_dir() . '/crm-test-server.log', 'a']],
            $pipes,
            BASE_PATH,
            $env
        );
        $ready = false;
        for ($i = 0; $i < 50 && !$ready; $i++) {
            usleep(100000);
            $ready = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2) !== false;
        }
        if (!$ready) {
            fwrite(STDERR, "Could not start the PHP test server.\n");
            exit(2);
        }
        $GLOBALS['__test_base_url'] = "http://127.0.0.1:$port";
        $load('Http');
        proc_terminate($serverProcess);
    }
}

echo "\n" . str_repeat('-', 60) . "\n";
echo "Passed: {$tester->passed}   Failed: " . count($tester->failures) . "\n";
foreach ($tester->failures as $failure) {
    echo "\033[31mFAILED\033[0m $failure\n";
}
exit($tester->failures === [] ? 0 : 1);
