<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Consultations. Clinical columns are listed once in FIELDS so create/update/snapshot never disagree. */
final class Visit
{
    /** The clinical columns a doctor edits (everything an amendment has to remember). */
    public const FIELDS = [
        'chief_complaint', 'symptoms', 'bp_systolic', 'bp_diastolic', 'pulse_bpm', 'temperature_c',
        'respiratory_rate', 'spo2_percent', 'weight_kg', 'height_cm', 'diagnosis', 'treatment_plan', 'notes', 'follow_up_advice',
    ];

    private const SELECT = "SELECT v.*, CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.patient_code, p.date_of_birth, p.gender,
            p.blood_group, u.full_name AS doctor_name, s.name AS specialty_name, d.qualification, d.license_no
        FROM visits v
        JOIN patients p ON p.id = v.patient_id
        JOIN doctors d ON d.id = v.doctor_id
        JOIN users u ON u.id = d.user_id
        JOIN specialties s ON s.id = d.specialty_id";

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE v.id = :id', ['id' => $id]);
    }

    public static function findForUpdate(int $id): ?array
    {
        return Database::one('SELECT * FROM visits WHERE id = :id FOR UPDATE', ['id' => $id]);
    }

    public static function findByAppointment(int $appointmentId): ?array
    {
        return Database::one(self::SELECT . ' WHERE v.appointment_id = :a', ['a' => $appointmentId]);
    }

    /** @return array<int,array<string,mixed>> Newest first. */
    public static function forPatient(int $patientId): array
    {
        return Database::all(self::SELECT . ' WHERE v.patient_id = :p ORDER BY v.visited_at DESC, v.id DESC', ['p' => $patientId]);
    }

    /** @return array<int,array<string,mixed>> Newest first, for the "recent consultations" panel. */
    public static function recentForDoctor(int $doctorId, int $limit = 8): array
    {
        return Database::all(
            self::SELECT . ' WHERE v.doctor_id = :d ORDER BY v.visited_at DESC, v.id DESC LIMIT ' . (int) $limit,
            ['d' => $doctorId]
        );
    }

    public static function createDraft(int $patientId, int $doctorId, ?int $appointmentId, int $createdBy): int
    {
        return Database::insert(
            "INSERT INTO visits (patient_id, doctor_id, appointment_id, visited_at, status, created_by)
             VALUES (:p, :d, :a, NOW(), 'Draft', :by)",
            ['p' => $patientId, 'd' => $doctorId, 'a' => $appointmentId, 'by' => $createdBy]
        );
    }

    /** @param array<string,mixed> $data keys from FIELDS */
    public static function updateClinical(int $id, array $data): void
    {
        $sets = [];
        $params = ['id' => $id];
        foreach (self::FIELDS as $field) {
            $sets[] = "$field = :$field";
            $params[$field] = $data[$field] ?? null;
        }
        Database::run('UPDATE visits SET ' . implode(', ', $sets) . ' WHERE id = :id', $params);
    }

    public static function finalize(int $id): void
    {
        Database::run("UPDATE visits SET status = 'Finalized', finalized_at = NOW() WHERE id = :id", ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function amendments(int $visitId): array
    {
        return Database::all(
            'SELECT a.*, u.full_name AS amended_by_name FROM visit_amendments a JOIN users u ON u.id = a.amended_by
             WHERE a.visit_id = :v ORDER BY a.amended_at DESC, a.id DESC',
            ['v' => $visitId]
        );
    }

    public static function addAmendment(int $visitId, int $userId, string $reason, array $previous, array $new): void
    {
        Database::run(
            'INSERT INTO visit_amendments (visit_id, amended_by, reason, previous_data, new_data) VALUES (:v, :u, :r, :p, :n)',
            [
                'v' => $visitId,
                'u' => $userId,
                'r' => $reason,
                'p' => json_encode($previous, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'n' => json_encode($new, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]
        );
    }
}
