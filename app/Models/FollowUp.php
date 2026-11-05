<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Page;

final class FollowUp
{
    private const SELECT = "SELECT f.*, CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.patient_code, p.phone AS patient_phone,
            u.full_name AS doctor_name,
            CASE WHEN f.status <> 'Pending' THEN f.status
                 WHEN f.follow_up_date < CURDATE() THEN 'Overdue'
                 WHEN f.follow_up_date = CURDATE() THEN 'Due today'
                 ELSE 'Upcoming' END AS bucket
        FROM follow_ups f
        JOIN patients p ON p.id = f.patient_id
        JOIN doctors d ON d.id = f.doctor_id
        JOIN users u ON u.id = d.user_id";

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE f.id = :id', ['id' => $id]);
    }

    /**
     * @param array{bucket?:string,doctor_id?:int,from?:string,to?:string} $filters
     * bucket: overdue | due | upcoming | completed | cancelled | all
     */
    public static function paginate(array $filters, int $page, int $perPage): Page
    {
        [$where, $params] = self::filterSql($filters);
        $total = (int) Database::value('SELECT COUNT(*) FROM follow_ups f WHERE ' . $where, $params);
        $items = Database::all(
            self::SELECT . " WHERE $where ORDER BY f.follow_up_date ASC, f.id ASC LIMIT " . (int) $perPage . ' OFFSET ' . Page::offset($page, $perPage),
            $params
        );

        return new Page($items, $total, $page, $perPage);
    }

    /** Counts of pending follow-ups by bucket (for tabs and dashboards). @return array<string,int> */
    public static function counts(?int $doctorId = null): array
    {
        $doctorSql = $doctorId !== null ? ' AND doctor_id = :d' : '';
        $params = $doctorId !== null ? ['d' => $doctorId] : [];
        $row = Database::one(
            "SELECT
                COALESCE(SUM(follow_up_date < CURDATE()), 0)  AS overdue,
                COALESCE(SUM(follow_up_date = CURDATE()), 0)  AS due,
                COALESCE(SUM(follow_up_date > CURDATE()), 0)  AS upcoming
             FROM follow_ups WHERE status = 'Pending'" . $doctorSql,
            $params
        );

        return ['overdue' => (int) $row['overdue'], 'due' => (int) $row['due'], 'upcoming' => (int) $row['upcoming']];
    }

    /** @return array<int,array<string,mixed>> */
    public static function forPatient(int $patientId): array
    {
        return Database::all(self::SELECT . ' WHERE f.patient_id = :p ORDER BY f.follow_up_date DESC', ['p' => $patientId]);
    }

    public static function create(int $patientId, ?int $visitId, int $doctorId, string $date, ?string $notes, int $createdBy): int
    {
        return Database::insert(
            'INSERT INTO follow_ups (patient_id, visit_id, doctor_id, follow_up_date, notes, created_by) VALUES (:p, :v, :d, :date, :n, :by)',
            ['p' => $patientId, 'v' => $visitId, 'd' => $doctorId, 'date' => $date, 'n' => $notes, 'by' => $createdBy]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::run(
            'UPDATE follow_ups SET status = :s, completed_at = ' . ($status === 'Completed' ? 'NOW()' : 'NULL') . ' WHERE id = :id',
            ['s' => $status, 'id' => $id]
        );
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private static function filterSql(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        $bucket = $filters['bucket'] ?? 'all';
        $where[] = match ($bucket) {
            'overdue'   => "f.status = 'Pending' AND f.follow_up_date < CURDATE()",
            'due'       => "f.status = 'Pending' AND f.follow_up_date = CURDATE()",
            'upcoming'  => "f.status = 'Pending' AND f.follow_up_date > CURDATE()",
            'completed' => "f.status = 'Completed'",
            'cancelled' => "f.status = 'Cancelled'",
            default     => '1 = 1',
        };
        if (!empty($filters['doctor_id'])) {
            $where[] = 'f.doctor_id = :doctor';
            $params['doctor'] = (int) $filters['doctor_id'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'f.follow_up_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'f.follow_up_date <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['patient_id'])) {
            $where[] = 'f.patient_id = :patient';
            $params['patient'] = (int) $filters['patient_id'];
        }

        return [implode(' AND ', $where), $params];
    }
}
