<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Doctor professional profiles (linked 1-to-1 with a user account) and their weekly schedules. */
final class Doctor
{
    private const SELECT = 'SELECT d.*, u.full_name, u.email, u.is_active AS account_active, s.name AS specialty_name
        FROM doctors d JOIN users u ON u.id = d.user_id JOIN specialties s ON s.id = d.specialty_id';

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE d.id = :id', ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(bool $activeOnly = false): array
    {
        return Database::all(self::SELECT . ($activeOnly ? ' WHERE d.is_active = 1 AND u.is_active = 1' : '') . ' ORDER BY d.is_active DESC, u.full_name');
    }

    /**
     * Doctor directory search.
     *
     * @param array{q?:string,specialty_id?:int,status?:string} $filters status: active | inactive | '' (all)
     * @return array<int,array<string,mixed>>
     */
    public static function search(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = 'u.full_name LIKE :q';
            $params['q'] = '%' . Database::escapeLike($q) . '%';
        }
        if (!empty($filters['specialty_id'])) {
            $where[] = 'd.specialty_id = :specialty';
            $params['specialty'] = (int) $filters['specialty_id'];
        }
        if (($filters['status'] ?? '') === 'active') {
            $where[] = 'd.is_active = 1 AND u.is_active = 1';
        } elseif (($filters['status'] ?? '') === 'inactive') {
            $where[] = '(d.is_active = 0 OR u.is_active = 0)';
        }

        return Database::all(self::SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY d.is_active DESC, u.full_name', $params);
    }

    public static function create(array $data): int
    {
        return Database::insert(
            'INSERT INTO doctors (user_id, specialty_id, license_no, qualification, phone, room, consultation_fee, slot_minutes)
             VALUES (:user_id, :specialty_id, :license_no, :qualification, :phone, :room, :fee, :slot)',
            [
                'user_id'       => $data['user_id'],
                'specialty_id'  => $data['specialty_id'],
                'license_no'    => $data['license_no'],
                'qualification' => $data['qualification'],
                'phone'         => $data['phone'],
                'room'          => $data['room'],
                'fee'           => $data['consultation_fee'],
                'slot'          => $data['slot_minutes'],
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE doctors SET specialty_id = :specialty_id, license_no = :license_no, qualification = :qualification,
                    phone = :phone, room = :room, consultation_fee = :fee, slot_minutes = :slot WHERE id = :id',
            [
                'specialty_id'  => $data['specialty_id'],
                'license_no'    => $data['license_no'],
                'qualification' => $data['qualification'],
                'phone'         => $data['phone'],
                'room'          => $data['room'],
                'fee'           => $data['consultation_fee'],
                'slot'          => $data['slot_minutes'],
                'id'            => $id,
            ]
        );
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::run('UPDATE doctors SET is_active = :a WHERE id = :id', ['a' => (int) $active, 'id' => $id]);
    }

    /** Locks the doctor row so two simultaneous bookings for this doctor are handled one after the other. */
    public static function lock(int $id): void
    {
        Database::run('SELECT id FROM doctors WHERE id = :id FOR UPDATE', ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function schedules(int $doctorId): array
    {
        return Database::all('SELECT * FROM doctor_schedules WHERE doctor_id = :d ORDER BY weekday, start_time', ['d' => $doctorId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function schedulesForWeekday(int $doctorId, int $weekday): array
    {
        return Database::all(
            'SELECT * FROM doctor_schedules WHERE doctor_id = :d AND weekday = :w ORDER BY start_time',
            ['d' => $doctorId, 'w' => $weekday]
        );
    }

    public static function addSchedule(int $doctorId, int $weekday, string $start, string $end): void
    {
        Database::run(
            'INSERT INTO doctor_schedules (doctor_id, weekday, start_time, end_time) VALUES (:d, :w, :s, :e)',
            ['d' => $doctorId, 'w' => $weekday, 's' => $start, 'e' => $end]
        );
    }

    public static function deleteSchedule(int $doctorId, int $scheduleId): void
    {
        Database::run('DELETE FROM doctor_schedules WHERE id = :id AND doctor_id = :d', ['id' => $scheduleId, 'd' => $doctorId]);
    }
}
