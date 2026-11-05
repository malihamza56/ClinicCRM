<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Route;

/** Rejects every POST that does not carry the secret form token. */
final class CsrfMiddleware
{
    public function handle(Request $request, Route $route): ?Response
    {
        if ($request->isPost() && !Csrf::verify($request)) {
            throw new HttpException(419);
        }

        return null;
    }
}
