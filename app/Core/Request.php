<?php
declare(strict_types=1);

namespace App\Core;

/** A read-only snapshot of the incoming HTTP request. */
final class Request
{
    private static string $basePath = '';

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $server = [],
    ) {
    }

    public static function capture(): self
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(dirname($script), '/');
        self::$basePath = ($dir === '.' || $dir === '\\') ? '' : $dir;

        $uri = rawurldecode((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'));
        if (self::$basePath !== '' && str_starts_with($uri, self::$basePath)) {
            $uri = substr($uri, strlen(self::$basePath));
        }
        $path = '/' . trim($uri, '/');

        return new self(strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')), $path, $_GET, $_POST, $_SERVER);
    }

    /** URL prefix of the app when it lives in a sub-folder (e.g. "/Clinic%20CRM/public"); empty at a domain root. */
    public static function basePath(): string
    {
        return self::$basePath;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    /** A trimmed string input ('' when missing or not a string). */
    public function string(string $key): string
    {
        $value = $this->input($key, '');

        return is_string($value) ? trim($value) : '';
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_string($value) && preg_match('/^\d+$/', $value) === 1 ? (int) $value : $default;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /** @return array<string,mixed> POST body only. */
    public function body(): array
    {
        return $this->body;
    }

    public function query(): array
    {
        return $this->query;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return isset($this->server[$key]) ? (string) $this->server[$key] : null;
    }

    /** Only REMOTE_ADDR is trusted: forwarded headers can be forged by the client. */
    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function isHttps(): bool
    {
        $https = $this->server['HTTPS'] ?? '';

        return $https !== '' && strtolower((string) $https) !== 'off';
    }
}
