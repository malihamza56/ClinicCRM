<?php
declare(strict_types=1);

/*
 * Front controller: the ONLY PHP file the web server runs directly.
 * Every request enters here, is matched to a route, checked (login, CSRF, permission) and answered.
 */

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Core\Request;
use App\Core\Router;
use App\Core\Session;

$request = Request::capture();

// Baseline browser protections. Scripts may only come from this site (no inline scripts), pages cannot be framed.
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store');
if ($request->isHttps()) {
    header('Strict-Transport-Security: max-age=31536000');
}

Session::start($request);

$router = new Router();
(require BASE_PATH . '/routes/web.php')($router);

$router->dispatch($request)->send();
