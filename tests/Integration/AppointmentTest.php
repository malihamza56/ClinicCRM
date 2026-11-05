<?php
declare(strict_types=1);

use App\Core\BusinessRuleException;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Setting;
use App\Services\AppointmentService;
use Tests\Support\Fixtures;
use Tests\Support\Tester;

return static function (Tester $t): void {
    [$before, $after] = $GLOBALS['__test_hooks'];
    $t->group('Integration: appointments, slots, queue', $before, $after);

    $book = fn (array $p, array $d, string $date, string $start, int $by) => AppointmentService::book((int) $p['id'], (int) $d['id'], $date, $start, 'Check-up', $by);

    $t->test('slots follow the doctor schedule and visit length', function () use ($t) {
        $d = Fixtures::doctor(15);                      // 09:00-21:00, 15 minutes
        $slots = AppointmentService::slots((int) $d['id'], Fixtures::futureDate());
        $t->same(48, count($slots));
        $t->same('09:00', $slots[0]['start']);
        $t->same('20:45', $slots[47]['start']);
        $t->same('21:00', $slots[47]['end']);
        $t->true($slots[0]['available']);
        $d30 = Fixtures::doctor(30);
        $t->same(24, count(AppointmentService::slots((int) $d30['id'], Fixtures::futureDate())));
    });
    $t->test('no slots on days without a schedule, in the past, or for inactive doctors', function () use ($t) {
        $d = Fixtures::doctor(15, '1000.00', false);
        $t->same([], AppointmentService::slots((int) $d['id'], Fixtures::futureDate()));
        $d2 = Fixtures::doctor();
        $t->same([], AppointmentService::slots((int) $d2['id'], date('Y-m-d', strtotime('-1 day'))));
        Doctor::setActive((int) $d2['id'], false);
        $t->same([], AppointmentService::slots((int) $d2['id'], Fixtures::futureDate()));
    });
    $t->test('booking succeeds, then the same slot is refused', function () use ($t, $book) {
        $admin = Fixtures::user('receptionist');
        $d = Fixtures::doctor();
        $date = Fixtures::futureDate();
        $id = $book(Fixtures::patient(), $d, $date, '10:00', (int) $admin['id']);
        $row = Appointment::find($id);
        $t->same('Scheduled', $row['status']);
        $t->same('10:00:00', $row['start_time']);
        $t->same('10:15:00', $row['end_time']);
        $t->throws(BusinessRuleException::class, fn () => $book(Fixtures::patient(), $d, $date, '10:00', (int) $admin['id']), 'no longer available');
        // the slot shows as taken
        $taken = array_values(array_filter(AppointmentService::slots((int) $d['id'], $date), fn ($s) => $s['start'] === '10:00'));
        $t->false($taken[0]['available']);
    });
    $t->test('cancelling frees the slot for someone else', function () use ($t, $book) {
        $u = Fixtures::user('receptionist');
        $d = Fixtures::doctor();
        $date = Fixtures::futureDate();
        $id = $book(Fixtures::patient(), $d, $date, '11:00', (int) $u['id']);
        AppointmentService::changeStatus($id, 'Cancelled', (int) $u['id'], 'Patient called');
        $second = $book(Fixtures::patient(), $d, $date, '11:00', (int) $u['id']);
        $t->true($second > $id);
    });
    $t->test('overlapping bookings are detected by time range, not only start time', function () use ($t) {
        $u = Fixtures::user('receptionist');
        $d = Fixtures::doctor(15);
        $date = Fixtures::futureDate();
        Appointment::create(['patient_id' => Fixtures::patient()['id'], 'doctor_id' => $d['id'], 'appointment_date' => $date, 'start_time' => '10:00', 'end_time' => '10:30', 'booking_type' => 'Scheduled', 'status' => 'Scheduled'], (int) $u['id']);
        $map = [];
        foreach (AppointmentService::slots((int) $d['id'], $date) as $s) { $map[$s['start']] = $s['available']; }
        $t->false($map['10:00']);
        $t->false($map['10:15'], 'overlaps the 30-minute booking');
        $t->true($map['10:30']);
        $t->true($map['09:45']);
    });
    $t->test('invalid requests: time outside schedule, archived patient, inactive doctor, same-day double booking', function () use ($t, $book) {
        $u = Fixtures::user('receptionist');
        $d = Fixtures::doctor();
        $date = Fixtures::futureDate();
        $t->throws(BusinessRuleException::class, fn () => $book(Fixtures::patient(), $d, $date, '03:00', (int) $u['id']), 'does not work');
        $t->throws(BusinessRuleException::class, fn () => $book(Fixtures::patient(), $d, $date, '10:07', (int) $u['id']), 'does not work');
        $archived = Fixtures::patient();
        \App\Models\Patient::setArchived((int) $archived['id'], true, (int) $u['id']);
        $t->throws(BusinessRuleException::class, fn () => $book($archived, $d, $date, '10:00', (int) $u['id']), 'archived');
        $p = Fixtures::patient();
        $book($p, $d, $date, '12:00', (int) $u['id']);
        $t->throws(BusinessRuleException::class, fn () => $book($p, $d, $date, '12:15', (int) $u['id']), 'already has an appointment');
        Doctor::setActive((int) $d['id'], false);
        $t->throws(BusinessRuleException::class, fn () => $book(Fixtures::patient(), $d, $date, '13:00', (int) $u['id']), 'not available');
    });
    $t->test('booking validation rejects past dates and bad times', function () use ($t) {
        $v = AppointmentService::validateBooking(['patient_id' => '1', 'doctor_id' => '1', 'appointment_date' => date('Y-m-d', strtotime('-1 day')), 'start_time' => '10:00']);
        $t->true(isset($v->errors()['appointment_date']));
        $v = AppointmentService::validateBooking(['patient_id' => '1', 'doctor_id' => '1', 'appointment_date' => Fixtures::futureDate(), 'start_time' => '25:99']);
        $t->true(isset($v->errors()['start_time']));
    });
    $t->test('rescheduling moves the appointment and respects other bookings', function () use ($t, $book) {
        $u = Fixtures::user('receptionist');
        $d = Fixtures::doctor();
        $date = Fixtures::futureDate();
        $id = $book(Fixtures::patient(), $d, $date, '10:00', (int) $u['id']);
        $book(Fixtures::patient(), $d, $date, '11:00', (int) $u['id']);
        $t->throws(BusinessRuleException::class, fn () => AppointmentService::reschedule($id, $date, '11:00', (int) $u['id']), 'no longer available');
        AppointmentService::reschedule($id, $date, '10:15', (int) $u['id']);      // can move within the day
        AppointmentService::reschedule($id, $date, '10:15', (int) $u['id']);      // moving onto its own slot is fine
        $t->same('10:15:00', Appointment::find($id)['start_time']);
        $t->throws(BusinessRuleException::class, fn () => AppointmentService::reschedule($id, date('Y-m-d', strtotime('-1 day')), '10:00', (int) $u['id']), 'past');
        // the old slot is free again
        $book(Fixtures::patient(), $d, $date, '10:00', (int) $u['id']);
        $t->true(true);
    });
    $t->test('status machine: valid steps, required reasons, forbidden shortcuts', function () use ($t, $book) {
        $u = Fixtures::user('receptionist');
        $d = Fixtures::doctor();
        $id = $book(Fixtures::patient(), $d, Fixtures::futureDate(), '10:00', (int) $u['id']);
        AppointmentService::changeStatus($id, 'Confirmed', (int) $u['id']);
        $t->same('Confirmed', Appointment::find($id)['status']);
        $t->throws(BusinessRuleException::class, fn () => AppointmentService::changeStatus($id, 'Completed', (int) $u['id']), 'cannot be changed');
        $t->throws(BusinessRuleException::class, fn () => AppointmentService::changeStatus($id, 'In Progress', (int) $u['id']), 'cannot be changed');
        // even for a patient who IS waiting, "In Progress" can only come from the consultation workflow
        $waiting = Appointment::create(['patient_id' => Fixtures::patient()['id'], 'doctor_id' => $d['id'], 'appointment_date' => date('Y-m-d'), 'start_time' => '09:00', 'end_time' => '09:15', 'booking_type' => 'Scheduled', 'status' => 'Waiting', 'queue_number' => 1], (int) $u['id']);
        $t->throws(BusinessRuleException::class, fn () => AppointmentService::changeStatus($waiting, 'In Progress', (int) $u['id']), 'automatically');
        $t->throws(BusinessRuleException::class, fn () => AppointmentService::changeStatus($id, 'Cancelled', (int) $u['id'], '  '), 'reason');
        $t->throws(BusinessRuleException::class, fn () => AppointmentService::changeStatus($id, 'Waiting', (int) $u['id']), 'day of');
        $t->throws(BusinessRuleException::class, fn () => AppointmentService::changeStatus($id, 'No Show', (int) $u['id']), 'today or earlier');
        AppointmentService::changeStatus($id, 'Cancelled', (int) $u['id'], 'Patient travelling');
        $row = Appointment::find($id);
        $t->same('Cancelled', $row['status']);
        $t->same('Patient travelling', $row['cancellation_reason']);
        $t->throws(BusinessRuleException::class, fn () => AppointmentService::changeStatus($id, 'Confirmed', (int) $u['id']), 'cannot be changed');
    });
    $t->test('check-in assigns queue numbers per doctor per day, in arrival order', function () use ($t) {
        $u = Fixtures::user('receptionist');
        $d1 = Fixtures::doctor();
        $d2 = Fixtures::doctor();
        $today = date('Y-m-d');
        $mk = fn (array $d, string $start) => Appointment::create(['patient_id' => Fixtures::patient()['id'], 'doctor_id' => $d['id'], 'appointment_date' => $today, 'start_time' => $start, 'end_time' => date('H:i', strtotime($start) + 900), 'booking_type' => 'Scheduled', 'status' => 'Scheduled'], (int) $u['id']);
        $a = $mk($d1, '09:00'); $b = $mk($d1, '09:15'); $c = $mk($d2, '09:00');
        AppointmentService::changeStatus($b, 'Waiting', (int) $u['id']);
        AppointmentService::changeStatus($a, 'Waiting', (int) $u['id']);
        AppointmentService::changeStatus($c, 'Waiting', (int) $u['id']);
        $t->same(1, (int) Appointment::find($b)['queue_number']);
        $t->same(2, (int) Appointment::find($a)['queue_number']);
        $t->same(1, (int) Appointment::find($c)['queue_number']);
        $queue = Appointment::queue($today, (int) $d1['id']);
        $t->same([$b, $a], array_map('intval', array_column($queue, 'id')));
    });
    $t->test('walk-in joins the queue immediately; duplicates and closed hours are refused', function () use ($t) {
        $u = Fixtures::user('receptionist');
        $d = Fixtures::doctor(15, '1000.00', false);
        for ($w = 1; $w <= 7; $w++) { Doctor::addSchedule((int) $d['id'], $w, '00:00', '23:59'); }
        Setting::set('opening_time', '00:00');
        Setting::set('closing_time', '23:59');
        $p = Fixtures::patient();
        $id = AppointmentService::registerWalkIn((int) $p['id'], (int) $d['id'], 'Fever', (int) $u['id']);
        $row = Appointment::find($id);
        $t->same('Waiting', $row['status']);
        $t->same('Walk-in', $row['booking_type']);
        $t->same(1, (int) $row['queue_number']);
        $t->throws(BusinessRuleException::class, fn () => AppointmentService::registerWalkIn((int) $p['id'], (int) $d['id'], null, (int) $u['id']), 'already has an appointment');
        $second = AppointmentService::registerWalkIn((int) Fixtures::patient()['id'], (int) $d['id'], null, (int) $u['id']);
        $t->same(2, (int) Appointment::find($second)['queue_number']);
        // clinic closed right now
        Setting::set('opening_time', date('H') < 23 ? '23:30' : '00:00');
        Setting::set('closing_time', date('H') < 23 ? '23:59' : '00:01');
        $t->throws(BusinessRuleException::class, fn () => AppointmentService::registerWalkIn((int) Fixtures::patient()['id'], (int) $d['id'], null, (int) $u['id']), 'closed');
    });
    $t->test('walk-in is refused when the doctor is not on duty', function () use ($t) {
        $u = Fixtures::user('receptionist');
        $d = Fixtures::doctor(15, '1000.00', false);   // no schedule at all
        Setting::set('opening_time', '00:00');
        Setting::set('closing_time', '23:59');
        $t->throws(BusinessRuleException::class, fn () => AppointmentService::registerWalkIn((int) Fixtures::patient()['id'], (int) $d['id'], null, (int) $u['id']), 'not on duty');
    });
    $t->test('doctor schedule rules: inside clinic hours, no overlaps', function () use ($t) {
        $d = Fixtures::doctor(15, '1000.00', false);
        \App\Services\DoctorService::addSchedule((int) $d['id'], 1, '09:00', '12:00');
        $t->throws(BusinessRuleException::class, fn () => \App\Services\DoctorService::addSchedule((int) $d['id'], 1, '11:00', '13:00'), 'overlaps');
        $t->throws(BusinessRuleException::class, fn () => \App\Services\DoctorService::addSchedule((int) $d['id'], 1, '07:00', '08:00'), 'clinic hours');
        $t->throws(BusinessRuleException::class, fn () => \App\Services\DoctorService::addSchedule((int) $d['id'], 1, '14:00', '13:00'), 'after the start');
        \App\Services\DoctorService::addSchedule((int) $d['id'], 1, '12:00', '14:00');   // touching windows are fine
        $t->true(true);
    });
};
