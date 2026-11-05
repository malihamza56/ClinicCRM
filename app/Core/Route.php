<?php
declare(strict_types=1);

namespace App\Core;

/** One entry in the route table: URL pattern -> controller method, plus who may use it. */
final class Route
{
    public bool $public = false;
    /** Any signed-in user may use the route (own password page, sign out). */
    public bool $anyAuthenticated = false;
    /** @var string[] Permission codes - the user needs at least one of them. */
    public array $permissions = [];

    /** @param array{0:class-string,1:string} $handler */
    public function __construct(
        public readonly string $method,
        public readonly string $pattern,
        public readonly array $handler,
    ) {
    }

    /** Marks the route as reachable without signing in (login page only). */
    public function public(): self
    {
        $this->public = true;

        return $this;
    }

    public function any(): self
    {
        $this->anyAuthenticated = true;

        return $this;
    }

    /** @param string|string[] $permissions */
    public function can(string|array $permissions): self
    {
        $this->permissions = (array) $permissions;

        return $this;
    }

    /** @return array<string,string>|null Named URL parameters when the path matches, otherwise null. */
    public function match(string $method, string $path): ?array
    {
        if ($method !== $this->method) {
            return null;
        }
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $this->pattern) . '$#';
        if (preg_match($regex, $path, $matches) !== 1) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
