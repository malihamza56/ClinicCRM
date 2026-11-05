<?php
declare(strict_types=1);

namespace App\Config;

/**
 * Appointment lifecycle and the only allowed moves between statuses.
 * The status describes the appointment itself - visit (consultation) status and
 * invoice/payment status are tracked separately on purpose.
 */
final class AppointmentStatus
{
    public const SCHEDULED   = 'Scheduled';
    public const CONFIRMED   = 'Confirmed';
    public const WAITING     = 'Waiting';
    public const IN_PROGRESS = 'In Progress';
    public const COMPLETED   = 'Completed';
    public const CANCELLED   = 'Cancelled';
    public const NO_SHOW     = 'No Show';

    public const ALL = [
        self::SCHEDULED, self::CONFIRMED, self::WAITING, self::IN_PROGRESS,
        self::COMPLETED, self::CANCELLED, self::NO_SHOW,
    ];

    /** current status => statuses it may move to */
    private const TRANSITIONS = [
        self::SCHEDULED   => [self::CONFIRMED, self::WAITING, self::CANCELLED, self::NO_SHOW],
        self::CONFIRMED   => [self::WAITING, self::CANCELLED, self::NO_SHOW],
        self::WAITING     => [self::IN_PROGRESS, self::CANCELLED, self::NO_SHOW],
        self::IN_PROGRESS => [self::COMPLETED],
        self::COMPLETED   => [],
        self::CANCELLED   => [],
        self::NO_SHOW     => [],
    ];

    /** Statuses that no longer occupy a doctor's time slot. */
    public const FREES_SLOT = [self::CANCELLED, self::NO_SHOW];

    public static function canMove(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    /** @return string[] */
    public static function nextStatuses(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    /** Only appointments that have not started can be moved to another time. */
    public static function canReschedule(string $status): bool
    {
        return in_array($status, [self::SCHEDULED, self::CONFIRMED], true);
    }
}
