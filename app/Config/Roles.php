<?php
declare(strict_types=1);

namespace App\Config;

/** Role codes in one place so no controller ever hard-codes a role string. Must match the `roles` table. */
final class Roles
{
    public const ADMIN        = 'admin';
    public const DOCTOR       = 'doctor';
    public const RECEPTIONIST = 'receptionist';
    public const ACCOUNTANT   = 'accountant';
    /** Reserved for the future patient portal. Patient accounts cannot sign in to the staff app yet. */
    public const PATIENT      = 'patient';

    /** Roles that may be assigned to staff accounts in the MVP. */
    public const STAFF = [self::ADMIN, self::DOCTOR, self::RECEPTIONIST, self::ACCOUNTANT];

    public const LABELS = [
        self::ADMIN        => 'Clinic Admin',
        self::DOCTOR       => 'Doctor',
        self::RECEPTIONIST => 'Receptionist',
        self::ACCOUNTANT   => 'Accountant',
        self::PATIENT      => 'Patient',
    ];
}
