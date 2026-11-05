<?php
declare(strict_types=1);

use App\Core\BusinessRuleException;
use App\Core\Database;
use App\Core\HttpException;
use App\Models\Appointment;
use App\Models\FollowUp;
use App\Models\Prescription;
use App\Models\Visit;
use App\Services\VisitService;
use Tests\Support\Fixtures;
use Tests\Support\Tester;

return static function (Tester $t): void {
    [$before, $after] = $GLOBALS['__test_hooks'];
    $t->group('Integration: consultations, prescriptions, amendments', $before, $after);

    $waiting = function (?array $doctor = null) {
        $u = Fixtures::user('receptionist');
        $d = $doctor ?? Fixtures::doctor();
        $p = Fixtures::patient();
        $apt = Appointment::create(['patient_id' => $p['id'], 'doctor_id' => $d['id'], 'appointment_date' => date('Y-m-d'), 'start_time' => '10:00', 'end_time' => '10:15', 'booking_type' => 'Scheduled', 'status' => 'Waiting', 'queue_number' => 1], (int) $u['id']);

        return [$d, $p, $apt];
    };
    $clinical = fn (array $o = []) => VisitService::validateClinical($o + ['chief_complaint' => 'Fever', 'diagnosis' => 'Viral fever', 'bp_systolic' => '120', 'bp_diastolic' => '80', 'temperature_c' => '38.4', 'advice' => 'Rest'], true)->validated();
    $meds = [['medicine_name' => 'Paracetamol 500 mg', 'dosage' => '1 tablet', 'frequency' => 'Three times a day', 'duration' => '3 days', 'instructions' => null]];

    $t->test('starting a consultation creates a draft and marks the appointment In Progress (idempotent)', function () use ($t, $waiting) {
        [$d, $p, $apt] = $waiting();
        $vid = VisitService::start($apt, (int) $d['id'], (int) $d['user']['id']);
        $t->same('Draft', Visit::find($vid)['status']);
        $t->same('In Progress', Appointment::find($apt)['status']);
        $t->same($vid, VisitService::start($apt, (int) $d['id'], (int) $d['user']['id']), 'resuming returns the same visit');
        $t->same(1, (int) Database::value('SELECT COUNT(*) FROM visits WHERE appointment_id = :a', ['a' => $apt]));
    });
    $t->test('only the booked doctor can start it, and only when the patient is waiting', function () use ($t, $waiting) {
        [$d, $p, $apt] = $waiting();
        $other = Fixtures::doctor();
        $t->throws(HttpException::class, fn () => VisitService::start($apt, (int) $other['id'], (int) $other['user']['id']));
        Appointment::setStatus($apt, 'Scheduled');
        $t->throws(BusinessRuleException::class, fn () => VisitService::start($apt, (int) $d['id'], (int) $d['user']['id']), 'waiting');
    });
    $t->test('finalizing locks the visit, completes the appointment, stores the prescription and creates the follow-up', function () use ($t, $waiting, $clinical, $meds) {
        [$d, $p, $apt] = $waiting();
        $uid = (int) $d['user']['id'];
        $vid = VisitService::start($apt, (int) $d['id'], $uid);
        $due = date('Y-m-d', strtotime('+7 days'));
        VisitService::saveDraft($vid, $clinical(), $meds, ['date' => $due, 'notes' => 'Review'], true, $uid);
        $v = Visit::find($vid);
        $t->same('Finalized', $v['status']);
        $t->true($v['finalized_at'] !== null);
        $t->same('Completed', Appointment::find($apt)['status']);
        $rx = Prescription::findByVisit($vid);
        $t->true(str_starts_with($rx['prescription_no'], 'RX-'));
        $t->same(1, count(Prescription::items((int) $rx['id'])));
        $fu = FollowUp::forPatient((int) $p['id']);
        $t->same(1, count($fu));
        $t->same($due, $fu[0]['follow_up_date']);
        $t->same('Pending', $fu[0]['status']);
        $t->throws(BusinessRuleException::class, fn () => VisitService::saveDraft($vid, $clinical(), $meds, ['date' => null, 'notes' => null], false, $uid), 'finalized');
    });
    $t->test('drafts can be saved repeatedly; prescription lines are replaced, not duplicated', function () use ($t, $waiting, $clinical, $meds) {
        [$d, $p, $apt] = $waiting();
        $uid = (int) $d['user']['id'];
        $vid = VisitService::start($apt, (int) $d['id'], $uid);
        VisitService::saveDraft($vid, $clinical(), $meds, ['date' => null, 'notes' => null], false, $uid);
        $two = array_merge($meds, [['medicine_name' => 'ORS', 'dosage' => '1 sachet', 'frequency' => 'Daily', 'duration' => '3 days', 'instructions' => 'In water']]);
        VisitService::saveDraft($vid, $clinical(), $two, ['date' => null, 'notes' => null], false, $uid);
        $rx = Prescription::findByVisit($vid);
        $t->same(2, count(Prescription::items((int) $rx['id'])));
        $t->same('Draft', Visit::find($vid)['status']);
        $t->same('In Progress', Appointment::find($apt)['status']);
        $t->same(1, (int) Database::value('SELECT COUNT(*) FROM prescriptions WHERE visit_id = :v', ['v' => $vid]));
    });
    $t->test('amending keeps the previous version, who, when and why', function () use ($t, $waiting, $clinical, $meds) {
        [$d, $p, $apt] = $waiting();
        $uid = (int) $d['user']['id'];
        $vid = VisitService::start($apt, (int) $d['id'], $uid);
        VisitService::saveDraft($vid, $clinical(), $meds, ['date' => null, 'notes' => null], true, $uid);

        $changed = $meds;
        $changed[0]['dosage'] = '2 tablets';
        VisitService::amend($vid, $clinical(['diagnosis' => 'Dengue fever (confirmed)']), $changed, 'Lab result arrived', $uid);

        $now = Visit::find($vid);
        $t->same('Dengue fever (confirmed)', $now['diagnosis']);
        $log = Visit::amendments($vid);
        $t->same(1, count($log));
        $t->same('Lab result arrived', $log[0]['reason']);
        $t->same($uid, (int) $log[0]['amended_by']);
        $before = json_decode($log[0]['previous_data'], true);
        $after = json_decode($log[0]['new_data'], true);
        $t->same('Viral fever', $before['clinical']['diagnosis']);
        $t->same('Dengue fever (confirmed)', $after['clinical']['diagnosis']);
        $t->same('1 tablet', $before['prescription']['items'][0]['dosage']);
        $t->same('2 tablets', $after['prescription']['items'][0]['dosage']);
        $t->same('Finalized', $now['status'], 'still finalized');
        $t->throws(PDOException::class, fn () => Database::run("UPDATE visit_amendments SET reason = 'x'"), 'cannot be changed');
    });
    $t->test('an amendment that changes nothing is refused; drafts cannot be amended', function () use ($t, $waiting, $clinical, $meds) {
        [$d, $p, $apt] = $waiting();
        $uid = (int) $d['user']['id'];
        $vid = VisitService::start($apt, (int) $d['id'], $uid);
        $t->throws(BusinessRuleException::class, fn () => VisitService::amend($vid, $clinical(), $meds, 'try', $uid), 'Only finalized');
        VisitService::saveDraft($vid, $clinical(), $meds, ['date' => null, 'notes' => null], true, $uid);
        $t->throws(BusinessRuleException::class, fn () => VisitService::amend($vid, $clinical(), $meds, 'no real change', $uid), 'No changes');
        $t->same(0, count(Visit::amendments($vid)));
    });
    $t->test('clinical validation: finalize needs complaint and diagnosis; vitals must be plausible and consistent', function () use ($t) {
        $v = VisitService::validateClinical(['chief_complaint' => '', 'diagnosis' => ''], true);
        $t->true(isset($v->errors()['chief_complaint']) && isset($v->errors()['diagnosis']));
        $t->false(VisitService::validateClinical(['chief_complaint' => '', 'diagnosis' => ''], false)->fails(), 'drafts may be incomplete');
        $t->true(isset(VisitService::validateClinical(['bp_systolic' => '80', 'bp_diastolic' => '90'], false)->errors()['bp_systolic']));
        $t->true(isset(VisitService::validateClinical(['bp_systolic' => '120'], false)->errors()['bp_diastolic']));
        $t->true(isset(VisitService::validateClinical(['temperature_c' => '98.6'], false)->errors()['temperature_c']));
        $t->true(isset(VisitService::validateClinical(['pulse_bpm' => '70.5'], false)->errors()['pulse_bpm']));
        $t->true(isset(VisitService::validateClinical(['spo2_percent' => '101'], false)->errors()['spo2_percent']));
        $t->true(isset(VisitService::validateClinical(['weight_kg' => '-2'], false)->errors()['weight_kg']));
    });
    $t->test('medicine rows: empty rows ignored, incomplete rows rejected, limit enforced', function () use ($t) {
        $r = VisitService::parseMedicines(['rx_medicine' => ['Paracetamol', '', ''], 'rx_dosage' => ['1', '', ''], 'rx_frequency' => ['Daily', '', ''], 'rx_duration' => ['3d', '', ''], 'rx_instructions' => ['', '', '']]);
        $t->same(1, count($r['items']));
        $t->same([], $r['errors']);
        $bad = VisitService::parseMedicines(['rx_medicine' => ['Paracetamol'], 'rx_dosage' => [''], 'rx_frequency' => ['Daily'], 'rx_duration' => ['3d']]);
        $t->true(isset($bad['errors']['rx']));
        $many = array_fill(0, 21, 'x');
        $over = VisitService::parseMedicines(['rx_medicine' => $many, 'rx_dosage' => $many, 'rx_frequency' => $many, 'rx_duration' => $many]);
        $t->true(isset($over['errors']['rx']));
        $t->same(['items' => [], 'errors' => []], VisitService::parseMedicines(['rx_medicine' => 'not-an-array']));
    });
};
