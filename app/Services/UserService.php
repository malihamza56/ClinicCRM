<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Roles;
use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Database;
use App\Core\Validator;
use App\Models\Doctor;
use App\Models\User;

/** Staff account management (admin only). */
final class UserService
{
    public static function validate(array $input, ?int $editingId = null): Validator
    {
        $rules = [
            'full_name' => 'required|name|max:120',
            'email'     => 'required|email|max:190',
            'phone'     => 'phone',
            'role_id'   => 'required|int',
        ];
        $v = Validator::make($input, $rules, ['full_name' => 'Full name', 'role_id' => 'Role']);
        if ($v->fails()) {
            return $v;
        }

        $data = $v->validated();
        $role = self::roleById((int) $data['role_id']);
        if ($role === null || $role['code'] === Roles::PATIENT) {
            $v->addError('role_id', 'Please choose a valid staff role.');
        }
        if (User::emailTaken(strtolower($data['email']), $editingId)) {
            $v->addError('email', 'Another account already uses this email address.');
        }

        return $v;
    }

    /** @return array{id:int,temporary_password:string} */
    public static function create(array $data): array
    {
        $temporary = AuthService::temporaryPassword();
        $id = User::create([
            'role_id'       => (int) $data['role_id'],
            'full_name'     => $data['full_name'],
            'email'         => strtolower($data['email']),
            'phone'         => $data['phone'],
            'password_hash' => password_hash($temporary, PASSWORD_DEFAULT),
        ]);
        AuditService::log('user.created', 'user', $id, 'Created staff account ' . $data['full_name'], ['role_id' => $data['role_id'], 'email' => strtolower($data['email'])]);

        return ['id' => $id, 'temporary_password' => $temporary];
    }

    /** @throws BusinessRuleException */
    public static function update(int $id, array $data): void
    {
        $existing = User::findWithRole($id);
        if ($existing === null) {
            throw new BusinessRuleException('Account not found.');
        }
        $newRole = self::roleById((int) $data['role_id']);
        if ((int) $data['role_id'] !== (int) $existing['role_id']) {
            if ($id === Auth::id()) {
                throw new BusinessRuleException('You cannot change your own role.');
            }
            if ($existing['role_code'] === Roles::DOCTOR || self::hasDoctorProfile($id)) {
                throw new BusinessRuleException('This account has a doctor profile. Its role cannot be changed.');
            }
            if ($existing['role_code'] === Roles::ADMIN && $existing['is_active'] && User::activeAdminCount() <= 1) {
                throw new BusinessRuleException('The last active admin cannot be moved to another role.');
            }
        }
        User::update($id, [
            'role_id'   => (int) $newRole['id'],
            'full_name' => $data['full_name'],
            'email'     => strtolower($data['email']),
            'phone'     => $data['phone'],
        ]);
        AuditService::log('user.updated', 'user', $id, 'Updated staff account ' . $data['full_name'], ['role_id' => $data['role_id']]);
    }

    /** @throws BusinessRuleException */
    public static function setActive(int $id, bool $active): void
    {
        $user = User::findWithRole($id);
        if ($user === null) {
            throw new BusinessRuleException('Account not found.');
        }
        if (!$active) {
            if ($id === Auth::id()) {
                throw new BusinessRuleException('You cannot deactivate your own account.');
            }
            if ($user['role_code'] === Roles::ADMIN && User::activeAdminCount() <= 1) {
                throw new BusinessRuleException('The last active admin cannot be deactivated.');
            }
        }
        User::setActive($id, $active);
        AuditService::log($active ? 'user.activated' : 'user.deactivated', 'user', $id, ($active ? 'Activated ' : 'Deactivated ') . $user['full_name']);
    }

    /** Issues a new temporary password; the user must replace it at next sign-in. */
    public static function resetPassword(int $id): ?string
    {
        $user = User::findWithRole($id);
        if ($user === null) {
            return null;
        }
        $temporary = AuthService::temporaryPassword();
        User::setPassword($id, password_hash($temporary, PASSWORD_DEFAULT), true);
        AuditService::log('user.password_reset', 'user', $id, 'Reset password for ' . $user['full_name']);

        return $temporary;
    }

    private static function roleById(int $id): ?array
    {
        return Database::one('SELECT id, code, name FROM roles WHERE id = :id', ['id' => $id]);
    }

    private static function hasDoctorProfile(int $userId): bool
    {
        return Database::value('SELECT COUNT(*) FROM doctors WHERE user_id = :u', ['u' => $userId]) > 0;
    }
}
