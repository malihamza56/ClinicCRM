<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Records sign-in attempts so repeated failures can be throttled. */
final class LoginAttempt
{
    public static function record(string $email, string $ip, bool $succeeded): void
    {
        Database::run(
            'INSERT INTO login_attempts (email, ip_address, succeeded) VALUES (:e, :ip, :s)',
            ['e' => mb_substr($email, 0, 190), 'ip' => $ip, 's' => (int) $succeeded]
        );
    }

    /** Failures for this email+IP since the last success, inside the window. */
    public static function failuresForPair(string $email, string $ip, int $windowMinutes): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = :e AND ip_address = :ip AND succeeded = 0
               AND attempted_at > DATE_SUB(NOW(), INTERVAL ' . $windowMinutes . ' MINUTE)
               AND attempted_at > COALESCE((SELECT MAX(attempted_at) FROM login_attempts WHERE email = :e2 AND ip_address = :ip2 AND succeeded = 1), \'1970-01-01\')',
            ['e' => $email, 'ip' => $ip, 'e2' => $email, 'ip2' => $ip]
        );
    }

    public static function failuresForIp(string $ip, int $windowMinutes): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE ip_address = :ip AND succeeded = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL ' . $windowMinutes . ' MINUTE)',
            ['ip' => $ip]
        );
    }

    /** Seconds until the oldest counted failure leaves the window (i.e. until one more attempt is allowed). */
    public static function secondsUntilRelief(string $email, string $ip, int $windowMinutes): int
    {
        $seconds = Database::value(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(MIN(attempted_at), INTERVAL ' . $windowMinutes . ' MINUTE))
             FROM login_attempts
             WHERE email = :e AND ip_address = :ip AND succeeded = 0
               AND attempted_at > DATE_SUB(NOW(), INTERVAL ' . $windowMinutes . ' MINUTE)',
            ['e' => $email, 'ip' => $ip]
        );

        return max(0, (int) $seconds);
    }

    public static function purgeOlderThanDays(int $days): void
    {
        Database::run('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)');
    }
}
