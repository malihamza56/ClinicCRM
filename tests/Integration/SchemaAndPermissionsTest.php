<?php
declare(strict_types=1);

use App\Config\Permissions;
use App\Core\Database;
use App\Core\Router;
use App\Models\Appointment;
use App\Services\AuditService;
use App\Services\BillingService;
use Tests\Support\Fixtures;
use Tests\Support\Tester;

return static function (Tester $t): void {
    [$before, $after] = $GLOBALS['__test_hooks'];
    $t->group('Integration: database guarantees', $before, $after);

    $t->test('payments are append-only (UPDATE and DELETE are blocked by the database)', function () use ($t) {
        $admin = Fixtures::actAs(Fixtures::user('admin'));
        $doctor = Fixtures::doctor();
        $patient = Fixtures::patient();
        $invoiceId = BillingService::createInvoice((int) $patient['id'], ['consultation_doctor_id' => (int) $doctor['id'], 'services' => [], 'discount' => '', 'notes' => null, 'visit_id' => null], (int) $admin['id']);
        $paymentId = BillingService::recordPayment($invoiceId, '100', 'Cash', null, (int) $admin['id']);
        $t->throws(PDOException::class, fn () => Database::run('UPDATE payments SET amount = 1 WHERE id = :i', ['i' => $paymentId]), 'append-only');
        $t->throws(PDOException::class, fn () => Database::run('DELETE FROM payments WHERE id = :i', ['i' => $paymentId]), 'cannot be deleted');
        $t->throws(PDOException::class, fn () => Database::run('DELETE FROM invoices WHERE id = :i', ['i' => $invoiceId]), 'void');
    });
    $t->test('audit log entries cannot be changed or deleted', function () use ($t) {
        AuditService::log('test.event', 'test', 1, 'A test entry');
        $id = (int) Database::value('SELECT MAX(id) FROM audit_logs');
        $t->throws(PDOException::class, fn () => Database::run("UPDATE audit_logs SET summary = 'x' WHERE id = :i", ['i' => $id]), 'cannot be changed');
        $t->throws(PDOException::class, fn () => Database::run('DELETE FROM audit_logs WHERE id = :i', ['i' => $id]), 'cannot be deleted');
    });
    $t->test('a doctor cannot be double-booked for the same slot, even if the service layer is bypassed', function () use ($t) {
        $admin = Fixtures::user('admin');
        $d = Fixtures::doctor();
        $a = Fixtures::patient();
        $b = Fixtures::patient();
        $date = Fixtures::futureDate();
        $row = fn (array $p) => ['patient_id' => $p['id'], 'doctor_id' => $d['id'], 'appointment_date' => $date, 'start_time' => '10:00', 'end_time' => '10:15', 'booking_type' => 'Scheduled', 'status' => 'Scheduled'];
        $first = Appointment::create($row($a), (int) $admin['id']);
        $t->throws(PDOException::class, fn () => Appointment::create($row($b), (int) $admin['id']), 'Duplicate');
        Appointment::setStatus($first, 'Cancelled', 'test');
        Appointment::create($row($b), (int) $admin['id']); // slot is free again
        $t->true(true);
    });
    $t->test('finalized visits cannot be deleted', function () use ($t) {
        $admin = Fixtures::user('admin');
        $d = Fixtures::doctor();
        $p = Fixtures::patient();
        $vid = Database::insert("INSERT INTO visits (patient_id, doctor_id, visited_at, status, created_by) VALUES (:p, :d, NOW(), 'Finalized', :u)", ['p' => $p['id'], 'd' => $d['id'], 'u' => $admin['id']]);
        $t->throws(PDOException::class, fn () => Database::run('DELETE FROM visits WHERE id = :i', ['i' => $vid]), 'cannot be deleted');
    });
    $t->test('the same person cannot be registered twice (unique identity key)', function () use ($t) {
        Fixtures::patient(['first_name' => 'Dup', 'last_name' => 'Licate', 'date_of_birth' => '1980-01-01', 'phone' => '03001112222']);
        $t->throws(PDOException::class, fn () => Fixtures::patient(['first_name' => 'dup', 'last_name' => 'licate', 'date_of_birth' => '1980-01-01', 'phone' => '03001112222']));
    });

    $t->group('Integration: role-permission matrix', $before, $after);
    // Written out by hand (independent from schema.sql) so an accidental change to the matrix is caught.
    $expected = [
        'admin' => ['dashboard.view', 'users.manage', 'specialties.manage', 'doctors.view', 'doctors.manage', 'services.manage', 'settings.manage', 'patients.view', 'patients.create', 'patients.update', 'patients.archive', 'appointments.view', 'appointments.manage', 'queue.view', 'followups.view', 'followups.update', 'invoices.view', 'payments.view', 'reports.financial', 'reports.operational', 'audit.view', 'exports.patients', 'exports.appointments', 'exports.financial'],
        'doctor' => ['dashboard.view', 'patients.view', 'appointments.view', 'appointments.consult', 'queue.view', 'visits.view', 'visits.write', 'visits.amend', 'followups.view', 'followups.create', 'followups.update'],
        'receptionist' => ['dashboard.view', 'doctors.view', 'patients.view', 'patients.create', 'patients.update', 'appointments.view', 'appointments.manage', 'queue.view', 'followups.view', 'followups.update', 'invoices.view', 'invoices.create', 'payments.record'],
        'accountant' => ['dashboard.view', 'patients.view_billing', 'invoices.view', 'invoices.create', 'invoices.void', 'payments.view', 'payments.record', 'payments.refund', 'payments.adjust', 'reports.financial', 'exports.financial'],
        'patient' => [],
    ];
    foreach ($expected as $role => $codes) {
        $t->test("$role has exactly the documented permissions", function () use ($t, $role, $codes) {
            $actual = array_column(Database::all('SELECT p.code FROM role_permissions rp JOIN roles r ON r.id = rp.role_id JOIN permissions p ON p.id = rp.permission_id WHERE r.code = :r', ['r' => $role]), 'code');
            sort($actual);
            sort($codes);
            $t->same($codes, $actual);
        });
    }
    $t->test('clinical data is reachable only by doctors; money-moving rights stay with billing roles', function () use ($t, $expected) {
        foreach (['admin', 'receptionist', 'accountant', 'patient'] as $role) {
            $t->false((bool) array_intersect(['visits.view', 'visits.write', 'visits.amend'], $expected[$role]), "$role must not see clinical records");
        }
        foreach (['doctor', 'admin'] as $role) {
            $t->false((bool) array_intersect(['payments.record', 'payments.refund', 'payments.adjust', 'invoices.void'], $expected[$role]), "$role must not move money");
        }
        $t->false(in_array('payments.refund', $expected['receptionist'], true));
        $t->false(in_array('reports.financial', $expected['receptionist'], true));
        $t->false(in_array('patients.view', $expected['accountant'], true), 'accountant sees billing-basic patient data only');
    });
    $t->test('code constants and database permissions are identical', function () use ($t) {
        $db = array_column(Database::all('SELECT code FROM permissions'), 'code');
        $code = Permissions::all();
        sort($db);
        sort($code);
        $t->same($code, $db);
    });
    $t->test('every route has a handler that exists and a valid access rule', function () use ($t) {
        $router = new Router();
        (require BASE_PATH . '/routes/web.php')($router);
        $valid = Permissions::all();
        $t->true(count($router->routes()) > 60, 'route table loaded');
        foreach ($router->routes() as $route) {
            [$class, $method] = $route->handler;
            $label = $route->method . ' ' . $route->pattern;
            $t->true(class_exists($class) && method_exists($class, $method), "$label handler missing");
            $t->true($route->public || $route->anyAuthenticated || $route->permissions !== [], "$label has no access rule (would be denied)");
            foreach ($route->permissions as $permission) {
                $t->true(in_array($permission, $valid, true), "$label uses unknown permission $permission");
            }
            if ($route->method === 'POST') {
                $t->false($route->public && $route->pattern !== '/login', "$label: public POST");
            }
        }
    });
};
