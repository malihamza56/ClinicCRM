<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cross-Site Request Forgery protection.
 * Every form carries a secret token that only pages served by this app know.
 * A malicious site cannot read it, so a forged POST is rejected.
 */
final class Csrf
{
    public const FIELD = '_token';

    public static function token(): string
    {
        $token = Session::get('csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put('csrf_token', $token);
        }

        return $token;
    }

    public static function verify(Request $request): bool
    {
        $sent = $request->input(self::FIELD) ?? $request->header('X-CSRF-Token');
        $expected = Session::get('csrf_token');

        return is_string($sent) && is_string($expected) && $expected !== '' && hash_equals($expected, $sent);
    }

    public static function rotate(): void
    {
        Session::forget('csrf_token');
    }
}
