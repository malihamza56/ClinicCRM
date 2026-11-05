<?php
declare(strict_types=1);

/*
 * Bootstrap: runs once per request (and once per CLI script / test run).
 * It wires up autoloading, environment values, configuration and error handling.
 * Nothing here starts a session or touches the database - that happens later, only when needed.
 */

define('BASE_PATH', dirname(__DIR__));

// Tiny PSR-4 style autoloader: App\Services\Foo  ->  app/Services/Foo.php
// Composer is optional, so a student can run the project without installing anything.
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once BASE_PATH . '/app/Helpers/helpers.php';

App\Core\Env::load(BASE_PATH . '/.env');
App\Core\Config::load(BASE_PATH . '/config');

date_default_timezone_set((string) App\Core\Config::get('app.timezone'));
mb_internal_encoding('UTF-8');

App\Core\ErrorHandler::register();
