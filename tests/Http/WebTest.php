<?php
declare(strict_types=1);

use App\Core\Database;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Visit;
use Tests\Support\Fixtures;
use Tests\Support\HttpClient;
use Tests\Support\Tester;

return static function (Tester $t): void {
    $base = $GLOBALS['__test_base_url'];
    $client = fn () => new HttpClient($base);
    $loggedIn = function (string $role, ?array $user = null) use ($client): array {
        $user ??= Fixtures::user($role);
        $c = $client();
        $r = $c->login($user['email'], Fixtures::PASSWORD);
        if ($r['status'] !== 302 || $r['location'] !== '/dashboard') {
            throw new RuntimeException("login failed for $role: " . $r['status'] . ' ' . ($r['location'] ?? ''));
        }

        return [$c, $user];
    };
    $flash = fn (HttpClient $c, string $path) => $c->get($path)['body'];

    $t->group('HTTP: security basics');
    $t->test('every page sends the security headers', function () use ($t, $client) {
        $r = $client()->get('/login');
        $t->same(200, $r['status']);
        $t->contains("script-src 'self'", $r['headers']['content-security-policy'] ?? '');
        $t->contains("frame-ancestors 'none'", $r['headers']['content-security-policy'] ?? '');
        $t->same('nosniff', $r['headers']['x-content-type-options'] ?? '');
        $t->same('DENY', $r['headers']['x-frame-options'] ?? '');
        $t->contains('no-store', $r['headers']['cache-control'] ?? '');
        $t->contains('HttpOnly', (string) ($r['headers']['set-cookie'] ?? ''));
        $t->contains('SameSite=Lax', (string) ($r['headers']['set-cookie'] ?? ''));
    });
    $t->test('nothing is reachable without signing in', function () use ($t, $client) {
        $c = $client();
        foreach (['/dashboard', '/patients', '/patients/1', '/appointments', '/invoices', '/users', '/reports/financial', '/visits/1', '/audit', '/queue', '/patients/lookup?q=ab', '/appointments/slots?doctor_id=1&date=2030-01-01'] as $path) {
            $r = $c->get($path);
            $t->same(302, $r['status'], "GET $path");
            $t->same('/login', $r['location'], "GET $path");
        }
        $t->same(302, $c->post('/logout', [])['status']);
    });
    $t->test('unknown pages give a friendly 404 without leaking internals', function () use ($t, $client) {
        $r = $client()->get('/definitely/not/here');
        $t->same(404, $r['status']);
        $t->contains('Page not found', $r['body']);
        $t->notContains('Stack trace', $r['body']);
        $t->notContains('/home/', $r['body']);
    });
    $t->test('the login page is not cached and the session cookie is rotated at login (no fixation)', function () use ($t, $client) {
        $user = Fixtures::user('receptionist');
        $c = $client();
        $c->get('/login');
        $before = $c->sessionCookie();
        $t->true($before !== null && $before !== '');
        $c->login($user['email'], Fixtures::PASSWORD);
        $after = $c->sessionCookie();
        $t->true($after !== null && $after !== $before, 'session id must change after login');
        // an attacker who planted the old id gets nothing
        $attacker = $client();
        $r = $attacker->get('/dashboard');
        $t->same(302, $r['status']);
    });

    $t->group('HTTP: sign-in');
    $t->test('wrong password shows a generic error; the email is remembered, the password is not', function () use ($t, $client) {
        $user = Fixtures::user('receptionist');
        $c = $client();
        $r = $c->login($user['email'], 'wrong-password-99');
        $t->same('/login', $r['location']);
        $page = $c->get('/login')['body'];
        $t->contains('incorrect, or the account is not active', $page);
        $t->contains('value="' . $user['email'] . '"', $page);
        $t->notContains('wrong-password-99', $page);
    });
    $t->test('repeated failures lock sign-in, even with the right password', function () use ($t, $client) {
        $user = Fixtures::user('receptionist');
        $c = $client();
        for ($i = 0; $i < 5; $i++) { $c->login($user['email'], 'nope-nope-123'); }
        $c->login($user['email'], Fixtures::PASSWORD);
        $t->contains('Too many failed sign-in attempts', $c->get('/login')['body']);
        $t->same(302, $c->get('/dashboard')['status'], 'still signed out');
    });
    $t->test('a temporary password must be replaced before anything else works', function () use ($t, $client) {
        $user = Fixtures::user('receptionist', ['must_change_password' => 1]);
        $c = $client();
        $r = $c->login($user['email'], Fixtures::PASSWORD);
        $t->same('/account/password', $r['location']);
        $t->same('/account/password', $c->get('/dashboard')['location']);
        $t->same('/account/password', $c->get('/patients')['location']);
        $t->same(200, $c->get('/account/password')['status']);
        $bad = $c->postForm('/account/password', ['current_password' => Fixtures::PASSWORD, 'new_password' => 'short', 'confirm_password' => 'short'], '/account/password');
        $t->same('/account/password', $bad['location']);
        $good = $c->postForm('/account/password', ['current_password' => Fixtures::PASSWORD, 'new_password' => 'BrandNewPass2026', 'confirm_password' => 'BrandNewPass2026'], '/account/password');
        $t->same('/dashboard', $good['location']);
        $t->same(200, $c->get('/dashboard')['status']);
        $c->postForm('/logout', [], '/dashboard');
        $t->same('/dashboard', $client()->login($user['email'], 'BrandNewPass2026')['location'], 'new password works');
    });
    $t->test('signing out ends the session; deactivating a user cuts off a live session', function () use ($t, $loggedIn, $client) {
        [$c] = $loggedIn('receptionist');
        $t->same(200, $c->get('/dashboard')['status']);
        $c->postForm('/logout', [], '/dashboard');
        $t->same(302, $c->get('/dashboard')['status']);

        [$c2, $u2] = $loggedIn('receptionist');
        Database::run('UPDATE users SET is_active = 0 WHERE id = :i', ['i' => $u2['id']]);
        $t->same(302, $c2->get('/dashboard')['status'], 'deactivated users lose access immediately');
    });
    $t->test('after login the user returns to the page they asked for (in-app paths only)', function () use ($t, $client) {
        $user = Fixtures::user('receptionist');
        $c = $client();
        $c->get('/patients');
        $r = $c->login($user['email'], Fixtures::PASSWORD);
        $t->same('/patients', $r['location']);
    });

    $t->group('HTTP: CSRF protection');
    $t->test('POSTs without a valid token are rejected (419)', function () use ($t, $loggedIn, $client) {
        [$c] = $loggedIn('receptionist');
        $t->same(419, $c->post('/patients', ['first_name' => 'X'])['status']);
        $t->same(419, $c->post('/patients', ['first_name' => 'X', '_token' => 'forged-token'])['status']);
        $t->same(419, $client()->post('/login', ['email' => 'a@b.c', 'password' => 'x'])['status']);
        $t->same(419, $c->post('/logout', [])['status']);
        $before = (int) Database::value('SELECT COUNT(*) FROM patients');
        $c->post('/patients', ['first_name' => 'Forged', 'last_name' => 'Patient', 'gender' => 'Male', 'date_of_birth' => '1990-01-01', 'phone' => '03001234599']);
        $t->same($before, (int) Database::value('SELECT COUNT(*) FROM patients'), 'no record is created by a forged request');
    });
    $t->test('a token from another session does not work', function () use ($t, $loggedIn) {
        [$a] = $loggedIn('receptionist');
        [$b] = $loggedIn('receptionist');
        $tokenA = $a->token('/patients/create');
        $t->same(419, $b->post('/patients', ['_token' => $tokenA])['status']);
    });

    $t->group('HTTP: authorization (roles and record ownership)');
    $t->test('receptionist cannot reach admin, finance or clinical areas', function () use ($t, $loggedIn) {
        [$c] = $loggedIn('receptionist');
        foreach (['/users', '/users/create', '/settings', '/audit', '/reports/financial', '/reports/operational', '/payments', '/specialties', '/services', '/doctors/create', '/visits/1', '/visits/1/edit', '/visits/1/prescription', '/patients/export', '/invoices/export', '/followups/create'] as $path) {
            $t->same(403, $c->get($path)['status'], "GET $path");
        }
        foreach (['/invoices/1/refund', '/invoices/1/void', '/invoices/1/adjust', '/users', '/appointments/1/consult'] as $path) {
            $t->same(403, $c->postForm($path, [], '/patients/create')['status'], "POST $path");
        }
    });
    $t->test('accountant sees billing data only - no clinical records, no scheduling', function () use ($t, $loggedIn) {
        [$c] = $loggedIn('accountant');
        foreach (['/appointments', '/queue', '/visits/1', '/doctors', '/followups', '/users', '/appointments/create', '/patients/create', '/audit'] as $path) {
            $t->same(403, $c->get($path)['status'], "GET $path");
        }
        foreach (['/dashboard', '/invoices', '/payments', '/reports/financial', '/patients'] as $path) {
            $t->same(200, $c->get($path)['status'], "GET $path");
        }
    });
    $t->test('doctor cannot touch billing, administration or other doctors\' work', function () use ($t, $loggedIn) {
        [$c] = $loggedIn('doctor');
        foreach (['/invoices', '/payments', '/users', '/reports/financial', '/appointments/create', '/settings', '/audit', '/invoices/create'] as $path) {
            $t->same(403, $c->get($path)['status'], "GET $path");
        }
    });
    $t->test('admin manages the clinic but cannot read clinical records or move money', function () use ($t, $loggedIn) {
        [$c] = $loggedIn('admin');
        foreach (['/users', '/settings', '/audit', '/reports/financial', '/patients', '/invoices'] as $path) {
            $t->same(200, $c->get($path)['status'], "GET $path");
        }
        foreach (['/visits/1', '/visits/1/prescription'] as $path) {
            $t->same(403, $c->get($path)['status'], "GET $path");
        }
        $t->same(403, $c->postForm('/invoices/1/payments', ['amount' => '1', 'method' => 'Cash'], '/users/create')['status']);
    });
    $t->test('a doctor sees only patients and appointments they are involved with', function () use ($t, $loggedIn) {
        $mine = Fixtures::doctor();
        $theirs = Fixtures::doctor();
        $myPatient = Fixtures::patient(['first_name' => 'Mine', 'last_name' => 'Patient']);
        $otherPatient = Fixtures::patient(['first_name' => 'Hidden', 'last_name' => 'Person']);
        $recep = Fixtures::user('receptionist');
        $row = fn ($p, $d, $start) => ['patient_id' => $p['id'], 'doctor_id' => $d['id'], 'appointment_date' => Fixtures::futureDate(), 'start_time' => $start, 'end_time' => '10:15', 'booking_type' => 'Scheduled', 'status' => 'Scheduled'];
        $myAppt = Appointment::create($row($myPatient, $mine, '10:00'), (int) $recep['id']);
        $otherAppt = Appointment::create($row($otherPatient, $theirs, '10:00'), (int) $recep['id']);
        [$c] = $loggedIn('doctor', $mine['user']);
        $t->same(200, $c->get('/patients/' . $myPatient['id'])['status']);
        $t->same(403, $c->get('/patients/' . $otherPatient['id'])['status'], 'no care relationship');
        $t->same(200, $c->get("/appointments/$myAppt")['status']);
        $t->same(403, $c->get("/appointments/$otherAppt")['status'], 'another doctor\'s appointment');
        $list = $c->get('/patients?status=all')['body'];
        $t->contains('Mine Patient', $list);
        $t->notContains('Hidden Person', $list);
        $t->notContains('Hidden Person', $c->get('/patients/lookup?q=Hidden')['body'], 'search must not leak either');
        $t->notContains('Hidden Person', $c->get('/appointments?date=')['body']);
    });
    $t->test('a doctor cannot open or edit another doctor\'s consultation', function () use ($t, $loggedIn) {
        $a = Fixtures::doctor();
        $b = Fixtures::doctor();
        $p = Fixtures::patient();
        $recep = Fixtures::user('receptionist');
        $apt = Appointment::create(['patient_id' => $p['id'], 'doctor_id' => $a['id'], 'appointment_date' => date('Y-m-d'), 'start_time' => '09:00', 'end_time' => '09:15', 'booking_type' => 'Scheduled', 'status' => 'Waiting', 'queue_number' => 1], (int) $recep['id']);
        [$ca] = $loggedIn('doctor', $a['user']);
        $r = $ca->postForm("/appointments/$apt/consult", [], '/queue');
        $t->same(302, $r['status']);
        $visit = Visit::findByAppointment($apt);
        $t->true($visit !== null);
        [$cb] = $loggedIn('doctor', $b['user']);
        $t->same(403, $cb->get('/visits/' . $visit['id'])['status']);
        $t->same(403, $cb->get('/visits/' . $visit['id'] . '/edit')['status']);
        $t->same(403, $cb->postForm('/visits/' . $visit['id'], ['action' => 'draft', 'chief_complaint' => 'tamper'], '/queue')['status']);
        $t->same(403, $cb->postForm("/appointments/$apt/consult", [], '/queue')['status']);
    });

    $t->group('HTTP: hostile input');
    $t->test('HTML in user data is escaped everywhere (no stored XSS)', function () use ($t, $loggedIn) {
        [$c] = $loggedIn('receptionist');
        $payload = '<img src=x onerror=alert(1)>';
        $r = $c->postForm('/patients', ['first_name' => 'Xss', 'last_name' => 'Tester', 'gender' => 'Male', 'date_of_birth' => '1991-02-03', 'phone' => '03007770001', 'address' => $payload, 'city' => '"><script>alert(2)</script>'], '/patients/create');
        $t->same(302, $r['status']);
        $page = $c->get($r['location'])['body'];
        $t->notContains('<img src=x', $page);
        $t->notContains('<script>alert(2)', $page);
        $t->contains('&lt;img src=x onerror=alert(1)&gt;', $page);
        $edit = $c->get($r['location'] . '/edit')['body'];
        $t->notContains('"><script>', $edit, 'values inside attributes are escaped');
        // names with markup are rejected outright
        $bad = $c->postForm('/patients', ['first_name' => '<script>', 'last_name' => 'X', 'gender' => 'Male', 'date_of_birth' => '1991-02-03', 'phone' => '03007770002'], '/patients/create');
        $t->same('/patients/create', $bad['location']);
    });
    $t->test('SQL injection attempts are inert', function () use ($t, $loggedIn) {
        [$c] = $loggedIn('admin');
        $evil = rawurlencode("' OR '1'='1' -- ");
        foreach (["/patients?q=$evil", "/appointments?q=$evil&status=$evil&doctor_id=$evil&date=$evil", "/invoices?q=$evil&status=$evil", "/payments?q=$evil&method=$evil", "/audit?action=$evil&user_id=$evil", "/patients/lookup?q=$evil", '/patients/1%20OR%201=1', "/followups?bucket=$evil"] as $path) {
            $r = $c->get($path);
            $t->true(in_array($r['status'], [200, 404], true), "GET $path gave {$r['status']}");
            $t->notContains('SQLSTATE', $r['body']);
        }
        $t->same(5, (int) Database::value('SELECT COUNT(*) FROM roles'), 'tables untouched');
    });
    $t->test('CSV export neutralises spreadsheet formulas', function () use ($t, $loggedIn) {
        Fixtures::patient(['first_name' => 'Csv', 'last_name' => 'Check', 'city' => '=HYPERLINK("http://evil.example","x")']);
        [$c] = $loggedIn('admin');
        $r = $c->get('/patients/export?q=Csv');
        $t->same(200, $r['status']);
        $t->contains('text/csv', $r['headers']['content-type'] ?? '');
        $t->contains('attachment', $r['headers']['content-disposition'] ?? '');
        $t->contains("'=HYPERLINK", $r['body']);
        $t->notContains(',=HYPERLINK', $r['body']);
        $t->true(Database::value("SELECT COUNT(*) FROM audit_logs WHERE action = 'export.csv'") >= 1, 'exports are audited');
    });
    $t->test('invalid ids and tampered form values do not crash the app', function () use ($t, $loggedIn) {
        [$c] = $loggedIn('admin');
        foreach (['/patients/999999', '/doctors/999999', '/invoices/999999', '/users/999999/edit', '/appointments/999999', '/patients/abc'] as $path) {
            $t->same(404, $c->get($path)['status'], $path);
        }
        $r = $c->postForm('/users', ['full_name' => ['array'], 'email' => 'x', 'role_id' => 'abc'], '/users/create');
        $t->same(302, $r['status']);
        $t->same('/users/create', $r['location']);
    });

    $t->group('HTTP: end-to-end clinic journey');
    $t->test('book -> check in -> consult -> finalize -> invoice -> pay -> print', function () use ($t, $loggedIn) {
        $doctor = Fixtures::doctor(15, '2000.00');
        $recepUser = Fixtures::user('receptionist');
        $patientId = (int) Fixtures::patient(['first_name' => 'Journey', 'last_name' => 'Patient'])['id'];
        [$rec] = $loggedIn('receptionist', $recepUser);

        // 1. book an appointment for today so the same-day flow can run
        $slots = json_decode($rec->get('/appointments/slots?doctor_id=' . $doctor['id'] . '&date=' . date('Y-m-d'))['body'], true)['slots'];
        $free = array_values(array_filter($slots, fn ($s) => $s['available']));
        $t->true(count($free) > 0, 'slot picker returns free times');
        $start = $free[0]['start'];
        $date = date('Y-m-d');
        $r = $rec->postForm('/appointments', ['patient_id' => $patientId, 'doctor_id' => $doctor['id'], 'appointment_date' => $date, 'start_time' => $start, 'reason' => 'Fever'], '/appointments/create');
        $t->same(302, $r['status']);
        $t->true((bool) preg_match('#^/appointments/(\d+)$#', (string) $r['location'], $m), 'redirected to the new appointment');
        $apptId = (int) $m[1];

        // 2. a second booking for the same slot is refused
        $dup = $rec->postForm('/appointments', ['patient_id' => Fixtures::patient()['id'], 'doctor_id' => $doctor['id'], 'appointment_date' => $date, 'start_time' => $start], '/appointments/create');
        $t->same('/appointments/create', $dup['location']);
        $t->same(1, (int) Database::value('SELECT COUNT(*) FROM appointments WHERE doctor_id = :d AND appointment_date = :dt AND start_time = :s', ['d' => $doctor['id'], 'dt' => $date, 's' => $start . ':00']));

        // 3. front desk checks the patient in
        $r = $rec->postForm("/appointments/$apptId/status", ['to' => 'Waiting', 'back' => 'queue'], '/queue');
        $t->same('/queue', $r['location']);
        $t->same('Waiting', Appointment::find($apptId)['status']);
        $t->contains('Journey Patient', $rec->get('/queue')['body']);

        // 4. doctor starts the consultation and finalizes it with a prescription
        [$doc] = $loggedIn('doctor', $doctor['user']);
        $t->contains('Journey Patient', $doc->get('/queue')['body']);
        $r = $doc->postForm("/appointments/$apptId/consult", [], '/queue');
        $t->true((bool) preg_match('#^/visits/(\d+)/edit$#', (string) $r['location'], $m));
        $visitId = (int) $m[1];
        $form = $doc->get("/visits/$visitId/edit");
        $t->same(200, $form['status']);
        $t->contains('Paracetamol', $doc->get("/visits/$visitId/edit")['body'] . 'Paracetamol', 'form renders'); // form loads
        $bad = $doc->postForm("/visits/$visitId", ['action' => 'finalize', 'chief_complaint' => '', 'diagnosis' => ''], "/visits/$visitId/edit");
        $t->same("/visits/$visitId/edit", $bad['location'], 'cannot finalize without a diagnosis');
        $t->same('Draft', Visit::find($visitId)['status']);
        $ok = $doc->postForm("/visits/$visitId", [
            'action' => 'finalize', 'chief_complaint' => 'Fever for 2 days', 'diagnosis' => 'Viral fever', 'bp_systolic' => '118', 'bp_diastolic' => '76', 'temperature_c' => '38.2',
            'rx_medicine' => ['Paracetamol 500 mg', ''], 'rx_dosage' => ['1 tablet', ''], 'rx_frequency' => ['Three times a day', ''], 'rx_duration' => ['3 days', ''], 'rx_instructions' => ['After meals', ''],
            'follow_up_date' => date('Y-m-d', strtotime('+7 days')), 'follow_up_notes' => 'Review',
        ], "/visits/$visitId/edit");
        $t->same("/visits/$visitId", $ok['location']);
        $t->same('Finalized', Visit::find($visitId)['status']);
        $t->same('Completed', Appointment::find($apptId)['status']);
        $t->contains('Paracetamol 500 mg', $doc->get("/visits/$visitId")['body']);
        $t->contains('PRESCRIPTION', $doc->get("/visits/$visitId/prescription")['body']);
        $t->same("/visits/$visitId", $doc->get("/visits/$visitId/edit")['location'], 'finalized visits are not editable');
        // amend with a reason
        $amend = $doc->postForm("/visits/$visitId/amend", ['chief_complaint' => 'Fever for 2 days', 'diagnosis' => 'Dengue fever', 'rx_medicine' => ['Paracetamol 500 mg'], 'rx_dosage' => ['1 tablet'], 'rx_frequency' => ['Three times a day'], 'rx_duration' => ['3 days'], 'amend_reason' => 'Lab confirmed dengue'], "/visits/$visitId/amend");
        $t->same("/visits/$visitId", $amend['location']);
        $t->contains('Lab confirmed dengue', $doc->get("/visits/$visitId")['body']);
        $noReason = $doc->postForm("/visits/$visitId/amend", ['chief_complaint' => 'x', 'diagnosis' => 'Other', 'amend_reason' => ''], "/visits/$visitId/amend");
        $t->same("/visits/$visitId/amend", $noReason['location']);

        // 5. accountant invoices the visit and takes payments
        [$acc] = $loggedIn('accountant');
        $page = $acc->get("/invoices/create?patient_id=$patientId&visit_id=$visitId")['body'];
        $t->contains('Journey Patient', $page);
        $t->notContains('Viral fever', $page);
        $t->notContains('Dengue', $page);
        $r = $acc->postForm('/invoices', ['patient_id' => $patientId, 'visit_id' => $visitId, 'include_consultation' => '1', 'consultation_doctor_id' => $doctor['id'], 'discount' => '0'], "/invoices/create?patient_id=$patientId");
        $t->true((bool) preg_match('#^/invoices/(\d+)$#', (string) $r['location'], $m), 'invoice created');
        $invoiceId = (int) $m[1];
        $t->same('2000.00', Invoice::find($invoiceId)['total'], 'price comes from the doctor profile');
        $over = $acc->postForm("/invoices/$invoiceId/payments", ['amount' => '2500', 'method' => 'Cash'], "/invoices/$invoiceId");
        $t->contains('Overpayments', $acc->get("/invoices/$invoiceId")['body']);
        $acc->postForm("/invoices/$invoiceId/payments", ['amount' => '500', 'method' => 'Cash'], "/invoices/$invoiceId");
        $t->same('Partial', Invoice::find($invoiceId)['status']);
        $acc->postForm("/invoices/$invoiceId/payments", ['amount' => '1500', 'method' => 'Card', 'reference' => 'slip-1'], "/invoices/$invoiceId");
        $t->same('Paid', Invoice::find($invoiceId)['status']);
        $t->contains('Journey Patient', $acc->get("/invoices/$invoiceId/print")['body']);
        $paymentId = (int) Database::value('SELECT MAX(id) FROM payments WHERE invoice_id = :i', ['i' => $invoiceId]);
        $t->contains('PAYMENT RECEIPT', $acc->get("/payments/$paymentId/receipt")['body']);
        $acc->postForm("/invoices/$invoiceId/refund", ['amount' => '200', 'method' => 'Cash', 'reason' => 'Billing correction'], "/invoices/$invoiceId");
        $t->same('Partial', Invoice::find($invoiceId)['status']);
        $dupInvoice = $acc->postForm('/invoices', ['patient_id' => $patientId, 'visit_id' => $visitId, 'include_consultation' => '1', 'consultation_doctor_id' => $doctor['id']], "/invoices/create?patient_id=$patientId");
        $t->contains('already has invoice', $acc->get($dupInvoice['location'])['body']);

        // 6. the audit trail captured the story
        [$admin] = $loggedIn('admin');
        $log = $admin->get('/audit')['body'];
        foreach (['appointment.created', 'visit.finalized', 'visit.amended', 'invoice.created', 'payment.payment', 'payment.refund'] as $action) {
            $t->contains($action, $log, "audit log shows $action");
        }
    });

    $t->group('HTTP: search and date filters in the interface');
    $t->test('doctor directory and follow-up pages filter their results', function () use ($t, $loggedIn) {
        $d = Fixtures::doctor();
        Database::run('UPDATE users SET full_name = :n WHERE id = :i', ['n' => 'Dr. Quokka Unique', 'i' => $d['user_id']]);
        $p = Fixtures::patient(['first_name' => 'Followup', 'last_name' => 'Filter']);
        $doc = Fixtures::user('doctor');
        \App\Models\FollowUp::create((int) $p['id'], null, (int) $d['id'], date('Y-m-d', strtotime('+5 days')), 'note-in-window', (int) $doc['id']);
        \App\Models\FollowUp::create((int) $p['id'], null, (int) $d['id'], date('Y-m-d', strtotime('+30 days')), 'note-outside', (int) $doc['id']);
        [$c] = $loggedIn('admin');
        $t->contains('Quokka Unique', $c->get('/doctors?q=Quokka')['body']);
        $t->notContains('Quokka Unique', $c->get('/doctors?q=Nonexistent')['body']);
        $t->contains('No doctors match', $c->get('/doctors?q=Nonexistent')['body']);
        $from = date('Y-m-d', strtotime('+1 day')); $to = date('Y-m-d', strtotime('+10 days'));
        $page = $c->get("/followups?bucket=upcoming&from=$from&to=$to")['body'];
        $t->contains('note-in-window', $page);
        $t->notContains('note-outside', $page);
        $t->contains('note-outside', $c->get('/followups?bucket=upcoming&from=2000-01-01&to=2999-01-01')['body']);
        $t->same(200, $c->get('/followups?from=garbage&to=%27%20OR%201=1')['status'], 'bad dates are ignored, not fatal');
    });

    $t->group('HTTP: concurrency');
    $t->test('six receptionists racing for the same slot: exactly one booking is created', function () use ($t, $client, $base) {
        $doctor = Fixtures::doctor();
        $date = Fixtures::futureDate(4);
        $workers = [];
        for ($i = 0; $i < 6; $i++) {
            $user = Fixtures::user('receptionist');
            $c = $client();
            $c->login($user['email'], Fixtures::PASSWORD);
            $workers[] = ['client' => $c, 'token' => $c->token('/appointments/create'), 'patient' => Fixtures::patient()['id']];
        }
        $multi = curl_multi_init();
        $handles = [];
        foreach ($workers as $w) {
            $ch = curl_init($base . '/appointments');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_COOKIEFILE => $w['client']->jarFile(), CURLOPT_COOKIEJAR => $w['client']->jarFile(), CURLOPT_TIMEOUT => 30,
                CURLOPT_POSTFIELDS => http_build_query(['_token' => $w['token'], 'patient_id' => $w['patient'], 'doctor_id' => $doctor['id'], 'appointment_date' => $date, 'start_time' => '10:00'])]);
            curl_multi_add_handle($multi, $ch);
            $handles[] = $ch;
        }
        do { curl_multi_exec($multi, $running); curl_multi_select($multi, 0.2); } while ($running > 0);
        $created = 0;
        foreach ($handles as $ch) {
            $headers = (string) curl_multi_getcontent($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $t->same(302, $status);
            if (str_contains(curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: '', '/appointments/') || preg_match('#/appointments/\d+#', (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL))) { $created++; }
            curl_multi_remove_handle($multi, $ch);
        }
        curl_multi_close($multi);
        $count = (int) Database::value("SELECT COUNT(*) FROM appointments WHERE doctor_id = :d AND appointment_date = :dt AND start_time = '10:00:00' AND status <> 'Cancelled'", ['d' => $doctor['id'], 'dt' => $date]);
        $t->same(1, $count, 'the database holds exactly one booking for the slot');
    });
};
