<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Reads the project's ".env" file.
 *
 * PHP does not load ".env" files by itself, so this tiny class does it:
 * every "KEY=value" line becomes available through Env::get().
 * Real server environment variables win over the file, which is convenient in hosting panels.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];

    /** @var array<string,string> Values set from code (used by the test runner). */
    private static array $overrides = [];

    public static function load(string $file): void
    {
        self::$values = [];
        if (!is_file($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            self::$values[trim($key)] = self::unquote(trim($value));
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$overrides)) {
            return self::$overrides[$key];
        }
        $fromServer = getenv($key);
        if ($fromServer !== false) {
            return $fromServer;
        }

        return self::$values[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public static function set(string $key, string $value): void
    {
        self::$overrides[$key] = $value;
    }

    private static function unquote(string $value): string
    {
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
