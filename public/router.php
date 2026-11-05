<?php
declare(strict_types=1);

/*
 * Router script for PHP's built-in development server only:
 *   php -S localhost:8000 -t public public/router.php
 * It lets the built-in server serve real files (CSS, JS, images) and send everything else to index.php,
 * which is what Apache does with public/.htaccess.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}
require __DIR__ . '/index.php';
