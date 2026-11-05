<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Prescription
{
    public static function findByVisit(int $visitId): ?array
    {
        return Database::one('SELECT * FROM prescriptions WHERE visit_id = :v', ['v' => $visitId]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(int $prescriptionId): array
    {
        return Database::all('SELECT * FROM prescription_items WHERE prescription_id = :p ORDER BY sort_order, id', ['p' => $prescriptionId]);
    }

    public static function create(int $visitId, int $patientId, int $doctorId, string $number, ?string $advice): int
    {
        return Database::insert(
            'INSERT INTO prescriptions (visit_id, patient_id, doctor_id, prescription_no, advice) VALUES (:v, :p, :d, :no, :a)',
            ['v' => $visitId, 'p' => $patientId, 'd' => $doctorId, 'no' => $number, 'a' => $advice]
        );
    }

    public static function updateAdvice(int $id, ?string $advice): void
    {
        Database::run('UPDATE prescriptions SET advice = :a WHERE id = :id', ['a' => $advice, 'id' => $id]);
    }

    /** Replaces all medicine lines. Changes to a finalized visit are recorded as amendments by VisitService. */
    public static function replaceItems(int $prescriptionId, array $items): void
    {
        Database::run('DELETE FROM prescription_items WHERE prescription_id = :p', ['p' => $prescriptionId]);
        $order = 1;
        foreach ($items as $item) {
            Database::run(
                'INSERT INTO prescription_items (prescription_id, sort_order, medicine_name, dosage, frequency, duration, instructions)
                 VALUES (:p, :o, :m, :d, :f, :du, :i)',
                [
                    'p'  => $prescriptionId,
                    'o'  => $order++,
                    'm'  => $item['medicine_name'],
                    'd'  => $item['dosage'],
                    'f'  => $item['frequency'],
                    'du' => $item['duration'],
                    'i'  => $item['instructions'] ?? null,
                ]
            );
        }
    }
}
