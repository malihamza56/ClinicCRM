<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\BusinessRuleException;
use App\Core\Validator;
use App\Models\FollowUp;

/** Follow-up reminders. They are tracked on screen only - no SMS/email is sent (a documented future enhancement). */
final class FollowUpService
{
    public static function validate(array $input): Validator
    {
        return Validator::make($input, [
            'follow_up_date' => 'required|date|date_not_past',
            'notes'          => 'max:500',
        ], ['follow_up_date' => 'Follow-up date', 'notes' => 'Notes']);
    }

    public static function create(int $patientId, ?int $visitId, int $doctorId, string $date, ?string $notes, int $userId): int
    {
        $id = FollowUp::create($patientId, $visitId, $doctorId, $date, $notes, $userId);
        AuditService::log('followup.created', 'follow_up', $id, "Follow-up planned for $date", ['patient_id' => $patientId]);

        return $id;
    }

    /** @throws BusinessRuleException */
    public static function setStatus(int $id, string $status): void
    {
        $followUp = FollowUp::find($id) ?? throw new BusinessRuleException('Follow-up not found.');
        if ($followUp['status'] !== 'Pending') {
            throw new BusinessRuleException('This follow-up is already ' . strtolower((string) $followUp['status']) . '.');
        }
        if (!in_array($status, ['Completed', 'Cancelled'], true)) {
            throw new BusinessRuleException('Invalid follow-up status.');
        }
        FollowUp::setStatus($id, $status);
        AuditService::log('followup.' . strtolower($status), 'follow_up', $id, "Follow-up marked $status");
    }
}
