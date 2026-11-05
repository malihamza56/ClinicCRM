<?php
declare(strict_types=1);

namespace App\Config;

/**
 * Every permission code the application knows about.
 * Which role holds which permission is stored in the database (tables `permissions` and
 * `role_permissions`, seeded in database/schema.sql) and documented in docs/security.md.
 * A unit test checks that this list and the database never drift apart.
 */
final class Permissions
{
    public const DASHBOARD_VIEW      = 'dashboard.view';
    public const USERS_MANAGE        = 'users.manage';
    public const SPECIALTIES_MANAGE  = 'specialties.manage';
    public const DOCTORS_VIEW        = 'doctors.view';
    public const DOCTORS_MANAGE      = 'doctors.manage';
    public const SERVICES_MANAGE     = 'services.manage';
    public const SETTINGS_MANAGE     = 'settings.manage';
    public const PATIENTS_VIEW       = 'patients.view';
    public const PATIENTS_VIEW_BILLING = 'patients.view_billing';
    public const PATIENTS_CREATE     = 'patients.create';
    public const PATIENTS_UPDATE     = 'patients.update';
    public const PATIENTS_ARCHIVE    = 'patients.archive';
    public const APPOINTMENTS_VIEW   = 'appointments.view';
    public const APPOINTMENTS_MANAGE = 'appointments.manage';
    public const APPOINTMENTS_CONSULT = 'appointments.consult';
    public const QUEUE_VIEW          = 'queue.view';
    public const VISITS_VIEW         = 'visits.view';
    public const VISITS_WRITE        = 'visits.write';
    public const VISITS_AMEND        = 'visits.amend';
    public const FOLLOWUPS_VIEW      = 'followups.view';
    public const FOLLOWUPS_CREATE    = 'followups.create';
    public const FOLLOWUPS_UPDATE    = 'followups.update';
    public const INVOICES_VIEW       = 'invoices.view';
    public const INVOICES_CREATE     = 'invoices.create';
    public const INVOICES_VOID       = 'invoices.void';
    public const PAYMENTS_VIEW       = 'payments.view';
    public const PAYMENTS_RECORD     = 'payments.record';
    public const PAYMENTS_REFUND     = 'payments.refund';
    public const PAYMENTS_ADJUST     = 'payments.adjust';
    public const REPORTS_FINANCIAL   = 'reports.financial';
    public const REPORTS_OPERATIONAL = 'reports.operational';
    public const AUDIT_VIEW          = 'audit.view';
    public const EXPORTS_PATIENTS    = 'exports.patients';
    public const EXPORTS_APPOINTMENTS = 'exports.appointments';
    public const EXPORTS_FINANCIAL   = 'exports.financial';

    /** @return string[] */
    public static function all(): array
    {
        return array_values((new \ReflectionClass(self::class))->getConstants());
    }
}
