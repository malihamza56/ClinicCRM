<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Thin wrapper around PHP sessions.
 * "Flash" values live for exactly one following request (used for success/error banners and old form input).
 */
final class Session
{
    public static function start(Request $request): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secureSetting = (string) Config::get('security.cookie_secure', 'auto');
        $secure = $secureSetting === 'auto' ? $request->isHttps() : in_array(strtolower($secureSetting), ['1', 'true', 'yes'], true);

        ini_set('session.use_strict_mode', '1');   // refuse session IDs the server did not create
        ini_set('session.use_only_cookies', '1');  // never accept IDs from the URL
        session_name((string) Config::get('security.session_name'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,       // JavaScript cannot read the cookie
            'samesite' => 'Lax',      // not sent on cross-site POSTs (extra CSRF defence)
        ]);
        session_start();

        // Flash data written during the previous request becomes readable now, and is cleared afterwards.
        $_SESSION['_flash_now'] = $_SESSION['_flash_next'] ?? [];
        $_SESSION['_flash_next'] = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_next'][$key] = $value;
    }

    public static function flashed(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash_now'][$key] ?? $default;
    }

    /** New session ID, same data. Called after login to prevent session fixation. */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Wipes the session data and issues a fresh, empty session. */
    public static function reset(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['_flash_now'] = [];
        $_SESSION['_flash_next'] = [];
    }
}
