<?php
declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PermissionMiddleware;

/**
 * Matches the request to a route, runs the safety checks (middleware), then calls the controller.
 *
 * Request lifecycle:  public/index.php -> Router::dispatch ->
 *   AuthMiddleware (signed in?) -> CsrfMiddleware (POST token ok?) -> PermissionMiddleware (allowed?) -> Controller -> Response
 */
final class Router
{
    /** @var Route[] */
    private array $routes = [];

    /** @param array{0:class-string,1:string} $handler */
    public function get(string $pattern, array $handler): Route
    {
        return $this->add('GET', $pattern, $handler);
    }

    /** @param array{0:class-string,1:string} $handler */
    public function post(string $pattern, array $handler): Route
    {
        return $this->add('POST', $pattern, $handler);
    }

    /** @return Route[] */
    public function routes(): array
    {
        return $this->routes;
    }

    public function dispatch(Request $request): Response
    {
        $route = null;
        $params = [];
        foreach ($this->routes as $candidate) {
            $matched = $candidate->match($request->method, $request->path);
            if ($matched !== null) {
                $route = $candidate;
                $params = $matched;
                break;
            }
        }
        if ($route === null) {
            throw new HttpException(404);
        }

        // Order matters: sign-in first, then CSRF, then the role's permission.
        foreach ([new AuthMiddleware(), new CsrfMiddleware(), new PermissionMiddleware()] as $middleware) {
            $early = $middleware->handle($request, $route);
            if ($early !== null) {
                return $early;
            }
        }

        [$class, $method] = $route->handler;
        $controller = new $class($request);

        return $controller->$method(...array_map('intval', array_values($params)));
    }

    /** @param array{0:class-string,1:string} $handler */
    private function add(string $method, string $pattern, array $handler): Route
    {
        return $this->routes[] = new Route($method, $pattern, $handler);
    }
}
