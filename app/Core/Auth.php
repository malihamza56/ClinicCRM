<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\User;

/**
 * Who is signed in during THIS request.
 *
 * The session only stores the user's id. The account is re-read from the database on every
 * request, so deactivating a user or changing their role takes effect immediately.
 */
final class Auth
{
    private static bool $loaded = false;
    private static ?array $user = null;
    /** @var string[]|null */
    private static ?array $permissions = null;
    private static ?int $doctorId = null;
    private static bool $doctorLoaded = false;

    /** @return array<string,mixed>|null The signed-in account (with role_code), or null. */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $id = Session::get('user_id');
        if (!is_int($id)) {
            return self::$user = null;
        }

        $idleSeconds = (int) Config::get('security.session_idle_minutes', 30) * 60;
        $last = (int) Session::get('last_activity', 0);
        if ($last > 0 && time() - $last > $idleSeconds) {
            self::logout();
            Session::flash('warning', 'You were signed out after a period of inactivity.');

            return null;
        }

        $user = User::findWithRole($id);
        if ($user === null || !(bool) $user['is_active'] || $user['role_code'] === 'patient') {
            self::logout();

            return null;
        }

        Session::put('last_activity', time());

        return self::$user = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user['id'];
    }

    public static function role(): ?string
    {
        return self::user()['role_code'] ?? null;
    }

    public static function isRole(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    /** True when the signed-in role holds the permission (read from the database, cached per request). */
    public static function can(string $permission): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        self::$permissions ??= User::permissionCodes((int) $user['role_id']);

        return in_array($permission, self::$permissions, true);
    }

    /** True when the role holds at least one of the permissions. */
    public static function canAny(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return true;
            }
        }

        return false;
    }

    /** The doctor profile id of the signed-in user, or null when they are not a doctor. */
    public static function doctorId(): ?int
    {
        if (!self::$doctorLoaded) {
            self::$doctorLoaded = true;
            $id = self::id();
            self::$doctorId = $id === null ? null : User::doctorIdForUser($id);
        }

        return self::$doctorId;
    }

    /** Called after a successful password check. */
    public static function login(array $user): void
    {
        Session::regenerate();               // defeats session fixation
        Csrf::rotate();
        Session::put('user_id', (int) $user['id']);
        Session::put('last_activity', time());
        self::reset();
    }

    public static function logout(): void
    {
        Session::reset();
        self::reset();
    }

    /** Forgets the per-request cache (used after logout and by tests). */
    public static function reset(): void
    {
        self::$loaded = false;
        self::$user = null;
        self::$permissions = null;
        self::$doctorId = null;
        self::$doctorLoaded = false;
    }

    /** Test helper: act as a given user without a session. */
    public static function actingAs(array $user): void
    {
        self::$loaded = true;
        self::$user = $user;
        self::$permissions = null;
        self::$doctorLoaded = false;
    }
}
