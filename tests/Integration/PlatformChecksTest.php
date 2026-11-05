<?php
declare(strict_types=1);

use App\Config\Roles;
use App\Core\Config;
use App\Core\Database;
use App\Core\SqlScriptRunner;
use App\Models\Doctor;
use App\Models\FollowUp;
use App\Models\Specialty;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\DateRange;
use App\Services\DoctorService;
use App\Services\ReportService;
use Tests\Support\Fixtures;
use Tests\Support\Tester;

return static function (Tester $t): void {
    [$before, $after] = $GLOBALS['__test_hooks'];
    $t->group('Integration: schema integrity (keys, types, delete rules)', $before, $after);

    $t->test('every table is InnoDB utf8mb4 and has a primary key', function () use ($t) {
        $rows = Database::all("SELECT t.table_name, t.engine, t.table_collation,
                (SELECT COUNT(*) FROM information_schema.table_constraints c WHERE c.table_schema = t.table_schema AND c.table_name = t.table_name AND c.constraint_type = 'PRIMARY KEY') AS pk
            FROM information_schema.tables t WHERE t.table_schema = DATABASE() AND t.table_type = 'BASE TABLE'");
        $names = array_map(fn ($r) => $r['table_name'] ?? $r['TABLE_NAME'], $rows);
        sort($names);
        $expected = ['appointments', 'audit_logs', 'clinics', 'doctor_schedules', 'doctors', 'follow_ups', 'invoice_items', 'invoices', 'login_attempts', 'number_sequences', 'patients', 'payments', 'permissions', 'prescription_items', 'prescriptions', 'role_permissions', 'roles', 'services', 'settings', 'specialties', 'users', 'visit_amendments', 'visits'];
        $t->same($expected, $names, 'the schema contains exactly the documented tables');
        foreach ($rows as $r) {
            $t->same('InnoDB', $r['engine'] ?? $r['ENGINE'], $r['table_name'] ?? $r['TABLE_NAME']);
            $t->true(str_starts_with((string) ($r['table_collation'] ?? $r['TABLE_COLLATION']), 'utf8mb4'), 'utf8mb4');
            $t->same(1, (int) ($r['pk'] ?? $r['PK']), 'primary key on ' . ($r['table_name'] ?? $r['TABLE_NAME']));
        }
    });
    $t->test('no money column uses a floating-point type', function () use ($t) {
        $moneyColumns = ['consultation_fee', 'price', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'amount_paid', 'adjusted_amount', 'balance', 'unit_price', 'line_total', 'amount'];
        $rows = Database::all("SELECT table_name, column_name, data_type FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name IN ('" . implode("','", $moneyColumns) . "')");
        $t->true(count($rows) >= 12);
        foreach ($rows as $r) {
            $t->same('decimal', strtolower((string) ($r['data_type'] ?? $r['DATA_TYPE'])), ($r['table_name'] ?? $r['TABLE_NAME']) . '.' . ($r['column_name'] ?? $r['COLUMN_NAME']));
        }
    });
    $t->test('foreign keys never cascade deletes except for pure child rows', function () use ($t) {
        $allowedCascade = ['role_permissions', 'doctor_schedules', 'prescription_items', 'invoice_items'];
        $rows = Database::all('SELECT table_name, constraint_name, delete_rule FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE()');
        $t->true(count($rows) >= 40, 'foreign keys present');
        foreach ($rows as $r) {
            $table = $r['table_name'] ?? $r['TABLE_NAME'];
            $rule = $r['delete_rule'] ?? $r['DELETE_RULE'];
            if ($rule === 'CASCADE') {
                $t->true(in_array($table, $allowedCascade, true), "$table must not cascade deletes");
            }
        }
    });
    $t->test('deleting a referenced record is refused (patient with history, specialty with doctors)', function () use ($t) {
        $d = Fixtures::doctor();
        $p = Fixtures::patient();
        $admin = Fixtures::user('admin');
        \App\Models\Appointment::create(['patient_id' => $p['id'], 'doctor_id' => $d['id'], 'appointment_date' => Fixtures::futureDate(), 'start_time' => '10:00', 'end_time' => '10:15', 'booking_type' => 'Scheduled', 'status' => 'Scheduled'], (int) $admin['id']);
        $t->throws(PDOException::class, fn () => Database::run('DELETE FROM patients WHERE id = :i', ['i' => $p['id']]), 'foreign key');
        $t->throws(PDOException::class, fn () => Database::run('DELETE FROM specialties WHERE id = :i', ['i' => $d['specialty_id']]), 'foreign key');
        $t->throws(PDOException::class, fn () => Database::run('DELETE FROM users WHERE id = :i', ['i' => $d['user_id']]), 'foreign key');
    });
    $t->test('CHECK constraints reject impossible data', function () use ($t) {
        $d = Fixtures::doctor();
        $t->throws(PDOException::class, fn () => Database::run('INSERT INTO doctor_schedules (doctor_id, weekday, start_time, end_time) VALUES (:d, 9, \'09:00\', \'10:00\')', ['d' => $d['id']]));
        $t->throws(PDOException::class, fn () => Database::run('INSERT INTO doctor_schedules (doctor_id, weekday, start_time, end_time) VALUES (:d, 1, \'10:00\', \'09:00\')', ['d' => $d['id']]));
        $t->throws(PDOException::class, fn () => Database::run("INSERT INTO services (name, price) VALUES ('Negative', -1)"));
    });

    $t->group('Integration: specialties, doctors and account linking', $before, $after);
    $t->test('specialty CRUD: unique names, activation, doctor counts', function () use ($t) {
        $id = Specialty::create('Neurology ' . Fixtures::unique(), 'Brain and nerves');
        $s = Specialty::find($id);
        $t->true(Specialty::nameTaken($s['name']));
        $t->false(Specialty::nameTaken($s['name'], $id), 'editing itself is not a duplicate');
        Specialty::update($id, 'Neurology Renamed', null);
        $t->same('Neurology Renamed', Specialty::find($id)['name']);
        Specialty::setActive($id, false);
        $t->false(in_array($id, array_map('intval', array_column(Specialty::all(true), 'id')), true), 'inactive specialties are hidden from pickers');
        $t->true(in_array($id, array_map('intval', array_column(Specialty::all(false), 'id')), true));
        $t->throws(PDOException::class, fn () => Specialty::create('Neurology Renamed', null), 'Duplicate');
    });
    $t->test('creating a doctor creates a linked doctor-role login with a temporary password', function () use ($t) {
        $spec = Specialty::create('Cardiology ' . Fixtures::unique(), null);
        $profile = DoctorService::validateProfile(['specialty_id' => (string) $spec, 'consultation_fee' => '2500', 'slot_minutes' => '20', 'phone' => '0300 5551234', 'room' => 'R-9'])->validated();
        $created = DoctorService::createWithAccount(['full_name' => 'Dr. Linked Account', 'email' => 'Linked@Test.example', 'phone' => null], $profile);
        $user = User::findWithRole($created['user_id']);
        $doctor = Doctor::find($created['doctor_id']);
        $t->same(Roles::DOCTOR, $user['role_code']);
        $t->same('linked@test.example', $user['email']);
        $t->same(1, (int) $user['must_change_password']);
        $t->same((int) $user['id'], (int) $doctor['user_id'], 'profile points at the login account');
        $t->same('2500.00', $doctor['consultation_fee']);
        $t->same(20, (int) $doctor['slot_minutes']);
        $t->same((int) $created['doctor_id'], User::doctorIdForUser((int) $user['id']));
        $t->throws(PDOException::class, fn () => Doctor::create(['user_id' => $user['id'], 'specialty_id' => $spec, 'license_no' => null, 'qualification' => null, 'phone' => null, 'room' => null, 'consultation_fee' => '1.00', 'slot_minutes' => 15]), 'Duplicate');
        // role of an account that owns a doctor profile cannot be changed
        $admin = Fixtures::actAs(Fixtures::user('admin'));
        $recRole = (int) Database::value("SELECT id FROM roles WHERE code = 'receptionist'");
        $t->throws(App\Core\BusinessRuleException::class, fn () => App\Services\UserService::update((int) $user['id'], ['role_id' => $recRole, 'full_name' => 'X', 'email' => $user['email'], 'phone' => null]), 'doctor profile');
    });
    $t->test('profile validation: specialty must exist, fee and visit length are bounded', function () use ($t) {
        $t->true(isset(DoctorService::validateProfile(['specialty_id' => '999999', 'consultation_fee' => '100', 'slot_minutes' => '15'])->errors()['specialty_id']));
        $spec = (string) Specialty::create('Valid ' . Fixtures::unique(), null);
        $t->true(isset(DoctorService::validateProfile(['specialty_id' => $spec, 'consultation_fee' => '-5', 'slot_minutes' => '15'])->errors()['consultation_fee']));
        $t->true(isset(DoctorService::validateProfile(['specialty_id' => $spec, 'consultation_fee' => '100', 'slot_minutes' => '3'])->errors()['slot_minutes']));
        $t->true(isset(DoctorService::validateProfile(['specialty_id' => $spec, 'consultation_fee' => '100', 'slot_minutes' => '500'])->errors()['slot_minutes']));
        $t->false(DoctorService::validateProfile(['specialty_id' => $spec, 'consultation_fee' => '100.50', 'slot_minutes' => '30'])->fails());
    });
    $t->test('a doctor with upcoming appointments cannot be deactivated', function () use ($t) {
        $admin = Fixtures::user('admin');
        $d = Fixtures::doctor();
        $apt = \App\Models\Appointment::create(['patient_id' => Fixtures::patient()['id'], 'doctor_id' => $d['id'], 'appointment_date' => Fixtures::futureDate(), 'start_time' => '10:00', 'end_time' => '10:15', 'booking_type' => 'Scheduled', 'status' => 'Scheduled'], (int) $admin['id']);
        $t->throws(App\Core\BusinessRuleException::class, fn () => DoctorService::setActive((int) $d['id'], false), 'upcoming');
        \App\Models\Appointment::setStatus($apt, 'Cancelled', 'x');
        DoctorService::setActive((int) $d['id'], false);
        $t->same(0, (int) Doctor::find((int) $d['id'])['is_active']);
    });

    $t->test('doctor directory search: by name, specialty and status', function () use ($t) {
        $a = Fixtures::doctor();
        $b = Fixtures::doctor();
        Database::run('UPDATE users SET full_name = :n WHERE id = :i', ['n' => 'Dr. Zebra Finder', 'i' => $a['user_id']]);
        $ids = fn (array $f) => array_map('intval', array_column(Doctor::search($f), 'id'));
        $t->same([(int) $a['id']], $ids(['q' => 'Zebra']));
        $t->same([], $ids(['q' => '%']), 'wildcards are literal');
        $t->true(in_array((int) $b['id'], $ids(['specialty_id' => (int) $b['specialty_id']]), true));
        Doctor::setActive((int) $b['id'], false);
        $t->false(in_array((int) $b['id'], $ids(['status' => 'active']), true));
        $t->true(in_array((int) $b['id'], $ids(['status' => 'inactive']), true));
        $t->true(in_array((int) $b['id'], $ids([]), true), 'no filter lists everyone');
    });

    $t->group('Integration: follow-up filters and an empty database', $before, $after);
    $t->test('follow-ups can be filtered by date range and doctor', function () use ($t) {
        $d1 = Fixtures::doctor(); $d2 = Fixtures::doctor(); $u = Fixtures::user('doctor'); $p = Fixtures::patient();
        foreach ([['+1 day', $d1], ['+5 days', $d1], ['+10 days', $d1], ['+5 days', $d2]] as [$offset, $d]) {
            FollowUp::create((int) $p['id'], null, (int) $d['id'], date('Y-m-d', strtotime($offset)), null, (int) $u['id']);
        }
        $range = fn (array $f) => count(FollowUp::paginate($f + ['bucket' => 'all'], 1, 50)->items);
        $t->same(4, $range([]));
        $t->same(3, $range(['doctor_id' => $d1['id']]));
        $t->same(2, $range(['from' => date('Y-m-d', strtotime('+4 days')), 'to' => date('Y-m-d', strtotime('+6 days'))]));
        $t->same(1, $range(['doctor_id' => $d2['id'], 'from' => date('Y-m-d', strtotime('+4 days')), 'to' => date('Y-m-d', strtotime('+6 days'))]));
        $t->same(0, $range(['from' => date('Y-m-d', strtotime('+20 days'))]));
    });
    $t->test('every dashboard and report works on an empty database (all zeros, no errors)', function () use ($t) {
        $range = DateRange::fromInput('30d', null, null);
        $a = DashboardService::admin($range);
        $t->same(0, $a['kpis']['revenue']);
        $t->same(0, $a['kpis']['today_total']);
        $t->same(['completed' => 0, 'pending' => 0, 'cancelled' => 0], $a['attendance']);
        $t->same(30, count($a['trend']['labels']));
        $t->same(0, array_sum($a['trend']['completed']));
        $t->same(0, DashboardService::accountant($range)['kpis']['outstanding']);
        $t->same(0, DashboardService::receptionist()['kpis']['today_total']);
        $t->same([], DashboardService::receptionist()['queue']);
        $f = ReportService::financial($range);
        $t->same([0, 0, 0], [$f['collected'], $f['invoiced'], $f['outstanding']]);
        $t->same([], $f['by_method']);
        $o = ReportService::operational($range);
        $t->same(0, $o['totals']['total']);
        $t->same([], $o['by_doctor']);
    });

    // ---- The next groups run WITHOUT the rollback wrapper, because they test commit/rollback and whole-database behaviour. ----
    $t->group('Integration: transaction atomicity');
    $t->test('a failure in the middle of a transaction undoes every earlier step', function () use ($t) {
        $email = 'atomic' . Fixtures::unique() . '@test.example';
        $profile = ['specialty_id' => 999999, 'license_no' => null, 'qualification' => null, 'phone' => null, 'room' => null, 'consultation_fee' => '1.00', 'slot_minutes' => 15];
        $t->throws(PDOException::class, fn () => DoctorService::createWithAccount(['full_name' => 'Dr. Atomic', 'email' => $email, 'phone' => null], $profile));
        $t->same(null, User::findByEmail($email), 'the login account must not survive a failed doctor creation');

        $before = (int) Database::value('SELECT COUNT(*) FROM specialties');
        $t->throws(RuntimeException::class, function () {
            Database::transaction(function () {
                Specialty::create('Rolled back ' . Fixtures::unique(), null);
                throw new RuntimeException('boom');
            });
        }, 'boom');
        $t->same($before, (int) Database::value('SELECT COUNT(*) FROM specialties'));
        Database::transaction(fn () => Specialty::create('Committed ' . Fixtures::unique(), null));
        $t->same($before + 1, (int) Database::value('SELECT COUNT(*) FROM specialties'));
        Database::run("DELETE FROM specialties WHERE name LIKE 'Committed %'");
    });

    $t->group('Integration: demo seed data and backup/restore');
    $t->test('seed loads into a clean database, is internally consistent, and refuses to run twice', function () use ($t) {
        $main = (string) Config::get('database.name');
        $seedDb = $main . '_seed';
        $db = Config::get('database');
        $server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], $db['port']), $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $server->exec("DROP DATABASE IF EXISTS `$seedDb`");
        $server->exec("CREATE DATABASE `$seedDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $server->exec("USE `$seedDb`");
        SqlScriptRunner::run($server, BASE_PATH . '/database/schema.sql');
        Config::set('database.name', $seedDb);
        Database::disconnect();
        try {
            require_once BASE_PATH . '/database/seed.php';
            $made = seedDemoData();
            $t->same(6, $made['doctors']);
            $t->same(48, $made['patients']);
            $t->true($made['appointments'] > 500);
            $t->throws(RuntimeException::class, fn () => seedDemoData(), 'fresh database');

            // every demo account is forced to change its well-known password
            $t->same(0, (int) Database::value('SELECT COUNT(*) FROM users WHERE must_change_password = 0'));
            $t->true(password_verify('ChangeMe!2026', (string) Database::value("SELECT password_hash FROM users WHERE email = 'admin@demo-clinic.test'")));

            // money is internally consistent: balance = total - paid - adjusted, paid = payments - refunds
            $bad = Database::value("SELECT COUNT(*) FROM invoices WHERE balance <> total - amount_paid - adjusted_amount AND status <> 'Void'");
            $t->same(0, (int) $bad, 'invoice balances');
            $mismatch = Database::value("SELECT COUNT(*) FROM invoices i WHERE i.amount_paid <> COALESCE((SELECT SUM(CASE entry_type WHEN 'Payment' THEN amount WHEN 'Refund' THEN -amount ELSE 0 END) FROM payments p WHERE p.invoice_id = i.id), 0)");
            $t->same(0, (int) $mismatch, 'amount_paid equals the ledger');
            $badStatus = Database::value("SELECT COUNT(*) FROM invoices WHERE (status = 'Paid' AND balance <> 0) OR (status = 'Unpaid' AND amount_paid <> 0) OR (status = 'Partial' AND (balance = 0 OR amount_paid = 0))");
            $t->same(0, (int) $badStatus, 'invoice statuses');
            $lines = Database::value('SELECT COUNT(*) FROM invoices i WHERE i.subtotal <> (SELECT SUM(line_total) FROM invoice_items WHERE invoice_id = i.id)');
            $t->same(0, (int) $lines, 'subtotal equals the sum of its lines');
            // clinical and scheduling consistency
            $t->same(0, (int) Database::value("SELECT COUNT(*) FROM visits v LEFT JOIN appointments a ON a.id = v.appointment_id WHERE v.status = 'Finalized' AND (a.status IS NULL OR a.status <> 'Completed')"), 'finalized visits belong to completed appointments');
            $t->same(0, (int) Database::value("SELECT COUNT(*) FROM appointments a WHERE a.status = 'Completed' AND NOT EXISTS (SELECT 1 FROM visits v WHERE v.appointment_id = a.id)"), 'completed appointments have a visit');
            $t->same(0, (int) Database::value("SELECT COUNT(*) FROM (SELECT doctor_id, appointment_date, start_time FROM appointments WHERE booking_type = 'Scheduled' AND status NOT IN ('Cancelled','No Show') GROUP BY 1,2,3 HAVING COUNT(*) > 1) x"), 'no double bookings');
            // sequences continue after the seed instead of colliding
            $t->same((int) Database::value("SELECT MAX(CAST(SUBSTRING(patient_code, 4) AS UNSIGNED)) FROM patients"), (int) Database::value("SELECT last_value FROM number_sequences WHERE name = 'patient'"));

            // the dashboards run against this larger dataset
            $range = DateRange::fromInput('30d', null, null);
            $admin = DashboardService::admin($range);
            $t->true($admin['kpis']['revenue'] > 0 && $admin['secondary']['patients'] === 48);
            $t->same(DashboardService::collected($range), ReportService::financial($range)['collected'], 'dashboard and report agree');

            // ---- backup / restore round trip ----
            $dump = trim((string) shell_exec('mysqldump --version 2>/dev/null'));
            if ($dump === '') {
                echo "      (backup round-trip BLOCKED: mysqldump not found on PATH)\n";
            } else {
                $file = tempnam(sys_get_temp_dir(), 'crmbk') . '.sql';
                $copy = $main . '_restore';
                $server->exec("DROP DATABASE IF EXISTS `$copy`");
                exec('php ' . escapeshellarg(BASE_PATH . '/bin/backup.php') . ' --db=' . escapeshellarg($seedDb) . ' --out=' . escapeshellarg($file) . ' 2>&1', $out1, $code1);
                $t->same(0, $code1, implode("\n", $out1));
                exec('php ' . escapeshellarg(BASE_PATH . '/bin/restore.php') . ' ' . escapeshellarg($file) . ' --db=' . escapeshellarg($copy) . ' 2>&1', $out2, $code2);
                $t->same(0, $code2, implode("\n", $out2));
                foreach (['users', 'patients', 'appointments', 'visits', 'invoices', 'payments', 'audit_logs', 'number_sequences'] as $table) {
                    $t->same((int) Database::value("SELECT COUNT(*) FROM `$seedDb`.`$table`"), (int) Database::value("SELECT COUNT(*) FROM `$copy`.`$table`"), "restored $table");
                }
                $t->same(8, (int) Database::value("SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = '$copy'"), 'triggers restored');
                exec('php ' . escapeshellarg(BASE_PATH . '/bin/restore.php') . ' ' . escapeshellarg($file) . ' --db=' . escapeshellarg($copy) . ' 2>&1', $out3, $code3);
                $t->true($code3 !== 0 && str_contains(implode(' ', $out3), 'already contains'), 'restore refuses to overwrite silently');
                $server->exec("DROP DATABASE IF EXISTS `$copy`");
                @unlink($file);
            }
        } finally {
            Config::set('database.name', $main);
            Database::disconnect();
            $server->exec("DROP DATABASE IF EXISTS `$seedDb`");
        }
    });
};
