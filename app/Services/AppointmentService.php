<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\AppointmentStatus as Status;
use App\Core\BusinessRuleException;
use App\Core\Database;
use App\Core\Validator;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;

/**
 * Scheduling rules: availability, double-booking protection, walk-ins, queue and status changes.
 * Time slots are computed from the doctor's weekly schedule, clinic hours and existing bookings.
 */
final class AppointmentService
{
    public static function validateBooking(array $input): Validator
    {
        return Validator::make($input, [
            'patient_id'       => 'required|int',
            'doctor_id'        => 'required|int',
            'appointment_date' => 'required|date|date_not_past',
            'start_time'       => 'required|time',
            'reason'           => 'max:255',
        ], ['patient_id' => 'Patient', 'doctor_id' => 'Doctor', 'appointment_date' => 'Date', 'start_time' => 'Time slot', 'reason' => 'Reason for visit']);
    }

    /**
     * Every bookable slot of a doctor on a date.
     *
     * @return array<int,array{start:string,end:string,available:bool}>
     */
    public static function slots(int $doctorId, string $date, ?int $exceptAppointmentId = null): array
    {
        $doctor = Doctor::find($doctorId);
        if ($doctor === null || !(bool) $doctor['is_active'] || !(bool) $doctor['account_active'] || $date < date('Y-m-d')) {
            return [];
        }

        $weekday = (int) date('N', strtotime($date));
        $opening = self::minutes(setting('opening_time', '09:00'));
        $closing = self::minutes(setting('closing_time', '21:00'));
        $length = (int) $doctor['slot_minutes'];
        $busy = array_map(
            static fn (array $r): array => [self::minutes((string) $r['start_time']), self::minutes((string) $r['end_time'])],
            Appointment::occupied($doctorId, $date, $exceptAppointmentId)
        );
        $nowMinutes = $date === date('Y-m-d') ? self::minutes(date('H:i')) : -1;

        $slots = [];
        foreach (Doctor::schedulesForWeekday($doctorId, $weekday) as $window) {
            $cursor = max(self::minutes((string) $window['start_time']), $opening);
            $limit = min(self::minutes((string) $window['end_time']), $closing);
            while ($cursor + $length <= $limit) {
                $end = $cursor + $length;
                $overlaps = false;
                foreach ($busy as [$busyStart, $busyEnd]) {
                    if ($cursor < $busyEnd && $end > $busyStart) {
                        $overlaps = true;
                        break;
                    }
                }
                $slots[] = [
                    'start'     => self::hhmm($cursor),
                    'end'       => self::hhmm($end),
                    'available' => !$overlaps && $cursor > $nowMinutes,
                ];
                $cursor = $end;
            }
        }

        return $slots;
    }

    /** @throws BusinessRuleException */
    public static function book(int $patientId, int $doctorId, string $date, string $start, ?string $reason, int $userId): int
    {
        self::assertBookable($patientId, $doctorId);

        return Database::transaction(static function () use ($patientId, $doctorId, $date, $start, $reason, $userId): int {
            // Locking the doctor row makes simultaneous bookings for the same doctor queue up one after another.
            Doctor::lock($doctorId);
            $slot = self::availableSlot($doctorId, $date, $start, null);
            if (Appointment::patientHasActiveBooking($patientId, $doctorId, $date)) {
                throw new BusinessRuleException('This patient already has an appointment with this doctor on that date.');
            }

            try {
                $id = Appointment::create([
                    'patient_id' => $patientId, 'doctor_id' => $doctorId, 'appointment_date' => $date,
                    'start_time' => $slot['start'], 'end_time' => $slot['end'], 'booking_type' => 'Scheduled',
                    'status' => Status::SCHEDULED, 'reason' => $reason,
                ], $userId);
            } catch (\PDOException $e) {
                if (($e->errorInfo[1] ?? 0) === 1062) { // database-level double-booking guard
                    throw new BusinessRuleException('That time slot was just taken. Please choose another.');
                }
                throw $e;
            }
            AuditService::log('appointment.created', 'appointment', $id, "Booked appointment for $date $start");

            return $id;
        });
    }

    /** Registers a patient who arrived without a booking and puts them straight into the queue. @throws BusinessRuleException */
    public static function registerWalkIn(int $patientId, int $doctorId, ?string $reason, int $userId): int
    {
        self::assertBookable($patientId, $doctorId);
        $doctor = Doctor::find($doctorId);
        $today = date('Y-m-d');
        $now = date('H:i');

        if ($now < setting('opening_time', '09:00') || $now >= setting('closing_time', '21:00')) {
            throw new BusinessRuleException('The clinic is closed right now, so a walk-in cannot be registered.');
        }
        $onDuty = false;
        foreach (Doctor::schedulesForWeekday($doctorId, (int) date('N')) as $window) {
            if ($now >= substr((string) $window['start_time'], 0, 5) && $now < substr((string) $window['end_time'], 0, 5)) {
                $onDuty = true;
            }
        }
        if (!$onDuty) {
            throw new BusinessRuleException($doctor['full_name'] . ' is not on duty at this time.');
        }

        return Database::transaction(static function () use ($patientId, $doctorId, $reason, $userId, $today, $now, $doctor): int {
            Doctor::lock($doctorId);
            if (Appointment::patientHasActiveBooking($patientId, $doctorId, $today)) {
                throw new BusinessRuleException('This patient already has an appointment today with this doctor. Check them in from the queue page instead.');
            }
            $end = min(self::hhmm(self::minutes($now) + (int) $doctor['slot_minutes']), '23:59');
            $queue = Appointment::nextQueueNumber($doctorId, $today);
            $id = Appointment::create([
                'patient_id' => $patientId, 'doctor_id' => $doctorId, 'appointment_date' => $today,
                'start_time' => $now, 'end_time' => $end, 'booking_type' => 'Walk-in', 'status' => Status::WAITING,
                'reason' => $reason, 'queue_number' => $queue, 'checked_in_at' => date('Y-m-d H:i:s'),
            ], $userId);
            AuditService::log('appointment.walk_in', 'appointment', $id, "Walk-in registered, queue #$queue");

            return $id;
        });
    }

    /** @throws BusinessRuleException */
    public static function reschedule(int $id, string $date, string $start, int $userId): void
    {
        Database::transaction(static function () use ($id, $date, $start, $userId): void {
            $appointment = Appointment::findForUpdate($id) ?? throw new BusinessRuleException('Appointment not found.');
            if ($appointment['booking_type'] !== 'Scheduled' || !Status::canReschedule((string) $appointment['status'])) {
                throw new BusinessRuleException('Only scheduled or confirmed appointments can be rescheduled.');
            }
            if ($date < date('Y-m-d')) {
                throw new BusinessRuleException('You cannot move an appointment to a past date.');
            }
            $doctorId = (int) $appointment['doctor_id'];
            Doctor::lock($doctorId);
            $slot = self::availableSlot($doctorId, $date, $start, $id);
            if (Appointment::patientHasActiveBooking((int) $appointment['patient_id'], $doctorId, $date, $id)) {
                throw new BusinessRuleException('This patient already has another appointment with this doctor on that date.');
            }

            try {
                Appointment::reschedule($id, $date, $slot['start'], $slot['end']);
            } catch (\PDOException $e) {
                if (($e->errorInfo[1] ?? 0) === 1062) {
                    throw new BusinessRuleException('That time slot was just taken. Please choose another.');
                }
                throw $e;
            }
            AuditService::log('appointment.rescheduled', 'appointment', $id, 'Rescheduled appointment', [
                'from' => $appointment['appointment_date'] . ' ' . substr((string) $appointment['start_time'], 0, 5),
                'to'   => "$date $start",
            ]);
        });
    }

    /**
     * Moves an appointment along its lifecycle. "In Progress" and "Completed" are set only by the
     * consultation workflow (VisitService), never directly from a button.
     *
     * @throws BusinessRuleException
     */
    public static function changeStatus(int $id, string $to, int $userId, ?string $reason = null): void
    {
        Database::transaction(static function () use ($id, $to, $reason): void {
            $appointment = Appointment::findForUpdate($id) ?? throw new BusinessRuleException('Appointment not found.');
            $from = (string) $appointment['status'];

            if (!Status::canMove($from, $to)) {
                throw new BusinessRuleException("An appointment that is \"$from\" cannot be changed to \"$to\".");
            }
            if (in_array($to, [Status::IN_PROGRESS, Status::COMPLETED], true)) {
                throw new BusinessRuleException('This status is set automatically when the doctor starts or finishes the consultation.');
            }
            if ($to === Status::CANCELLED && ($reason === null || trim($reason) === '')) {
                throw new BusinessRuleException('Please enter a cancellation reason.');
            }
            if ($to === Status::NO_SHOW && $appointment['appointment_date'] > date('Y-m-d')) {
                throw new BusinessRuleException('Only appointments dated today or earlier can be marked as no-show.');
            }

            if ($to === Status::WAITING) {
                if ($appointment['appointment_date'] !== date('Y-m-d')) {
                    throw new BusinessRuleException('Patients can only be checked in on the day of their appointment.');
                }
                Doctor::lock((int) $appointment['doctor_id']);
                Appointment::checkIn($id, Appointment::nextQueueNumber((int) $appointment['doctor_id'], $appointment['appointment_date']));
            } else {
                Appointment::setStatus($id, $to, $reason !== null ? mb_substr(trim($reason), 0, 255) : null);
            }
            AuditService::log('appointment.status_changed', 'appointment', $id, "Status $from -> $to", $reason ? ['reason' => $reason] : null);
        });
    }

    /** @throws BusinessRuleException */
    private static function assertBookable(int $patientId, int $doctorId): void
    {
        $patient = Patient::find($patientId) ?? throw new BusinessRuleException('Patient not found.');
        if ((bool) $patient['is_archived']) {
            throw new BusinessRuleException('This patient is archived. Restore the patient before booking.');
        }
        $doctor = Doctor::find($doctorId);
        if ($doctor === null || !(bool) $doctor['is_active'] || !(bool) $doctor['account_active']) {
            throw new BusinessRuleException('This doctor is not available for appointments.');
        }
    }

    /**
     * @return array{start:string,end:string,available:bool}
     * @throws BusinessRuleException when the requested start time is not an open slot
     */
    private static function availableSlot(int $doctorId, string $date, string $start, ?int $exceptId): array
    {
        foreach (self::slots($doctorId, $date, $exceptId) as $slot) {
            if ($slot['start'] === $start) {
                if (!$slot['available']) {
                    throw new BusinessRuleException('That time slot is no longer available. Please choose another.');
                }

                return $slot;
            }
        }
        throw new BusinessRuleException('The doctor does not work at that time on the chosen date.');
    }

    private static function minutes(string $time): int
    {
        return ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
    }

    private static function hhmm(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
