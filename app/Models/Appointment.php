<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Page;

final class Appointment
{
    private const SELECT = "SELECT a.*, p.patient_code, p.phone AS patient_phone,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.date_of_birth, p.gender,
            d.user_id AS doctor_user_id, u.full_name AS doctor_name, s.name AS specialty_name, d.room, d.slot_minutes
        FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        JOIN doctors d ON d.id = a.doctor_id
        JOIN users u ON u.id = d.user_id
        JOIN specialties s ON s.id = d.specialty_id";

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE a.id = :id', ['id' => $id]);
    }

    /** Same as find() but locks the row for the rest of the transaction (prevents two staff changing it at once). */
    public static function findForUpdate(int $id): ?array
    {
        return Database::one('SELECT * FROM appointments WHERE id = :id FOR UPDATE', ['id' => $id]);
    }

    /** @param array{date?:string,doctor_id?:int,status?:string,q?:string,patient_id?:int,from?:string,to?:string} $filters */
    public static function paginate(array $filters, int $page, int $perPage): Page
    {
        [$where, $params] = self::filterSql($filters);
        $total = (int) Database::value(
            'SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE ' . $where,
            $params
        );
        $items = Database::all(
            self::SELECT . " WHERE $where ORDER BY a.appointment_date DESC, a.start_time DESC, a.id DESC LIMIT " . (int) $perPage . ' OFFSET ' . Page::offset($page, $perPage),
            $params
        );

        return new Page($items, $total, $page, $perPage);
    }

    /** @return array<int,array<string,mixed>> */
    public static function exportRows(array $filters): array
    {
        [$where, $params] = self::filterSql($filters);

        return Database::all(self::SELECT . " WHERE $where ORDER BY a.appointment_date, a.start_time", $params);
    }

    /** @return array<int,array<string,mixed>> */
    public static function forPatient(int $patientId, int $limit = 20): array
    {
        return Database::all(
            self::SELECT . ' WHERE a.patient_id = :p ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT ' . (int) $limit,
            ['p' => $patientId]
        );
    }

    /** Today's list in time order. @return array<int,array<string,mixed>> */
    public static function forDay(string $date, ?int $doctorId = null): array
    {
        $sql = self::SELECT . ' WHERE a.appointment_date = :d';
        $params = ['d' => $date];
        if ($doctorId !== null) {
            $sql .= ' AND a.doctor_id = :doc';
            $params['doc'] = $doctorId;
        }

        return Database::all($sql . ' ORDER BY a.start_time, a.id', $params);
    }

    /** Waiting and in-progress patients for the queue board, in queue order. @return array<int,array<string,mixed>> */
    public static function queue(string $date, ?int $doctorId = null): array
    {
        $sql = self::SELECT . " WHERE a.appointment_date = :d AND a.status IN ('Waiting','In Progress')";
        $params = ['d' => $date];
        if ($doctorId !== null) {
            $sql .= ' AND a.doctor_id = :doc';
            $params['doc'] = $doctorId;
        }

        return Database::all($sql . " ORDER BY FIELD(a.status, 'In Progress', 'Waiting'), a.queue_number, a.id", $params);
    }

    public static function create(array $data, int $createdBy): int
    {
        return Database::insert(
            'INSERT INTO appointments (patient_id, doctor_id, appointment_date, start_time, end_time, booking_type, status,
                                       reason, queue_number, checked_in_at, created_by)
             VALUES (:patient_id, :doctor_id, :date, :start, :end, :type, :status, :reason, :queue, :checked_in, :created_by)',
            [
                'patient_id' => $data['patient_id'],
                'doctor_id'  => $data['doctor_id'],
                'date'       => $data['appointment_date'],
                'start'      => $data['start_time'],
                'end'        => $data['end_time'],
                'type'       => $data['booking_type'],
                'status'     => $data['status'],
                'reason'     => $data['reason'] ?? null,
                'queue'      => $data['queue_number'] ?? null,
                'checked_in' => $data['checked_in_at'] ?? null,
                'created_by' => $createdBy,
            ]
        );
    }

    public static function setStatus(int $id, string $status, ?string $cancellationReason = null): void
    {
        Database::run(
            'UPDATE appointments SET status = :s, cancellation_reason = COALESCE(:r, cancellation_reason) WHERE id = :id',
            ['s' => $status, 'r' => $cancellationReason, 'id' => $id]
        );
    }

    public static function checkIn(int $id, int $queueNumber): void
    {
        Database::run(
            "UPDATE appointments SET status = 'Waiting', queue_number = :q, checked_in_at = NOW() WHERE id = :id",
            ['q' => $queueNumber, 'id' => $id]
        );
    }

    public static function reschedule(int $id, string $date, string $start, string $end): void
    {
        Database::run(
            "UPDATE appointments SET appointment_date = :d, start_time = :s, end_time = :e, status = 'Scheduled' WHERE id = :id",
            ['d' => $date, 's' => $start, 'e' => $end, 'id' => $id]
        );
    }

    /** Booked slots of a doctor on a date (cancelled / no-show excluded). @return array<int,array<string,mixed>> */
    public static function occupied(int $doctorId, string $date, ?int $exceptId = null): array
    {
        return Database::all(
            "SELECT id, start_time, end_time FROM appointments
             WHERE doctor_id = :d AND appointment_date = :date AND booking_type = 'Scheduled'
               AND status NOT IN ('Cancelled','No Show') AND id <> :ex ORDER BY start_time",
            ['d' => $doctorId, 'date' => $date, 'ex' => $exceptId ?? 0]
        );
    }

    public static function nextQueueNumber(int $doctorId, string $date): int
    {
        return (int) Database::value(
            'SELECT COALESCE(MAX(queue_number), 0) + 1 FROM appointments WHERE doctor_id = :d AND appointment_date = :date',
            ['d' => $doctorId, 'date' => $date]
        );
    }

    /** Is this patient already booked (not cancelled) with this doctor on this date? */
    public static function patientHasActiveBooking(int $patientId, int $doctorId, string $date, ?int $exceptId = null): bool
    {
        return Database::value(
            "SELECT COUNT(*) FROM appointments WHERE patient_id = :p AND doctor_id = :d AND appointment_date = :date
               AND status NOT IN ('Cancelled','No Show','Completed') AND id <> :ex",
            ['p' => $patientId, 'd' => $doctorId, 'date' => $date, 'ex' => $exceptId ?? 0]
        ) > 0;
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private static function filterSql(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['date'])) {
            $where[] = 'a.appointment_date = :date';
            $params['date'] = $filters['date'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'a.appointment_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'a.appointment_date <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['doctor_id'])) {
            $where[] = 'a.doctor_id = :doctor';
            $params['doctor'] = (int) $filters['doctor_id'];
        }
        if (!empty($filters['patient_id'])) {
            $where[] = 'a.patient_id = :patient';
            $params['patient'] = (int) $filters['patient_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'a.status = :status';
            $params['status'] = $filters['status'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . Database::escapeLike($q) . '%';
            $where[] = "(p.patient_code LIKE :q1 OR CONCAT(p.first_name, ' ', p.last_name) LIKE :q2 OR p.phone LIKE :q3)";
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }

        return [implode(' AND ', $where), $params];
    }
}
