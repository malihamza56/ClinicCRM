<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\AppointmentStatus as Status;
use App\Core\BusinessRuleException;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Validator;
use App\Models\Appointment;
use App\Models\FollowUp;
use App\Models\Prescription;
use App\Models\Sequence;
use App\Models\Visit;

/**
 * The consultation workflow: start -> save draft -> finalize -> (amend with history).
 * Visit status (Draft/Finalized) is independent from the appointment status.
 */
final class VisitService
{
    public const MAX_MEDICINES = 20;

    /** Plausible-range checks only catch typing mistakes - they are not medical advice. */
    public static function validateClinical(array $input, bool $forFinalize): Validator
    {
        $v = Validator::make($input, [
            'chief_complaint'  => ($forFinalize ? 'required|' : '') . 'max:500',
            'symptoms'         => 'max:3000',
            'bp_systolic'      => 'number:50,260',
            'bp_diastolic'     => 'number:30,160',
            'pulse_bpm'        => 'number:20,250',
            'temperature_c'    => 'number:30,45',
            'respiratory_rate' => 'number:4,80',
            'spo2_percent'     => 'number:50,100',
            'weight_kg'        => 'number:0.5,400',
            'height_cm'        => 'number:20,260',
            'diagnosis'        => ($forFinalize ? 'required|' : '') . 'max:3000',
            'treatment_plan'   => 'max:3000',
            'notes'            => 'max:3000',
            'follow_up_advice' => 'max:255',
            'advice'           => 'max:2000',
        ], [
            'chief_complaint' => 'Chief complaint', 'bp_systolic' => 'Systolic BP', 'bp_diastolic' => 'Diastolic BP',
            'pulse_bpm' => 'Pulse', 'temperature_c' => 'Temperature', 'respiratory_rate' => 'Respiratory rate',
            'spo2_percent' => 'SpO2', 'weight_kg' => 'Weight', 'height_cm' => 'Height', 'treatment_plan' => 'Treatment plan',
            'follow_up_advice' => 'Follow-up advice', 'advice' => 'Prescription advice',
        ]);

        $d = $v->validated();
        foreach (['bp_systolic', 'bp_diastolic', 'pulse_bpm', 'respiratory_rate', 'spo2_percent'] as $wholeNumber) {
            if (($d[$wholeNumber] ?? null) !== null && !isset($v->errors()[$wholeNumber]) && !ctype_digit((string) $d[$wholeNumber])) {
                $v->addError($wholeNumber, 'Please enter a whole number.');
            }
        }
        if (($d['bp_systolic'] === null) !== ($d['bp_diastolic'] === null)) {
            $v->addError($d['bp_systolic'] === null ? 'bp_systolic' : 'bp_diastolic', 'Enter both blood pressure values, or leave both empty.');
        } elseif ($d['bp_systolic'] !== null && !$v->errors() && (int) $d['bp_systolic'] <= (int) $d['bp_diastolic']) {
            $v->addError('bp_systolic', 'Systolic pressure must be higher than diastolic.');
        }

        return $v;
    }

    /**
     * Reads the repeating medicine rows (rx_medicine[], rx_dosage[] ...) from the form.
     * Completely empty rows are ignored.
     *
     * @return array{items:array<int,array<string,?string>>,errors:array<string,string>}
     */
    public static function parseMedicines(array $input): array
    {
        $columns = ['medicine' => 150, 'dosage' => 80, 'frequency' => 80, 'duration' => 60, 'instructions' => 255];
        $lists = [];
        foreach ($columns as $name => $max) {
            $raw = $input['rx_' . $name] ?? [];
            $lists[$name] = is_array($raw) ? array_values($raw) : [];
        }

        $items = [];
        $errors = [];
        $rows = max(array_map('count', $lists));
        $row = 0;
        for ($i = 0; $i < $rows; $i++) {
            $values = [];
            foreach ($columns as $name => $max) {
                $cell = $lists[$name][$i] ?? '';
                $values[$name] = is_string($cell) ? trim($cell) : '';
            }
            if (implode('', $values) === '') {
                continue;
            }
            $row++;
            foreach (['medicine' => 'medicine name', 'dosage' => 'dosage', 'frequency' => 'frequency', 'duration' => 'duration'] as $name => $label) {
                if ($values[$name] === '') {
                    $errors['rx'] = "Medicine row $row: $label is required.";
                }
            }
            foreach ($columns as $name => $max) {
                if (mb_strlen($values[$name]) > $max) {
                    $errors['rx'] = "Medicine row $row: $name must not be longer than $max characters.";
                }
            }
            $items[] = [
                'medicine_name' => $values['medicine'], 'dosage' => $values['dosage'], 'frequency' => $values['frequency'],
                'duration' => $values['duration'], 'instructions' => $values['instructions'] !== '' ? $values['instructions'] : null,
            ];
        }
        if (count($items) > self::MAX_MEDICINES) {
            $errors['rx'] = 'A prescription can contain at most ' . self::MAX_MEDICINES . ' medicines.';
        }

        return ['items' => $items, 'errors' => $errors];
    }

    /** Starts (or resumes) the consultation for a patient who is waiting. @return int visit id @throws BusinessRuleException */
    public static function start(int $appointmentId, int $doctorId, int $userId): int
    {
        return Database::transaction(static function () use ($appointmentId, $doctorId, $userId): int {
            $appointment = Appointment::findForUpdate($appointmentId) ?? throw new HttpException(404);
            if ((int) $appointment['doctor_id'] !== $doctorId) {
                throw new HttpException(403);
            }
            $existing = Visit::findByAppointment($appointmentId);
            if ($existing !== null) {
                return (int) $existing['id'];
            }
            if ($appointment['status'] !== Status::WAITING) {
                throw new BusinessRuleException('Only patients in the waiting queue can be called in.');
            }

            Appointment::setStatus($appointmentId, Status::IN_PROGRESS);
            $visitId = Visit::createDraft((int) $appointment['patient_id'], $doctorId, $appointmentId, $userId);
            AuditService::log('visit.started', 'visit', $visitId, 'Started consultation', ['appointment_id' => $appointmentId]);

            return $visitId;
        });
    }

    /**
     * Saves a Draft visit; with $finalize = true it also locks the record, completes the appointment
     * and creates the follow-up the doctor asked for.
     *
     * @param array<string,mixed> $clinical    validated clinical fields (+ 'advice')
     * @param array<int,array<string,?string>> $medicines
     * @param array{date:?string,notes:?string} $followUp
     * @throws BusinessRuleException
     */
    public static function saveDraft(int $visitId, array $clinical, array $medicines, array $followUp, bool $finalize, int $userId): void
    {
        Database::transaction(static function () use ($visitId, $clinical, $medicines, $followUp, $finalize, $userId): void {
            $visit = Visit::findForUpdate($visitId) ?? throw new HttpException(404);
            if ($visit['status'] !== 'Draft') {
                throw new BusinessRuleException('This consultation is already finalized. Use "Amend" to change it.');
            }

            Visit::updateClinical($visitId, self::clinicalRow($clinical));
            self::storePrescription($visit, $medicines, $clinical['advice'] ?? null);

            if (!$finalize) {
                return;
            }
            Visit::finalize($visitId);
            if ($visit['appointment_id'] !== null) {
                $appointment = Appointment::findForUpdate((int) $visit['appointment_id']);
                if ($appointment !== null && Status::canMove((string) $appointment['status'], Status::COMPLETED)) {
                    Appointment::setStatus((int) $visit['appointment_id'], Status::COMPLETED);
                }
            }
            if (!empty($followUp['date'])) {
                $id = FollowUp::create((int) $visit['patient_id'], $visitId, (int) $visit['doctor_id'], $followUp['date'], $followUp['notes'], $userId);
                AuditService::log('followup.created', 'follow_up', $id, 'Follow-up created from consultation', ['date' => $followUp['date']]);
            }
            AuditService::log('visit.finalized', 'visit', $visitId, 'Finalized consultation');
        });
    }

    /**
     * Changes a FINALIZED visit. The previous content is preserved in visit_amendments together with who, when and why.
     *
     * @throws BusinessRuleException
     */
    public static function amend(int $visitId, array $clinical, array $medicines, string $reason, int $userId): void
    {
        Database::transaction(static function () use ($visitId, $clinical, $medicines, $reason, $userId): void {
            $visit = Visit::findForUpdate($visitId) ?? throw new HttpException(404);
            if ($visit['status'] !== 'Finalized') {
                throw new BusinessRuleException('Only finalized consultations are amended. Draft consultations can simply be edited.');
            }

            $before = self::snapshot($visitId);
            $newClinical = self::clinicalRow($clinical);
            $after = [
                'clinical'     => self::normaliseClinical($newClinical),
                'prescription' => ['advice' => $clinical['advice'] ?? null, 'items' => $medicines],
            ];
            if ($before == $after) {
                throw new BusinessRuleException('No changes were made, so there is nothing to amend.');
            }

            Visit::updateClinical($visitId, $newClinical);
            self::storePrescription($visit, $medicines, $clinical['advice'] ?? null);
            Visit::addAmendment($visitId, $userId, $reason, $before, $after);
            AuditService::log('visit.amended', 'visit', $visitId, 'Amended finalized consultation', ['reason' => $reason]);
        });
    }

    /** Current clinical content of a visit in the same normalised shape used for amendment history. @return array<string,mixed> */
    public static function snapshot(int $visitId): array
    {
        $visit = Visit::find($visitId) ?? throw new HttpException(404);
        $prescription = Prescription::findByVisit($visitId);
        $items = $prescription === null ? [] : array_map(
            static fn (array $i): array => [
                'medicine_name' => $i['medicine_name'], 'dosage' => $i['dosage'], 'frequency' => $i['frequency'],
                'duration' => $i['duration'], 'instructions' => $i['instructions'],
            ],
            Prescription::items((int) $prescription['id'])
        );

        return [
            'clinical'     => self::normaliseClinical(array_intersect_key($visit, array_flip(Visit::FIELDS))),
            'prescription' => ['advice' => $prescription['advice'] ?? null, 'items' => $items],
        ];
    }

    /** @param array<string,mixed> $clinical @return array<string,mixed> only the visit columns, decimals fixed to one place */
    private static function clinicalRow(array $clinical): array
    {
        $row = array_intersect_key($clinical, array_flip(Visit::FIELDS));
        foreach (['temperature_c', 'weight_kg', 'height_cm'] as $decimal) {
            if (($row[$decimal] ?? null) !== null) {
                $row[$decimal] = number_format((float) $row[$decimal], 1, '.', '');
            }
        }
        foreach (['bp_systolic', 'bp_diastolic', 'pulse_bpm', 'respiratory_rate', 'spo2_percent'] as $whole) {
            if (($row[$whole] ?? null) !== null) {
                $row[$whole] = (string) (int) $row[$whole];
            }
        }

        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,?string> */
    private static function normaliseClinical(array $row): array
    {
        $out = [];
        foreach (Visit::FIELDS as $field) {
            $value = $row[$field] ?? null;
            $out[$field] = $value === null || $value === '' ? null : (string) $value;
        }

        return $out;
    }

    /** @param array<string,mixed> $visit @param array<int,array<string,?string>> $medicines */
    private static function storePrescription(array $visit, array $medicines, ?string $advice): void
    {
        $prescription = Prescription::findByVisit((int) $visit['id']);
        if ($prescription === null) {
            if ($medicines === [] && $advice === null) {
                return;
            }
            $number = sprintf('RX-%06d', Sequence::next(Sequence::PRESCRIPTION));
            $id = Prescription::create((int) $visit['id'], (int) $visit['patient_id'], (int) $visit['doctor_id'], $number, $advice);
        } else {
            $id = (int) $prescription['id'];
            Prescription::updateAdvice($id, $advice);
        }
        Prescription::replaceItems($id, $medicines);
    }
}
