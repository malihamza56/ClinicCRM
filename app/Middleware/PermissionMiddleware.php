<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Route;

/**
 * Server-side authorization. Hiding a menu link is only cosmetic - this is the real lock.
 * A route with no permission listed is a programming mistake, so it is denied rather than allowed.
 */
final class PermissionMiddleware
{
    public function handle(Request $request, Route $route): ?Response
    {
        if ($route->public || $route->anyAuthenticated) {
            return null;
        }
        if ($route->permissions === [] || !Auth::canAny(...$route->permissions)) {
            throw new HttpException(403);
        }

        return null;
    }
}
