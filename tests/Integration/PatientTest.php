<?php
declare(strict_types=1);

use App\Core\BusinessRuleException;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\PatientService;
use Tests\Support\Fixtures;
use Tests\Support\Tester;

return static function (Tester $t): void {
    [$before, $after] = $GLOBALS['__test_hooks'];
    $t->group('Integration: patients', $before, $after);

    $valid = fn (array $o = []) => $o + ['first_name' => 'Sara', 'last_name' => 'Ahmed', 'gender' => 'Female', 'date_of_birth' => '1995-03-04', 'phone' => '0300 111-2233', 'email' => 'SARA@Mail.test', 'address' => null, 'city' => null, 'blood_group' => null, 'emergency_contact_name' => null, 'emergency_contact_phone' => null];

    $t->test('registration assigns sequential codes and normalises phone/email', function () use ($t, $valid) {
        $admin = Fixtures::user('admin');
        $a = PatientService::register($valid(), (int) $admin['id']);
        $b = PatientService::register($valid(['first_name' => 'Other', 'phone' => '03004445566']), (int) $admin['id']);
        $t->same('PT-000001', $a['code']);
        $t->same('PT-000002', $b['code']);
        $row = Patient::find($a['id']);
        $t->same('03001112233', $row['phone']);
        $t->same('sara@mail.test', $row['email']);
    });
    $t->test('duplicate patients are rejected and point to the existing record', function () use ($t, $valid) {
        $admin = Fixtures::user('admin');
        $first = PatientService::register($valid(), (int) $admin['id']);
        try {
            PatientService::register($valid(['phone' => '0300-111 2233']), (int) $admin['id']);
            $t->true(false, 'expected a duplicate error');
        } catch (BusinessRuleException $e) {
            $t->same($first['id'], $e->context['duplicate_id']);
        }
        // a family member with the same phone but another name/dob is a different person
        $other = PatientService::register($valid(['first_name' => 'Hira', 'date_of_birth' => '2015-01-01']), (int) $admin['id']);
        $t->true($other['id'] !== $first['id']);
    });
    $t->test('a failed registration does not burn a patient code', function () use ($t, $valid) {
        $admin = Fixtures::user('admin');
        PatientService::register($valid(), (int) $admin['id']);
        try { PatientService::register($valid(), (int) $admin['id']); } catch (BusinessRuleException) {}
        $next = PatientService::register($valid(['first_name' => 'Next', 'phone' => '03009998877']), (int) $admin['id']);
        $t->same('PT-000002', $next['code']);
    });
    $t->test('validation rejects future birth dates, bad phones and implausible ages', function () use ($t, $valid) {
        $t->true(PatientService::validate($valid(['date_of_birth' => date('Y-m-d', strtotime('+1 day'))]))->fails());
        $t->true(PatientService::validate($valid(['phone' => '12']))->fails());
        $t->true(PatientService::validate($valid(['date_of_birth' => '1800-01-01']))->fails());
        $t->true(PatientService::validate($valid(['gender' => 'Robot']))->fails());
        $t->false(PatientService::validate($valid())->fails());
    });
    $t->test('search matches name, code and phone and treats % and _ literally', function () use ($t) {
        $p = Fixtures::patient(['first_name' => 'Zubair', 'last_name' => 'Searchable', 'phone' => '03005550101']);
        Fixtures::patient(['first_name' => 'Other', 'last_name' => 'Person']);
        $names = fn (string $q) => array_column(Patient::paginate(['q' => $q, 'status' => 'active'], 1, 20)->items, 'id');
        $t->same([$p['id']], $names('Zubair Search'));
        $t->same([$p['id']], $names('03005550101'));
        $t->same([$p['id']], $names($p['patient_code']));
        $t->same([], $names('%'), 'a lone % must not match everything');
        $t->same([], $names('_ubair'), 'underscore is not a wildcard');
        $t->same([], $names("' OR 1=1 -- "), 'SQL metacharacters are inert');
    });
    $t->test('archiving hides the patient but keeps history; it is blocked while future appointments exist', function () use ($t) {
        $admin = Fixtures::user('admin');
        $d = Fixtures::doctor();
        $p = Fixtures::patient();
        $apptId = Appointment::create(['patient_id' => $p['id'], 'doctor_id' => $d['id'], 'appointment_date' => Fixtures::futureDate(), 'start_time' => '10:00', 'end_time' => '10:15', 'booking_type' => 'Scheduled', 'status' => 'Scheduled'], (int) $admin['id']);
        $t->throws(BusinessRuleException::class, fn () => PatientService::archive((int) $p['id'], (int) $admin['id']), 'upcoming');
        Appointment::setStatus($apptId, 'Cancelled', 'x');
        PatientService::archive((int) $p['id'], (int) $admin['id']);
        $t->same([], array_column(Patient::paginate(['q' => $p['patient_code'], 'status' => 'active'], 1, 20)->items, 'id'));
        $t->same(1, count(Patient::paginate(['q' => $p['patient_code'], 'status' => 'archived'], 1, 20)->items));
        PatientService::restore((int) $p['id'], (int) $admin['id']);
        $t->same(1, count(Patient::paginate(['q' => $p['patient_code'], 'status' => 'active'], 1, 20)->items));
    });
    $t->test('a doctor only sees patients they have a care relationship with', function () use ($t) {
        $admin = Fixtures::user('admin');
        $d1 = Fixtures::doctor();
        $d2 = Fixtures::doctor();
        $mine = Fixtures::patient();
        $theirs = Fixtures::patient();
        Fixtures::patient(); // unrelated
        Appointment::create(['patient_id' => $mine['id'], 'doctor_id' => $d1['id'], 'appointment_date' => Fixtures::futureDate(), 'start_time' => '10:00', 'end_time' => '10:15', 'booking_type' => 'Scheduled', 'status' => 'Scheduled'], (int) $admin['id']);
        Appointment::create(['patient_id' => $theirs['id'], 'doctor_id' => $d2['id'], 'appointment_date' => Fixtures::futureDate(), 'start_time' => '10:00', 'end_time' => '10:15', 'booking_type' => 'Scheduled', 'status' => 'Scheduled'], (int) $admin['id']);
        $seen = array_column(Patient::paginate(['status' => 'all'], 1, 50, (int) $d1['id'])->items, 'id');
        $t->same([$mine['id']], $seen);
        $t->true(Patient::hasCareRelationship((int) $d1['id'], (int) $mine['id']));
        $t->false(Patient::hasCareRelationship((int) $d1['id'], (int) $theirs['id']));
    });
    $t->test('cancelled-only relationships do not grant access', function () use ($t) {
        $admin = Fixtures::user('admin');
        $d = Fixtures::doctor();
        $p = Fixtures::patient();
        $id = Appointment::create(['patient_id' => $p['id'], 'doctor_id' => $d['id'], 'appointment_date' => Fixtures::futureDate(), 'start_time' => '10:00', 'end_time' => '10:15', 'booking_type' => 'Scheduled', 'status' => 'Scheduled'], (int) $admin['id']);
        Appointment::setStatus($id, 'Cancelled', 'x');
        $t->false(Patient::hasCareRelationship((int) $d['id'], (int) $p['id']));
    });
};
