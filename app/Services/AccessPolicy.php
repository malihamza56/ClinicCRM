<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Permissions;
use App\Config\Roles;
use App\Core\Auth;
use App\Core\HttpException;
use App\Models\Patient;

/**
 * "Record ownership" rules - the part of authorization that a permission code alone cannot express.
 *
 *  - A doctor sees only their own appointments and queue.
 *  - A doctor sees a patient's record (demographics + clinical history) only while they have a
 *    care relationship with that patient: a booked appointment or a recorded visit.
 *  - Clinical content is available only to doctors. Admins, receptionists and accountants never see it.
 */
final class AccessPolicy
{
    /** The doctor profile id the current user is restricted to, or null when they may see all doctors' data. */
    public static function doctorScope(): ?int
    {
        if (Auth::isRole(Roles::DOCTOR)) {
            // A doctor account without an active profile can see nothing (-1 matches no doctor).
            return Auth::doctorId() ?? -1;
        }

        return null;
    }

    public static function requireAppointmentAccess(array $appointment): void
    {
        $scope = self::doctorScope();
        if ($scope !== null && (int) $appointment['doctor_id'] !== $scope) {
            throw new HttpException(403);
        }
    }

    /** Front-desk style view of a patient (demographics). Doctors are limited by care relationship. */
    public static function requirePatientDemographics(int $patientId): void
    {
        if (!Auth::can(Permissions::PATIENTS_VIEW)) {
            throw new HttpException(403);
        }
        $scope = self::doctorScope();
        if ($scope !== null && !Patient::hasCareRelationship($scope, $patientId)) {
            throw new HttpException(403);
        }
    }

    public static function canViewClinical(int $patientId): bool
    {
        $doctorId = Auth::doctorId();

        return Auth::can(Permissions::VISITS_VIEW) && $doctorId !== null && Patient::hasCareRelationship($doctorId, $patientId);
    }

    public static function requireClinicalAccess(int $patientId): void
    {
        if (!self::canViewClinical($patientId)) {
            throw new HttpException(403);
        }
    }

    /** Only the treating doctor may edit a visit. */
    public static function requireVisitOwner(array $visit): void
    {
        $doctorId = Auth::doctorId();
        if ($doctorId === null || (int) $visit['doctor_id'] !== $doctorId) {
            throw new HttpException(403);
        }
    }
}
