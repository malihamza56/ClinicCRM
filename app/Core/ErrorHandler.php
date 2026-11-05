<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Last line of defence: turns any uncaught problem into a friendly page and a log line.
 * Details (stack traces) are only shown on screen when APP_DEBUG=true.
 */
final class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        set_exception_handler([self::class, 'handle']);
    }

    public static function handle(\Throwable $e): void
    {
        $status = match (true) {
            $e instanceof HttpException => $e->status,
            $e instanceof DatabaseUnavailableException => 503,
            default => 500,
        };

        if ($status >= 500) {
            self::log(sprintf('%s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
        }

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(1);
        }

        if (!headers_sent()) {
            http_response_code($status);
        }
        echo self::renderPage($e, $status);
    }

    public static function log(string $message): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir) || !is_writable($dir)) {
            error_log($message);

            return;
        }
        file_put_contents($dir . '/app.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    public static function renderPage(\Throwable $e, int $status): string
    {
        $debug = (bool) Config::get('app.debug', false);
        $detail = $debug ? $e->getMessage() . "\n\n" . $e->getTraceAsString() : null;

        $titles = [
            403 => 'Access denied',
            404 => 'Page not found',
            419 => 'Form expired',
            503 => 'Service unavailable',
        ];
        $messages = [
            503 => 'The clinic database is not reachable right now. Please check that MySQL is running and the .env settings are correct, then try again.',
        ];

        try {
            return View::render('errors/error', [
                'status'  => $status,
                'title'   => $titles[$status] ?? 'Something went wrong',
                'message' => $e instanceof HttpException ? $e->getMessage() : ($messages[$status] ?? 'An unexpected error occurred. The problem has been logged.'),
                'detail'  => $detail,
            ], 'layouts/plain');
        } catch (\Throwable) {
            return '<h1>Error ' . $status . '</h1><p>Something went wrong.</p>';
        }
    }
}
