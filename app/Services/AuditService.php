<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\AuditLog;

/** Writes the audit trail. Never pass passwords, tokens or hashes - they are stripped as a second line of defence. */
final class AuditService
{
    private const SENSITIVE_KEYS = ['password', 'token', 'hash', 'secret'];

    public static function log(string $action, string $entityType, ?int $entityId, string $summary, ?array $details = null, ?int $userId = null): void
    {
        AuditLog::write(
            $userId ?? Auth::id(),
            $action,
            $entityType,
            $entityId,
            $summary,
            $details === null ? null : self::scrub($details),
            $_SERVER['REMOTE_ADDR'] ?? null
        );
    }

    /** @param array<string,mixed> $data */
    private static function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if (stripos((string) $key, $sensitive) !== false) {
                    unset($data[$key]);
                    continue 2;
                }
            }
            if (is_array($value)) {
                $data[$key] = self::scrub($value);
            }
        }

        return $data;
    }
}
