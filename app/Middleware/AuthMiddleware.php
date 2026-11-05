<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Route;
use App\Core\Session;

/** Sends visitors who are not signed in to the login page, and forces a password change when required. */
final class AuthMiddleware
{
    private const ALLOWED_WHEN_PASSWORD_CHANGE_PENDING = ['/account/password', '/logout'];

    public function handle(Request $request, Route $route): ?Response
    {
        if ($route->public) {
            return null;
        }

        $user = Auth::user();
        if ($user === null) {
            if ($request->method === 'GET') {
                Session::put('intended_path', $request->path);
            }

            return Response::redirect('/login');
        }

        if ((bool) $user['must_change_password'] && !in_array($request->path, self::ALLOWED_WHEN_PASSWORD_CHANGE_PENDING, true)) {
            Session::flash('warning', 'Please choose a new password before continuing.');

            return Response::redirect('/account/password');
        }

        return null;
    }
}
