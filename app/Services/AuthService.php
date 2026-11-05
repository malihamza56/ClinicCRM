<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Config;
use App\Models\LoginAttempt;
use App\Models\User;

/** Sign-in, throttling and password rules. */
final class AuthService
{
    public const GENERIC_ERROR = 'The email or password is incorrect, or the account is not active.';

    private static ?string $dummyHash = null;

    /**
     * @return array{ok:bool,message:?string}
     */
    public static function attempt(string $email, string $password, string $ip): array
    {
        $email = strtolower(trim($email));
        $windowMinutes = (int) Config::get('security.login_lockout_minutes', 15);

        $wait = self::lockoutSeconds($email, $ip, $windowMinutes);
        if ($wait > 0) {
            return ['ok' => false, 'message' => 'Too many failed sign-in attempts. Please try again in ' . max(1, (int) ceil($wait / 60)) . ' minute(s).'];
        }

        $user = $email !== '' ? User::findByEmail($email) : null;

        // Always run password_verify, even for unknown emails, so response time does not reveal which emails exist.
        $valid = password_verify($password, $user['password_hash'] ?? self::dummyHash());
        $allowed = $user !== null && $valid && (bool) $user['is_active'] && $user['role_code'] !== 'patient';

        LoginAttempt::record($email, $ip, $allowed);
        if (!$allowed) {
            AuditService::log('auth.login_failed', 'user', null, 'Failed sign-in attempt', ['ip' => $ip], null);

            return ['ok' => false, 'message' => self::GENERIC_ERROR];
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::setPassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT), (bool) $user['must_change_password']);
        }
        User::touchLogin((int) $user['id']);
        Auth::login($user);
        AuditService::log('auth.login', 'user', (int) $user['id'], 'Signed in', null, (int) $user['id']);
        LoginAttempt::purgeOlderThanDays(7);

        return ['ok' => true, 'message' => null];
    }

    public static function logout(): void
    {
        $id = Auth::id();
        if ($id !== null) {
            AuditService::log('auth.logout', 'user', $id, 'Signed out');
        }
        Auth::logout();
    }

    /**
     * Applies the password policy. Returns an error message, or null when the password is acceptable.
     */
    public static function passwordProblem(string $password): ?string
    {
        $min = (int) Config::get('security.password_min_length', 10);
        if (mb_strlen($password) < $min) {
            return "Password must be at least $min characters long.";
        }
        if (strlen($password) > 72) {
            return 'Password must be at most 72 characters long.';
        }
        if (preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            return 'Password must contain at least one letter and one number.';
        }

        return null;
    }

    /**
     * @return array{ok:bool,message:?string}
     */
    public static function changePassword(int $userId, string $current, string $new): array
    {
        $user = User::findWithRole($userId);
        if ($user === null || !password_verify($current, $user['password_hash'])) {
            return ['ok' => false, 'message' => 'Your current password is not correct.'];
        }
        if ($current === $new) {
            return ['ok' => false, 'message' => 'Choose a password that is different from the current one.'];
        }
        $problem = self::passwordProblem($new);
        if ($problem !== null) {
            return ['ok' => false, 'message' => $problem];
        }

        User::setPassword($userId, password_hash($new, PASSWORD_DEFAULT), false);
        AuditService::log('auth.password_changed', 'user', $userId, 'Changed own password');

        return ['ok' => true, 'message' => null];
    }

    /** Random temporary password without look-alike characters (0/O, 1/l/I). */
    public static function temporaryPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        // Guarantee the policy (letter + number) is always met.
        return $password . random_int(2, 9);
    }

    private static function lockoutSeconds(string $email, string $ip, int $windowMinutes): int
    {
        $max = (int) Config::get('security.login_max_attempts', 5);
        $ipMax = (int) Config::get('security.login_ip_max_attempts', 25);

        if (LoginAttempt::failuresForPair($email, $ip, $windowMinutes) >= $max) {
            return max(60, LoginAttempt::secondsUntilRelief($email, $ip, $windowMinutes));
        }
        if (LoginAttempt::failuresForIp($ip, $windowMinutes) >= $ipMax) {
            return $windowMinutes * 60;
        }

        return 0;
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= password_hash('not-a-real-password', PASSWORD_DEFAULT);
    }
}
