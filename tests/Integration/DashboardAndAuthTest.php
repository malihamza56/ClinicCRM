<?php
declare(strict_types=1);

use App\Config\Roles;
use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Config;
use App\Core\Database;
use App\Models\Appointment;
use App\Models\FollowUp;
use App\Models\User;
use App\Services\AuthService;
use App\Services\BillingService;
use App\Services\DashboardService;
use App\Services\DateRange;
use App\Services\ReportService;
use App\Services\UserService;
use Tests\Support\Fixtures;
use Tests\Support\Tester;

return static function (Tester $t): void {
    [$before, $after] = $GLOBALS['__test_hooks'];
    $t->group('Integration: dashboards and reports match known data', $before, $after);

    $seed = function () {
        $u = Fixtures::actAs(Fixtures::user('receptionist'));
        $d1 = Fixtures::doctor(15, '1000.00');
        $d2 = Fixtures::doctor(15, '700.00');
        $today = date('Y-m-d');
        $n = 0;
        $mk = function (array $d, string $status, string $type = 'Scheduled', ?string $date = null) use ($u, $today, &$n) {
            $start = sprintf('%02d:%02d', 9 + intdiv(++$n, 4), ($n % 4) * 15);
            return Appointment::create(['patient_id' => Fixtures::patient()['id'], 'doctor_id' => $d['id'], 'appointment_date' => $date ?? $today, 'start_time' => $start, 'end_time' => date('H:i', strtotime($start) + 900), 'booking_type' => $type, 'status' => $status], (int) $u['id']);
        };
        foreach (['Completed', 'Completed', 'Waiting', 'In Progress', 'Scheduled', 'Cancelled', 'No Show'] as $s) { $mk($d1, $s); }
        $mk($d2, 'Waiting', 'Walk-in');
        $mk($d2, 'Confirmed');
        $mk($d2, 'Completed', 'Scheduled', date('Y-m-d', strtotime('-2 days')));     // outside "today"
        return [$u, $d1, $d2];
    };

    $t->test('admin tiles: today by status, ongoing, new patients', function () use ($t, $seed) {
        $seed();
        $data = DashboardService::admin(DateRange::fromInput('today', null, null));
        $k = $data['kpis'];
        $t->same(3, $k['ongoing'], 'waiting 2 + in progress 1');
        $t->same(2, $k['completed']);
        $t->same(2, $k['cancelled'], 'cancelled + no-show');
        $t->same(['completed' => 2, 'pending' => 5, 'cancelled' => 2], $data['attendance']);   // pending: waiting 2, in progress 1, scheduled 1, confirmed 1
        $t->same(9, $k['today_total']);
        $t->same(10, $data['secondary']['patients']);
        $t->same(2, $data['secondary']['doctors']);
    });
    $t->test('trend series put each appointment on its own day', function () use ($t, $seed) {
        $seed();
        $trend = DashboardService::bookingTrend(DateRange::fromInput('7d', null, null));
        $t->same(7, count($trend['labels']));
        $t->same(1, $trend['completed'][4], '2 days ago (index 4 of 7)');
        $t->same(2, $trend['completed'][6], 'today');
        $t->same(2, $trend['cancelled'][6]);
        $t->same(array_sum($trend['completed']) + array_sum($trend['pending']) + array_sum($trend['cancelled']), 10);
    });
    $t->test('doctor and receptionist dashboards count only what they should', function () use ($t, $seed) {
        [$u, $d1, $d2] = $seed();
        $doc = DashboardService::doctor((int) $d1['id']);
        $t->same(7, $doc['kpis']['today_total']);
        $t->same(1, $doc['kpis']['waiting']);
        $t->same(1, $doc['kpis']['in_progress']);
        $t->same(2, count($doc['queue']));
        $rec = DashboardService::receptionist();
        $t->same(9, $rec['kpis']['today_total']);
        $t->same(3, $rec['kpis']['waiting'], 'waiting 2 (incl. the walk-in) + in progress 1');
        $t->same(2, $rec['kpis']['expected']);
        $t->same(10, $rec['kpis']['new_patients'], 'every fixture patient was registered today');
    });
    $t->test('money tiles: collected = payments - refunds; outstanding excludes void; adjustments are not cash', function () use ($t) {
        $acc = Fixtures::actAs(Fixtures::user('accountant'));
        $d = Fixtures::doctor(15, '1000.00');
        $mk = fn (string $fee) => BillingService::createInvoice((int) Fixtures::patient()['id'], ['consultation_doctor_id' => (int) Fixtures::doctor(15, $fee)['id'], 'services' => [], 'discount' => '', 'notes' => null, 'visit_id' => null], (int) $acc['id']);
        $a = $mk('1000.00'); $b = $mk('500.00'); $c = $mk('700.00'); $v = $mk('300.00');
        $uid = (int) $acc['id'];
        BillingService::recordPayment($a, '1000', 'Cash', null, $uid);                 // A paid in full
        BillingService::recordPayment($b, '200', 'Card', null, $uid);                  // B partial
        BillingService::recordRefund($b, '50', 'Card', 'Overcharged', $uid);           // refund 50
        BillingService::recordAdjustment($c, '100', 'Goodwill', $uid);                 // C written off 100, still unpaid 600
        BillingService::voidInvoice($v, 'Mistake', $uid);
        $range = DateRange::fromInput('today', null, null);
        $t->same(115000, DashboardService::collected($range), '1000 + 200 - 50');
        $t->same(95000, DashboardService::outstanding(), 'B owes 350.00, C owes 600.00 (after the 100.00 write-off); A is paid, V is void');
        $r = ReportService::financial($range);
        $t->same(115000, $r['collected']);
        $t->same(120000, $r['payments']);
        $t->same(5000, $r['refunds']);
        $t->same(10000, $r['adjustments']);
        $t->same(220000, $r['invoiced'], 'void invoice excluded');
        $t->same(3, $r['invoice_count']);
        $methods = array_column($r['by_method'], 'amount', 'method');
        $t->same(100000, $methods['Cash']);
        $t->same(15000, $methods['Card'], 'card: 200 received - 50 refunded');
        $acct = DashboardService::accountant($range);
        $t->same(115000, $acct['kpis']['revenue']);
        $t->same(5000, $acct['kpis']['refunds']);
        $t->same(3, $acct['kpis']['invoices']);
        $yesterday = DateRange::fromInput('custom', date('Y-m-d', strtotime('-5 days')), date('Y-m-d', strtotime('-3 days')));
        $t->same(0, DashboardService::collected($yesterday), 'payments outside the range are not counted');
    });

    $t->test('operational report totals and per-doctor rows', function () use ($t, $seed) {
        $seed();
        $r = ReportService::operational(DateRange::fromInput('7d', null, null));
        $t->same(10, $r['totals']['total']);
        $t->same(3, $r['totals']['completed']);
        $t->same(1, $r['totals']['cancelled']);
        $t->same(1, $r['totals']['no_show']);
        $t->same(1, $r['totals']['walk_ins']);
        $perDoctor = array_column($r['by_doctor'], 'total');
        rsort($perDoctor);
        $t->same([7, 3], array_map('intval', $perDoctor));
        $t->same(10, array_sum($r['new_patients']['registrations']));
    });
    $t->test('follow-up buckets: overdue, due today, upcoming', function () use ($t) {
        $d = Fixtures::doctor();
        $u = Fixtures::user('doctor');
        $p = Fixtures::patient();
        foreach (['-3 days', '-1 day', 'today', '+2 days', '+9 days'] as $offset) {
            FollowUp::create((int) $p['id'], null, (int) $d['id'], date('Y-m-d', strtotime($offset)), null, (int) $u['id']);
        }
        $counts = FollowUp::counts();
        $t->same(['overdue' => 2, 'due' => 1, 'upcoming' => 2], $counts);
        $t->same(2, count(FollowUp::paginate(['bucket' => 'overdue'], 1, 20)->items));
        $first = FollowUp::paginate(['bucket' => 'due'], 1, 20)->items[0];
        FollowUp::setStatus((int) $first['id'], 'Completed');
        $t->same(0, FollowUp::counts()['due']);
        $t->same(1, count(FollowUp::paginate(['bucket' => 'completed'], 1, 20)->items));
    });

    $t->group('Integration: sign-in, lockout, accounts', $before, $after);

    $t->test('correct credentials sign in; wrong password and unknown email give the SAME message', function () use ($t) {
        $user = Fixtures::user('receptionist');
        $bad = AuthService::attempt($user['email'], 'wrong-password-1', '10.0.0.1');
        $unknown = AuthService::attempt('nobody@test.example', 'wrong-password-1', '10.0.0.1');
        $t->false($bad['ok']);
        $t->same($bad['message'], $unknown['message'], 'no account enumeration');
        $ok = AuthService::attempt(strtoupper($user['email']), Fixtures::PASSWORD, '10.0.0.2');
        $t->true($ok['ok'], 'email is case-insensitive');
        $t->true(User::findWithRole((int) $user['id'])['last_login_at'] !== null);
    });
    $t->test('five failures lock that email+IP, even for the correct password; other IPs are unaffected', function () use ($t) {
        $user = Fixtures::user('receptionist');
        for ($i = 0; $i < 5; $i++) { AuthService::attempt($user['email'], 'nope-nope-123', '10.9.9.9'); }
        $locked = AuthService::attempt($user['email'], Fixtures::PASSWORD, '10.9.9.9');
        $t->false($locked['ok']);
        $t->contains('Too many', (string) $locked['message']);
        $elsewhere = AuthService::attempt($user['email'], Fixtures::PASSWORD, '10.8.8.8');
        $t->true($elsewhere['ok'], 'an attacker cannot lock the real user out from another address');
    });
    $t->test('deactivated accounts and the reserved patient role cannot sign in', function () use ($t) {
        $user = Fixtures::user('receptionist');
        User::setActive((int) $user['id'], false);
        $t->false(AuthService::attempt($user['email'], Fixtures::PASSWORD, '10.1.1.1')['ok']);
        $patient = Fixtures::user('patient');
        $t->false(AuthService::attempt($patient['email'], Fixtures::PASSWORD, '10.1.1.2')['ok']);
    });
    $t->test('passwords are stored as hashes, never plain text', function () use ($t) {
        $user = Fixtures::user('accountant');
        $t->true(password_verify(Fixtures::PASSWORD, $user['password_hash']));
        $t->false(str_contains($user['password_hash'], Fixtures::PASSWORD));
        $t->same(60, strlen($user['password_hash']));
    });
    $t->test('changing the password needs the current one and a policy-compliant new one', function () use ($t) {
        $user = Fixtures::actAs(Fixtures::user('receptionist'));
        $t->false(AuthService::changePassword((int) $user['id'], 'wrong-current', 'NewPassword2026')['ok']);
        $t->false(AuthService::changePassword((int) $user['id'], Fixtures::PASSWORD, Fixtures::PASSWORD)['ok'], 'must differ');
        $t->false(AuthService::changePassword((int) $user['id'], Fixtures::PASSWORD, 'short1')['ok']);
        $t->true(AuthService::changePassword((int) $user['id'], Fixtures::PASSWORD, 'NewPassword2026')['ok']);
        $fresh = User::findWithRole((int) $user['id']);
        $t->true(password_verify('NewPassword2026', $fresh['password_hash']));
        $t->same(0, (int) $fresh['must_change_password']);
    });
    $t->test('admin safeguards: the last admin cannot be deactivated or demoted; you cannot change your own role', function () use ($t) {
        Database::run("UPDATE users SET is_active = 0 WHERE role_id = (SELECT id FROM roles WHERE code = 'admin')");
        $admin = Fixtures::actAs(Fixtures::user('admin'));
        $other = Fixtures::user('receptionist');
        $t->throws(BusinessRuleException::class, fn () => UserService::setActive((int) $admin['id'], false), 'own account');
        $second = Fixtures::user('admin');
        UserService::setActive((int) $second['id'], false);   // fine: the acting admin remains
        UserService::setActive((int) $second['id'], true);
        $roleReceptionist = (int) Database::value("SELECT id FROM roles WHERE code = 'receptionist'");
        $t->throws(BusinessRuleException::class, fn () => UserService::update((int) $admin['id'], ['role_id' => $roleReceptionist, 'full_name' => 'X', 'email' => $admin['email'], 'phone' => null]), 'own role');
        // another admin acting: demoting the only OTHER admin when they are the last one is blocked
        Fixtures::actAs($other);
        Database::run('UPDATE users SET is_active = 0 WHERE id = :i', ['i' => $second['id']]);
        $t->throws(BusinessRuleException::class, fn () => UserService::setActive((int) $admin['id'], false), 'last active admin');
    });
    $t->test('account validation: unique email, valid role, no patient role for staff', function () use ($t) {
        $existing = Fixtures::user('accountant');
        $role = (int) Database::value("SELECT id FROM roles WHERE code = 'receptionist'");
        $patientRole = (int) Database::value("SELECT id FROM roles WHERE code = 'patient'");
        $t->true(isset(UserService::validate(['full_name' => 'New Person', 'email' => strtoupper($existing['email']), 'role_id' => (string) $role])->errors()['email']));
        $t->true(isset(UserService::validate(['full_name' => 'New Person', 'email' => 'new@test.example', 'role_id' => (string) $patientRole])->errors()['role_id']));
        $t->true(isset(UserService::validate(['full_name' => 'New Person', 'email' => 'new@test.example', 'role_id' => '99999'])->errors()['role_id']));
        $t->false(UserService::validate(['full_name' => 'New Person', 'email' => 'new@test.example', 'role_id' => (string) $role])->fails());
        $created = UserService::create(['full_name' => 'New Person', 'email' => 'New@Test.example', 'role_id' => $role, 'phone' => null]);
        $user = User::findWithRole($created['id']);
        $t->same('new@test.example', $user['email']);
        $t->same(1, (int) $user['must_change_password'], 'temporary passwords must be replaced at first sign-in');
        $t->true(password_verify($created['temporary_password'], $user['password_hash']));
    });
};
