<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessRuleException;
use App\Core\Database;
use App\Core\Validator;
use App\Models\Patient;
use App\Models\Sequence;

final class PatientService
{
    public const GENDERS = ['Male', 'Female', 'Other'];
    public const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    public static function validate(array $input): Validator
    {
        $v = Validator::make($input, [
            'first_name'              => 'required|name|max:80',
            'last_name'               => 'required|name|max:80',
            'gender'                  => 'required|in:' . implode(',', self::GENDERS),
            'date_of_birth'           => 'required|date|date_not_future',
            'phone'                   => 'required|phone',
            'email'                   => 'email|max:190',
            'address'                 => 'max:255',
            'city'                    => 'max:80',
            'blood_group'             => 'in:' . implode(',', self::BLOOD_GROUPS),
            'emergency_contact_name'  => 'name|max:120',
            'emergency_contact_phone' => 'phone',
        ], ['date_of_birth' => 'Date of birth', 'emergency_contact_name' => 'Emergency contact name', 'emergency_contact_phone' => 'Emergency contact phone']);

        $dob = $v->validated()['date_of_birth'] ?? null;
        if ($dob !== null && !isset($v->errors()['date_of_birth']) && $dob < date('Y-m-d', strtotime('-130 years'))) {
            $v->addError('date_of_birth', 'Date of birth is not plausible.');
        }

        return $v;
    }

    /** Tidies validated input before saving: collapses spaces, strips phone formatting. @return array<string,mixed> */
    public static function normalise(array $data): array
    {
        foreach (['first_name', 'last_name', 'emergency_contact_name', 'city', 'address'] as $field) {
            if ($data[$field] !== null) {
                $data[$field] = preg_replace('/\s+/', ' ', (string) $data[$field]);
            }
        }
        foreach (['phone', 'emergency_contact_phone'] as $field) {
            if ($data[$field] !== null) {
                $data[$field] = preg_replace('/[\s\-]+/', '', (string) $data[$field]);
            }
        }
        if ($data['email'] !== null) {
            $data['email'] = strtolower((string) $data['email']);
        }

        return $data;
    }

    /** @return array{id:int,code:string} @throws BusinessRuleException when the patient already exists */
    public static function register(array $data, int $userId): array
    {
        $data = self::normalise($data);

        return Database::transaction(static function () use ($data, $userId): array {
            self::assertNotDuplicate($data, null);
            $code = sprintf('PT-%06d', Sequence::next(Sequence::PATIENT));

            try {
                $id = Patient::create($data, $code, $userId);
            } catch (\PDOException $e) {
                // The UNIQUE key is the final guard when two receptionists submit at the same moment.
                if (($e->errorInfo[1] ?? 0) === 1062) {
                    throw new BusinessRuleException('This patient is already registered.');
                }
                throw $e;
            }
            AuditService::log('patient.created', 'patient', $id, "Registered patient $code");

            return ['id' => $id, 'code' => $code];
        });
    }

    /** @throws BusinessRuleException */
    public static function update(int $id, array $data): void
    {
        $data = self::normalise($data);
        self::assertNotDuplicate($data, $id);
        Patient::update($id, $data);
        AuditService::log('patient.updated', 'patient', $id, 'Updated patient details');
    }

    /** @throws BusinessRuleException */
    public static function archive(int $id, int $userId): void
    {
        $open = (int) Database::value(
            "SELECT COUNT(*) FROM appointments WHERE patient_id = :p AND appointment_date >= CURDATE() AND status IN ('Scheduled','Confirmed','Waiting','In Progress')",
            ['p' => $id]
        );
        if ($open > 0) {
            throw new BusinessRuleException("This patient still has $open upcoming appointment(s). Cancel them before archiving.");
        }
        Patient::setArchived($id, true, $userId);
        AuditService::log('patient.archived', 'patient', $id, 'Archived patient (history kept)');
    }

    public static function restore(int $id, int $userId): void
    {
        Patient::setArchived($id, false, $userId);
        AuditService::log('patient.restored', 'patient', $id, 'Restored patient');
    }

    /** @throws BusinessRuleException */
    private static function assertNotDuplicate(array $data, ?int $exceptId): void
    {
        $duplicate = Patient::duplicateOf($data, $exceptId);
        if ($duplicate !== null) {
            throw new BusinessRuleException(
                'A patient with the same name, date of birth and phone already exists (' . $duplicate['patient_code'] . ').',
                ['duplicate_id' => (int) $duplicate['id']]
            );
        }
    }
}
