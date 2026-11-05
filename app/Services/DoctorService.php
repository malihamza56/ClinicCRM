<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Roles;
use App\Core\BusinessRuleException;
use App\Core\Database;
use App\Core\Money;
use App\Core\Validator;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;

/** Doctor profiles, their login accounts and weekly schedules. */
final class DoctorService
{
    public static function validateProfile(array $input): Validator
    {
        $v = Validator::make($input, [
            'specialty_id'     => 'required|int',
            'license_no'       => 'max:50',
            'qualification'    => 'max:150',
            'phone'            => 'phone',
            'room'             => 'max:30',
            'consultation_fee' => 'required|money',
            'slot_minutes'     => 'required|int',
        ], ['specialty_id' => 'Specialty', 'consultation_fee' => 'Consultation fee', 'slot_minutes' => 'Appointment length', 'license_no' => 'License number']);

        if (!$v->fails()) {
            $d = $v->validated();
            if (Specialty::find((int) $d['specialty_id']) === null) {
                $v->addError('specialty_id', 'Please choose a valid specialty.');
            }
            $slot = (int) $d['slot_minutes'];
            if ($slot < 5 || $slot > 120) {
                $v->addError('slot_minutes', 'Appointment length must be between 5 and 120 minutes.');
            }
        }

        return $v;
    }

    /** Creates the login account and the doctor profile together; both succeed or neither is saved. */
    public static function createWithAccount(array $account, array $profile): array
    {
        return Database::transaction(static function () use ($account, $profile): array {
            $doctorRole = Database::one('SELECT id FROM roles WHERE code = :c', ['c' => Roles::DOCTOR]);
            $temporary = AuthService::temporaryPassword();
            $userId = User::create([
                'role_id'       => (int) $doctorRole['id'],
                'full_name'     => $account['full_name'],
                'email'         => strtolower($account['email']),
                'phone'         => $account['phone'] ?? null,
                'password_hash' => password_hash($temporary, PASSWORD_DEFAULT),
            ]);
            $doctorId = Doctor::create(self::profileRow($profile) + ['user_id' => $userId]);
            AuditService::log('doctor.created', 'doctor', $doctorId, 'Created doctor profile for ' . $account['full_name'], ['user_id' => $userId]);

            return ['doctor_id' => $doctorId, 'user_id' => $userId, 'temporary_password' => $temporary];
        });
    }

    public static function updateProfile(int $doctorId, array $profile): void
    {
        Doctor::update($doctorId, self::profileRow($profile));
        AuditService::log('doctor.updated', 'doctor', $doctorId, 'Updated doctor profile', ['fee' => $profile['consultation_fee']]);
    }

    /** @throws BusinessRuleException */
    public static function setActive(int $doctorId, bool $active): void
    {
        $doctor = Doctor::find($doctorId);
        if ($doctor === null) {
            throw new BusinessRuleException('Doctor not found.');
        }
        if (!$active) {
            $upcoming = (int) Database::value(
                "SELECT COUNT(*) FROM appointments WHERE doctor_id = :d AND appointment_date >= CURDATE() AND status IN ('Scheduled','Confirmed','Waiting','In Progress')",
                ['d' => $doctorId]
            );
            if ($upcoming > 0) {
                throw new BusinessRuleException("This doctor still has $upcoming upcoming appointment(s). Reschedule or cancel them first.");
            }
        }
        Doctor::setActive($doctorId, $active);
        AuditService::log($active ? 'doctor.activated' : 'doctor.deactivated', 'doctor', $doctorId, ($active ? 'Activated ' : 'Deactivated ') . $doctor['full_name']);
    }

    /**
     * Adds a weekly availability window. Rejects windows outside clinic hours and windows that overlap an existing one.
     *
     * @throws BusinessRuleException
     */
    public static function addSchedule(int $doctorId, int $weekday, string $start, string $end): void
    {
        if ($weekday < 1 || $weekday > 7) {
            throw new BusinessRuleException('Please choose a valid weekday.');
        }
        if ($start >= $end) {
            throw new BusinessRuleException('The end time must be after the start time.');
        }
        $opening = setting('opening_time', '09:00');
        $closing = setting('closing_time', '21:00');
        if ($start < $opening || $end > $closing) {
            throw new BusinessRuleException('Doctor hours must fall inside clinic hours (' . format_time($opening) . ' - ' . format_time($closing) . ').');
        }
        foreach (Doctor::schedulesForWeekday($doctorId, $weekday) as $existing) {
            $existingStart = substr((string) $existing['start_time'], 0, 5);
            $existingEnd = substr((string) $existing['end_time'], 0, 5);
            if ($start < $existingEnd && $end > $existingStart) {
                throw new BusinessRuleException('This time overlaps an existing window on the same day.');
            }
        }

        Doctor::addSchedule($doctorId, $weekday, $start, $end);
        AuditService::log('doctor.schedule_added', 'doctor', $doctorId, "Added schedule window (day $weekday, $start-$end)");
    }

    public static function removeSchedule(int $doctorId, int $scheduleId): void
    {
        Doctor::deleteSchedule($doctorId, $scheduleId);
        AuditService::log('doctor.schedule_removed', 'doctor', $doctorId, 'Removed a schedule window');
    }

    /** @return array<string,mixed> */
    private static function profileRow(array $p): array
    {
        return [
            'specialty_id'     => (int) $p['specialty_id'],
            'license_no'       => $p['license_no'],
            'qualification'    => $p['qualification'],
            'phone'            => $p['phone'],
            'room'             => $p['room'],
            'consultation_fee' => Money::toDecimal((int) Money::parse((string) $p['consultation_fee'])),
            'slot_minutes'     => (int) $p['slot_minutes'],
        ];
    }
}
