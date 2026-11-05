<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Clinic-wide settings stored as key/value rows (currency, opening hours, tax rate ...). */
final class Setting
{
    private const CLINIC_ID = 1;

    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public static function get(string $key, string $default = ''): string
    {
        return self::all()[$key] ?? $default;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::all('SELECT setting_key, setting_value FROM settings WHERE clinic_id = :c', ['c' => self::CLINIC_ID]) as $row) {
                self::$cache[$row['setting_key']] = (string) $row['setting_value'];
            }
        }

        return self::$cache;
    }

    public static function set(string $key, string $value): void
    {
        Database::run(
            'INSERT INTO settings (clinic_id, setting_key, setting_value) VALUES (:c, :k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['c' => self::CLINIC_ID, 'k' => $key, 'v' => $value]
        );
        self::forget();
    }

    public static function forget(): void
    {
        self::$cache = null;
    }
}
