<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Staff accounts. Authentication data only - doctor profile data lives in the Doctor model. */
final class User
{
    private const SELECT = 'SELECT u.*, r.code AS role_code, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id';

    public static function findWithRole(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE u.id = :id', ['id' => $id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::one(self::SELECT . ' WHERE u.email = :email', ['email' => $email]);
    }

    /** @return string[] */
    public static function permissionCodes(int $roleId): array
    {
        $rows = Database::all(
            'SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = :r',
            ['r' => $roleId]
        );

        return array_column($rows, 'code');
    }

    public static function doctorIdForUser(int $userId): ?int
    {
        $id = Database::value('SELECT id FROM doctors WHERE user_id = :u AND is_active = 1', ['u' => $userId]);

        return $id === null ? null : (int) $id;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(?string $roleCode = null): array
    {
        $sql = self::SELECT;
        $params = [];
        if ($roleCode !== null) {
            $sql .= ' WHERE r.code = :role';
            $params['role'] = $roleCode;
        }

        return Database::all($sql . ' ORDER BY u.is_active DESC, u.full_name', $params);
    }

    /** Roles an admin may assign to staff (patient is reserved for the future portal). */
    public static function staffRoles(): array
    {
        return Database::all("SELECT id, code, name FROM roles WHERE code <> 'patient' ORDER BY id");
    }

    public static function emailTaken(string $email, ?int $exceptId = null): bool
    {
        return Database::value(
            'SELECT COUNT(*) FROM users WHERE email = :e AND id <> :id',
            ['e' => $email, 'id' => $exceptId ?? 0]
        ) > 0;
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO users (role_id, full_name, email, phone, password_hash, is_active, must_change_password)
             VALUES (:role_id, :full_name, :email, :phone, :password_hash, 1, :must_change)',
            [
                'role_id'       => $data['role_id'],
                'full_name'     => $data['full_name'],
                'email'         => $data['email'],
                'phone'         => $data['phone'] ?? null,
                'password_hash' => $data['password_hash'],
                'must_change'   => (int) ($data['must_change_password'] ?? 1),
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE users SET role_id = :role_id, full_name = :full_name, email = :email, phone = :phone WHERE id = :id',
            [
                'role_id'   => $data['role_id'],
                'full_name' => $data['full_name'],
                'email'     => $data['email'],
                'phone'     => $data['phone'] ?? null,
                'id'        => $id,
            ]
        );
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::run('UPDATE users SET is_active = :a WHERE id = :id', ['a' => (int) $active, 'id' => $id]);
    }

    public static function setPassword(int $id, string $hash, bool $mustChange): void
    {
        Database::run(
            'UPDATE users SET password_hash = :h, must_change_password = :m, password_changed_at = NOW() WHERE id = :id',
            ['h' => $hash, 'm' => (int) $mustChange, 'id' => $id]
        );
    }

    public static function touchLogin(int $id): void
    {
        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = :id', ['id' => $id]);
    }

    public static function activeAdminCount(): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin' AND u.is_active = 1"
        );
    }
}
