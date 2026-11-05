<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Page;

final class Patient
{
    /** Columns every patient list/profile needs. */
    private const SELECT = "SELECT p.*, CONCAT(p.first_name, ' ', p.last_name) AS full_name FROM patients p";

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE p.id = :id', ['id' => $id]);
    }

    /**
     * A doctor has a "care relationship" with a patient when the patient has a booked (not cancelled)
     * appointment with them, or they have recorded a visit for them. Used to restrict doctor access.
     */
    public static function hasCareRelationship(int $doctorId, int $patientId): bool
    {
        return (int) Database::value(
            "SELECT (SELECT COUNT(*) FROM appointments WHERE patient_id = :p1 AND doctor_id = :d1 AND status NOT IN ('Cancelled','No Show'))
                  + (SELECT COUNT(*) FROM visits WHERE patient_id = :p2 AND doctor_id = :d2)",
            ['p1' => $patientId, 'd1' => $doctorId, 'p2' => $patientId, 'd2' => $doctorId]
        ) > 0;
    }

    /**
     * @param array{q?:string,status?:string} $filters
     * @param int|null $doctorScope When set, only patients with a care relationship with this doctor are listed.
     */
    public static function paginate(array $filters, int $page, int $perPage, ?int $doctorScope = null): Page
    {
        [$where, $params] = self::filterSql($filters, $doctorScope);
        $total = (int) Database::value("SELECT COUNT(*) FROM patients p WHERE $where", $params);
        $items = Database::all(
            self::SELECT . " WHERE $where ORDER BY p.created_at DESC, p.id DESC LIMIT " . (int) $perPage . ' OFFSET ' . Page::offset($page, $perPage),
            $params
        );

        return new Page($items, $total, $page, $perPage);
    }

    /** @return array<int,array<string,mixed>> All matches for CSV export (no paging). */
    public static function exportRows(array $filters): array
    {
        [$where, $params] = self::filterSql($filters, null);

        return Database::all(self::SELECT . " WHERE $where ORDER BY p.id", $params);
    }

    /** Small typeahead search (active patients only). @return array<int,array<string,mixed>> */
    public static function lookup(string $q, int $limit = 8, ?int $doctorScope = null): array
    {
        [$where, $params] = self::filterSql(['q' => $q, 'status' => 'active'], $doctorScope);

        return Database::all(
            "SELECT p.id, p.patient_code, p.phone, p.date_of_birth, CONCAT(p.first_name, ' ', p.last_name) AS full_name
             FROM patients p WHERE $where ORDER BY p.first_name, p.last_name LIMIT " . (int) $limit,
            $params
        );
    }

    public static function create(array $data, string $code, int $createdBy): int
    {
        return Database::insert(
            'INSERT INTO patients (patient_code, first_name, last_name, gender, date_of_birth, phone, email, address, city,
                                   blood_group, emergency_contact_name, emergency_contact_phone, registered_on, created_by)
             VALUES (:code, :first_name, :last_name, :gender, :dob, :phone, :email, :address, :city,
                     :blood_group, :ec_name, :ec_phone, CURDATE(), :created_by)',
            [
                'code'        => $code,
                'first_name'  => $data['first_name'],
                'last_name'   => $data['last_name'],
                'gender'      => $data['gender'],
                'dob'         => $data['date_of_birth'],
                'phone'       => $data['phone'],
                'email'       => $data['email'],
                'address'     => $data['address'],
                'city'        => $data['city'],
                'blood_group' => $data['blood_group'],
                'ec_name'     => $data['emergency_contact_name'],
                'ec_phone'    => $data['emergency_contact_phone'],
                'created_by'  => $createdBy,
            ]
        );
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE patients SET first_name = :first_name, last_name = :last_name, gender = :gender, date_of_birth = :dob,
                    phone = :phone, email = :email, address = :address, city = :city, blood_group = :blood_group,
                    emergency_contact_name = :ec_name, emergency_contact_phone = :ec_phone WHERE id = :id',
            [
                'first_name'  => $data['first_name'],
                'last_name'   => $data['last_name'],
                'gender'      => $data['gender'],
                'dob'         => $data['date_of_birth'],
                'phone'       => $data['phone'],
                'email'       => $data['email'],
                'address'     => $data['address'],
                'city'        => $data['city'],
                'blood_group' => $data['blood_group'],
                'ec_name'     => $data['emergency_contact_name'],
                'ec_phone'    => $data['emergency_contact_phone'],
                'id'          => $id,
            ]
        );
    }

    public static function setArchived(int $id, bool $archived, int $byUser): void
    {
        Database::run(
            'UPDATE patients SET is_archived = :a, archived_at = ' . ($archived ? 'NOW()' : 'NULL') . ', archived_by = :by WHERE id = :id',
            ['a' => (int) $archived, 'by' => $archived ? $byUser : null, 'id' => $id]
        );
    }

    /** Same first name, last name, date of birth and phone = same person. */
    public static function duplicateOf(array $data, ?int $exceptId = null): ?array
    {
        return Database::one(
            'SELECT id, patient_code FROM patients
             WHERE first_name = :f AND last_name = :l AND date_of_birth = :d AND phone = :p AND id <> :id',
            ['f' => $data['first_name'], 'l' => $data['last_name'], 'd' => $data['date_of_birth'], 'p' => $data['phone'], 'id' => $exceptId ?? 0]
        );
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private static function filterSql(array $filters, ?int $doctorScope): array
    {
        $where = ['1 = 1'];
        $params = [];

        $status = $filters['status'] ?? 'active';
        if ($status === 'active') {
            $where[] = 'p.is_archived = 0';
        } elseif ($status === 'archived') {
            $where[] = 'p.is_archived = 1';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . Database::escapeLike($q) . '%';
            $where[] = "(p.patient_code LIKE :q1 OR CONCAT(p.first_name, ' ', p.last_name) LIKE :q2 OR p.phone LIKE :q3)";
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }

        if ($doctorScope !== null) {
            $where[] = "(EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.doctor_id = :ds1 AND a.status NOT IN ('Cancelled','No Show'))
                         OR EXISTS (SELECT 1 FROM visits v WHERE v.patient_id = p.id AND v.doctor_id = :ds2))";
            $params += ['ds1' => $doctorScope, 'ds2' => $doctorScope];
        }

        return [implode(' AND ', $where), $params];
    }
}
