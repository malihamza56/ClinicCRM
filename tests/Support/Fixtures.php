<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Money;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\User;

/** Builders for test data. Every created row is fictional. */
final class Fixtures
{
    public const PASSWORD = 'Test-Password-123';
    private static int $n = 0;

    public static function unique(): int
    {
        return 1000 + ++self::$n;
    }

    public static function user(string $roleCode, array $override = []): array
    {
        $role = Database::one('SELECT id FROM roles WHERE code = :c', ['c' => $roleCode]);
        $u = self::unique();
        $id = User::create($override + [
            'role_id' => (int) $role['id'], 'full_name' => 'Test ' . ucfirst($roleCode) . ' ' . chr(65 + $u % 26), 'email' => "$roleCode$u@test.example",
            'phone' => null, 'password_hash' => password_hash(self::PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]), 'must_change_password' => 0,
        ]);

        return User::findWithRole($id);
    }

    public static function actAs(array $user): array
    {
        Auth::actingAs($user);

        return $user;
    }

    /** Doctor that works every weekday around the clock, so tests do not depend on the time they run. */
    public static function doctor(int $slotMinutes = 15, string $fee = '1500.00', bool $allDay = true): array
    {
        $user = self::user('doctor');
        $spec = Database::one('SELECT id FROM specialties LIMIT 1');
        if ($spec === null) {
            $spec = ['id' => Database::insert("INSERT INTO specialties (name) VALUES ('General " . self::unique() . "')")];
        }
        $id = Doctor::create([
            'user_id' => $user['id'], 'specialty_id' => $spec['id'], 'license_no' => null, 'qualification' => 'MBBS', 'phone' => null,
            'room' => 'T-1', 'consultation_fee' => $fee, 'slot_minutes' => $slotMinutes,
        ]);
        if ($allDay) {
            for ($d = 1; $d <= 7; $d++) {
                Doctor::addSchedule($id, $d, '09:00', '21:00');
            }
        }
        Setting::set('opening_time', '09:00');
        Setting::set('closing_time', '21:00');

        return Doctor::find($id) + ['user' => User::findWithRole((int) $user['id'])];
    }

    public static function patient(array $override = []): array
    {
        $u = self::unique();
        $admin = Database::one("SELECT u.id FROM users u JOIN roles r ON r.id = u.role_id WHERE r.code = 'admin' LIMIT 1") ?? ['id' => self::user('admin')['id']];
        $data = $override + [
            'first_name' => 'Pat' . $u, 'last_name' => 'Tester', 'gender' => 'Male', 'date_of_birth' => '1990-05-05', 'phone' => '0300' . str_pad((string) $u, 7, '0', STR_PAD_LEFT),
            'email' => null, 'address' => null, 'city' => null, 'blood_group' => null, 'emergency_contact_name' => null, 'emergency_contact_phone' => null,
        ];
        $id = Patient::create($data, 'T' . $u, (int) $admin['id']);

        return Patient::find($id);
    }

    /** A date that is a weekday-independent future date for booking tests. */
    public static function futureDate(int $days = 3): string
    {
        return date('Y-m-d', strtotime("+$days days"));
    }

    public static function minor(string $decimal): int
    {
        return Money::fromDb($decimal);
    }
}
