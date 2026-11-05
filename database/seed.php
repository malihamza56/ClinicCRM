<?php
declare(strict_types=1);

/*
 * Fictional demo data - DEVELOPMENT ONLY. Run it ONCE on a freshly installed database:
 *     php bin/install-db.php --seed
 *
 * Everything here is invented: names, phone numbers and clinical notes do not belong to real people.
 * Dates are generated relative to the day you run it, so the dashboards always show recent activity.
 * All demo accounts use the same well-known password and are forced to change it at first sign-in.
 */

use App\Core\Database;
use App\Core\Money;

const DEMO_PASSWORD = 'ChangeMe!2026';

/** @return array<string,int> counts of what was created */
function seedDemoData(): array
{
    mt_srand(20261029); // fixed seed: same demo story every time (apart from the dates)
    $pdo = Database::pdo();
    $today = new DateTimeImmutable('today');

    if ((int) Database::value('SELECT COUNT(*) FROM users') > 0) {
        throw new RuntimeException('The database already contains users. Seed data must only be loaded into a fresh database.');
    }

    $hash = password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT);
    $role = array_column(Database::all('SELECT id, code FROM roles'), 'id', 'code');
    $counters = ['patient' => 0, 'invoice' => 0, 'receipt' => 0, 'prescription' => 0];

    $pdo->beginTransaction();

    // ---- Settings -------------------------------------------------------------------------------
    foreach (['clinic_name' => 'Demo Multi-Specialty Clinic', 'clinic_address' => '12 Garden Road, Sample City', 'clinic_phone' => '021-5550100', 'clinic_email' => 'info@demo-clinic.test'] as $k => $v) {
        Database::run('UPDATE settings SET setting_value = :v WHERE clinic_id = 1 AND setting_key = :k', ['v' => $v, 'k' => $k]);
    }

    // ---- Staff accounts -------------------------------------------------------------------------
    $addUser = static function (string $name, string $email, string $roleCode, ?string $phone) use ($hash, $role): int {
        return Database::insert(
            'INSERT INTO users (role_id, full_name, email, phone, password_hash, must_change_password) VALUES (:r, :n, :e, :p, :h, 1)',
            ['r' => $role[$roleCode], 'n' => $name, 'e' => $email, 'p' => $phone, 'h' => $hash]
        );
    };
    $adminId = $addUser('Zainab Farooq', 'admin@demo-clinic.test', 'admin', '03000000001');
    $recep1 = $addUser('Usman Ali', 'reception@demo-clinic.test', 'receptionist', '03000000002');
    $recep2 = $addUser('Nida Tariq', 'reception2@demo-clinic.test', 'receptionist', '03000000003');
    $accountantId = $addUser('Kamran Shah', 'accounts@demo-clinic.test', 'accountant', '03000000004');
    $staffIds = [$adminId, $recep1, $recep2, $accountantId];

    // ---- Specialties and doctors ----------------------------------------------------------------
    $specialties = [];
    foreach (['General Medicine' => 'Primary care and general illness', 'Pediatrics' => 'Care for infants and children', 'Gynecology & Obstetrics' => "Women's health and pregnancy care",
              'Cardiology' => 'Heart and blood pressure', 'Dermatology' => 'Skin, hair and nails', 'Orthopedics' => 'Bones, joints and muscles'] as $name => $desc) {
        $specialties[$name] = Database::insert('INSERT INTO specialties (name, description) VALUES (:n, :d)', ['n' => $name, 'd' => $desc]);
    }

    // weekday (ISO 1=Mon..7=Sun) => list of [start, end]
    $doctorsSpec = [
        ['Dr. Ayesha Rahman', 'ayesha@demo-clinic.test', 'General Medicine', 'MBBS, FCPS', 1500, 15, 'G-1', [1 => [['09:00', '13:00'], ['17:00', '20:00']], 2 => [['09:00', '13:00'], ['17:00', '20:00']], 3 => [['09:00', '13:00'], ['17:00', '20:00']], 4 => [['09:00', '13:00'], ['17:00', '20:00']], 5 => [['09:00', '13:00']], 6 => [['09:00', '13:00'], ['17:00', '20:00']], 7 => [['10:00', '14:00']]]],
        ['Dr. Bilal Hussain', 'bilal@demo-clinic.test', 'Pediatrics', 'MBBS, DCH', 2000, 20, 'G-2', [1 => [['10:00', '14:00'], ['17:00', '20:00']], 2 => [['10:00', '14:00']], 3 => [['10:00', '14:00'], ['17:00', '20:00']], 4 => [['10:00', '14:00']], 5 => [['10:00', '14:00'], ['17:00', '20:00']], 6 => [['10:00', '13:00']], 7 => [['11:00', '14:00']]]],
        ['Dr. Sana Malik', 'sana@demo-clinic.test', 'Gynecology & Obstetrics', 'MBBS, FCPS (Gynae)', 3000, 30, 'F-1', [1 => [['11:00', '15:00']], 2 => [['11:00', '15:00']], 3 => [['11:00', '15:00']], 4 => [['11:00', '15:00']], 5 => [['11:00', '15:00']], 6 => [['11:00', '15:00']], 7 => [['11:00', '13:00']]]],
        ['Dr. Imran Qureshi', 'imran@demo-clinic.test', 'Cardiology', 'MBBS, MRCP', 4000, 30, 'F-2', [1 => [['10:00', '13:00']], 2 => [['15:00', '20:00']], 3 => [['10:00', '13:00']], 4 => [['15:00', '20:00']], 5 => [['10:00', '13:00']], 6 => [['15:00', '20:00']], 7 => [['15:00', '19:00']]]],
        ['Dr. Hira Siddiqui', 'hira@demo-clinic.test', 'Dermatology', 'MBBS, FCPS (Derm)', 2500, 15, 'S-1', [1 => [['14:00', '19:00']], 2 => [['14:00', '19:00']], 3 => [['14:00', '19:00']], 4 => [['14:00', '19:00']], 5 => [['14:00', '19:00']], 6 => [['14:00', '19:00']], 7 => [['12:00', '16:00']]]],
        ['Dr. Farhan Javed', 'farhan@demo-clinic.test', 'Orthopedics', 'MBBS, MS Ortho', 3500, 20, 'S-2', [1 => [['16:00', '20:00']], 2 => [['10:00', '13:00']], 3 => [['16:00', '20:00']], 4 => [['10:00', '13:00']], 5 => [['16:00', '20:00']], 6 => [['10:00', '14:00']], 7 => [['10:00', '14:00']]]],
    ];
    $doctors = [];
    foreach ($doctorsSpec as $i => [$name, $email, $spec, $qual, $fee, $slot, $room, $windows]) {
        $userId = $addUser($name, $email, 'doctor', '0300000010' . $i);
        $id = Database::insert(
            'INSERT INTO doctors (user_id, specialty_id, license_no, qualification, phone, room, consultation_fee, slot_minutes) VALUES (:u, :s, :l, :q, :p, :r, :f, :sl)',
            ['u' => $userId, 's' => $specialties[$spec], 'l' => 'DEMO-' . (1000 + $i), 'q' => $qual, 'p' => '0300000010' . $i, 'r' => $room, 'f' => Money::toDecimal($fee * 100), 'sl' => $slot]
        );
        foreach ($windows as $weekday => $list) {
            foreach ($list as [$start, $end]) {
                Database::run('INSERT INTO doctor_schedules (doctor_id, weekday, start_time, end_time) VALUES (:d, :w, :s, :e)', ['d' => $id, 'w' => $weekday, 's' => $start, 'e' => $end]);
            }
        }
        $doctors[$id] = ['user_id' => $userId, 'fee' => $fee * 100, 'slot' => $slot, 'windows' => $windows, 'name' => $name];
    }

    // ---- Services -------------------------------------------------------------------------------
    $services = [];
    foreach ([['Complete blood count (CBC)', 800], ['ECG', 1200], ['Ultrasound abdomen', 3000], ['Dressing', 500], ['X-ray (single view)', 1500], ['Injection (administration)', 300], ['Nebulization', 600], ['Vaccination (per dose)', 1500]] as [$name, $price]) {
        $id = Database::insert('INSERT INTO services (name, price) VALUES (:n, :p)', ['n' => $name, 'p' => Money::toDecimal($price * 100)]);
        $services[$id] = ['name' => $name, 'price' => $price * 100];
    }

    // ---- Patients -------------------------------------------------------------------------------
    $firstM = ['Ahmed', 'Ali', 'Hassan', 'Usama', 'Zeeshan', 'Hamza', 'Taimoor', 'Rehan', 'Danish', 'Faisal', 'Saad', 'Adeel', 'Bilawal', 'Noman', 'Shahzaib', 'Junaid', 'Waqas', 'Arslan'];
    $firstF = ['Fatima', 'Ayesha', 'Maryam', 'Sadia', 'Hina', 'Rabia', 'Zara', 'Mehwish', 'Iqra', 'Komal', 'Sidra', 'Anum', 'Laiba', 'Noor', 'Areeba', 'Mahnoor', 'Saima', 'Bushra'];
    $last = ['Khan', 'Ahmed', 'Raza', 'Sheikh', 'Butt', 'Mirza', 'Chaudhry', 'Ansari', 'Baig', 'Nawaz', 'Lodhi', 'Memon', 'Abbasi', 'Gill', 'Rao'];
    $cities = ['Sample City', 'Garden Town', 'Model Colony', 'Lake View'];
    $patients = [];
    for ($i = 1; $i <= 48; $i++) {
        $female = $i % 2 === 0;
        $first = $female ? $firstF[array_rand($firstF)] : $firstM[array_rand($firstM)];
        $age = [1, 2, 4, 7, 11, 16, 24, 28, 31, 35, 38, 42, 47, 53, 58, 63, 70][array_rand([1, 2, 4, 7, 11, 16, 24, 28, 31, 35, 38, 42, 47, 53, 58, 63, 70])];
        $dob = $today->modify('-' . $age . ' years')->modify('-' . mt_rand(0, 360) . ' days')->format('Y-m-d');
        $registered = $today->modify('-' . mt_rand(1, 85) . ' days')->format('Y-m-d');
        $counters['patient']++;
        $id = Database::insert(
            'INSERT INTO patients (patient_code, first_name, last_name, gender, date_of_birth, phone, email, address, city, blood_group, emergency_contact_name, emergency_contact_phone, registered_on, created_by)
             VALUES (:code, :f, :l, :g, :dob, :ph, :em, :ad, :city, :bg, :ecn, :ecp, :reg, :by)',
            [
                'code' => sprintf('PT-%06d', $counters['patient']), 'f' => $first, 'l' => $last[array_rand($last)], 'g' => $female ? 'Female' : 'Male', 'dob' => $dob,
                'ph' => sprintf('0300%07d', 5550000 + $i), 'em' => mt_rand(0, 2) === 0 ? strtolower($first) . $i . '@mail.test' : null, 'ad' => mt_rand(10, 99) . ' Demo Street',
                'city' => $cities[array_rand($cities)], 'bg' => ['A+', 'B+', 'O+', 'AB+', 'O-', 'A-'][array_rand(['A+', 'B+', 'O+', 'AB+', 'O-', 'A-'])],
                'ecn' => 'Family contact ' . $i, 'ecp' => sprintf('0301%07d', 5550000 + $i), 'reg' => $registered, 'by' => [$recep1, $recep2][$i % 2],
            ]
        );
        $patients[] = $id;
    }

    // ---- Clinical content pools -----------------------------------------------------------------
    $cases = [
        ['Fever and body aches', 'Viral fever', [['Paracetamol 500 mg', '1 tablet', 'Three times a day', '3 days', 'After meals'], ['Oral rehydration salts', '1 sachet', 'Twice a day', '3 days', 'Dissolve in 1 litre of water']]],
        ['Sneezing and runny nose', 'Allergic rhinitis', [['Cetirizine 10 mg', '1 tablet', 'Once at night', '7 days', null]]],
        ['Stomach pain after meals', 'Gastritis', [['Omeprazole 20 mg', '1 capsule', 'Once daily', '14 days', 'Before breakfast'], ['Antacid syrup', '10 ml', 'After meals', '7 days', null]]],
        ['Sore throat and cough', 'Acute tonsillitis', [['Amoxicillin 500 mg', '1 capsule', 'Three times a day', '5 days', 'Complete the course'], ['Throat lozenges', '1 lozenge', 'As needed', '5 days', null]]],
        ['Itchy red patches on skin', 'Contact dermatitis', [['Hydrocortisone cream 1%', 'Thin layer', 'Twice a day', '7 days', 'Avoid broken skin']]],
        ['Lower back pain', 'Mechanical back pain', [['Ibuprofen 400 mg', '1 tablet', 'Twice a day', '5 days', 'With food'], ['Muscle relaxant', '1 tablet', 'At night', '5 days', null]]],
        ['Routine blood pressure check', 'Hypertension - stable', [['Amlodipine 5 mg', '1 tablet', 'Once daily', '30 days', 'Morning']]],
        ['Routine antenatal visit', 'Normal pregnancy follow-up', [['Folic acid 5 mg', '1 tablet', 'Once daily', '30 days', null], ['Iron supplement', '1 tablet', 'Once daily', '30 days', null]]],
    ];

    // ---- Appointments, visits, invoices, payments -----------------------------------------------
    $summary = ['appointments' => 0, 'visits' => 0, 'invoices' => 0, 'payments' => 0, 'follow_ups' => 0];
    $usedSlots = []; // "doctor|date|start" => true
    $patientDayDoctor = [];

    $slotsFor = static function (array $doctor, DateTimeImmutable $date): array {
        $out = [];
        foreach ($doctor['windows'][(int) $date->format('N')] ?? [] as [$start, $end]) {
            $cursor = ((int) substr($start, 0, 2)) * 60 + (int) substr($start, 3, 2);
            $limit = ((int) substr($end, 0, 2)) * 60 + (int) substr($end, 3, 2);
            for (; $cursor + $doctor['slot'] <= $limit; $cursor += $doctor['slot']) {
                $out[] = [sprintf('%02d:%02d', intdiv($cursor, 60), $cursor % 60), sprintf('%02d:%02d', intdiv($cursor + $doctor['slot'], 60), ($cursor + $doctor['slot']) % 60)];
            }
        }

        return $out;
    };

    $createInvoice = static function (int $patientId, ?int $visitId, int $doctorId, string $date, int $daysAgo) use (&$counters, &$summary, $doctors, $services, $staffIds, $recep1, $accountantId): void {
        $lines = [['Consultation - ' . $doctors[$doctorId]['name'], 'Consultation', null, 1, $doctors[$doctorId]['fee']]];
        if (mt_rand(1, 100) <= 30) {
            $sid = array_rand($services);
            $qty = mt_rand(1, 2);
            $lines[] = [$services[$sid]['name'], 'Service', $sid, $qty, $services[$sid]['price']];
        }
        $subtotal = array_sum(array_map(static fn ($l) => $l[3] * $l[4], $lines));
        $discount = mt_rand(1, 100) <= 12 ? intdiv($subtotal, 10 * 100) * 100 : 0;
        $total = $subtotal - $discount;
        $counters['invoice']++;
        $invoiceId = Database::insert(
            'INSERT INTO invoices (invoice_no, patient_id, visit_id, issue_date, subtotal, discount_amount, tax_rate, tax_amount, total, balance, status, created_by)
             VALUES (:no, :p, :v, :d, :s, :disc, 0, 0, :t, :t2, \'Unpaid\', :by)',
            ['no' => sprintf('INV-%s-%06d', substr($date, 0, 4), $counters['invoice']), 'p' => $patientId, 'v' => $visitId, 'd' => $date, 's' => Money::toDecimal($subtotal),
             'disc' => Money::toDecimal($discount), 't' => Money::toDecimal($total), 't2' => Money::toDecimal($total), 'by' => $recep1]
        );
        foreach ($lines as [$desc, $type, $sid, $qty, $price]) {
            Database::run('INSERT INTO invoice_items (invoice_id, item_type, service_id, description, quantity, unit_price, line_total) VALUES (:i, :t, :s, :d, :q, :u, :l)',
                ['i' => $invoiceId, 't' => $type, 's' => $sid, 'd' => $desc, 'q' => $qty, 'u' => Money::toDecimal($price), 'l' => Money::toDecimal($price * $qty)]);
        }
        $summary['invoices']++;

        // Older invoices are mostly settled; recent ones are more often still open.
        $roll = mt_rand(1, 100);
        $paidShare = $daysAgo > 14 ? ($roll <= 82 ? 100 : ($roll <= 92 ? 50 : 0)) : ($roll <= 62 ? 100 : ($roll <= 80 ? 50 : 0));
        if ($paidShare === 0) {
            return;
        }
        $methods = ['Cash', 'Cash', 'Cash', 'Card', 'Bank Transfer', 'Mobile Wallet'];
        $chunks = $paidShare === 100 ? [$total] : [intdiv($total, 200) * 100];
        $paid = 0;
        foreach ($chunks as $amount) {
            if ($amount <= 0) {
                continue;
            }
            $counters['receipt']++;
            Database::run(
                'INSERT INTO payments (invoice_id, receipt_no, entry_type, amount, method, paid_at, recorded_by) VALUES (:i, :r, \'Payment\', :a, :m, :at, :by)',
                ['i' => $invoiceId, 'r' => sprintf('RCT-%06d', $counters['receipt']), 'a' => Money::toDecimal($amount), 'm' => $methods[array_rand($methods)], 'at' => $date . ' ' . sprintf('%02d:%02d:00', mt_rand(9, 20), mt_rand(0, 59)), 'by' => mt_rand(0, 1) ? $recep1 : $accountantId]
            );
            $paid += $amount;
            $summary['payments']++;
        }
        $balance = $total - $paid;
        Database::run('UPDATE invoices SET amount_paid = :p, balance = :b, status = :s WHERE id = :id', ['p' => Money::toDecimal($paid), 'b' => Money::toDecimal($balance), 's' => $balance === 0 ? 'Paid' : 'Partial', 'id' => $invoiceId]);
    };

    $createVisit = static function (int $appointmentId, int $patientId, int $doctorId, string $date, string $start, bool $finalized) use (&$counters, &$summary, $cases, $doctors, $today): int {
        $case = $cases[array_rand($cases)];
        $at = $date . ' ' . $start . ':00';
        $vid = Database::insert(
            'INSERT INTO visits (patient_id, doctor_id, appointment_id, visited_at, chief_complaint, symptoms, bp_systolic, bp_diastolic, pulse_bpm, temperature_c, spo2_percent, weight_kg, height_cm,
                                 diagnosis, treatment_plan, status, finalized_at, created_by)
             VALUES (:p, :d, :a, :at, :cc, :sy, :bs, :bd, :pu, :t, :sp, :w, :h, :dx, :tp, :st, :fin, :by)',
            ['p' => $patientId, 'd' => $doctorId, 'a' => $appointmentId, 'at' => $at, 'cc' => $case[0], 'sy' => 'Reported for ' . mt_rand(1, 6) . ' day(s). (fictional demo note)',
             'bs' => mt_rand(105, 138), 'bd' => mt_rand(68, 88), 'pu' => mt_rand(64, 98), 't' => number_format(mt_rand(366, 391) / 10, 1, '.', ''), 'sp' => mt_rand(95, 100),
             'w' => number_format(mt_rand(450, 900) / 10, 1, '.', ''), 'h' => number_format(mt_rand(1500, 1850) / 10, 1, '.', ''),
             'dx' => $finalized ? $case[1] : null, 'tp' => $finalized ? 'Rest, fluids and the medicines listed. Return if symptoms worsen.' : null,
             'st' => $finalized ? 'Finalized' : 'Draft', 'fin' => $finalized ? $at : null, 'by' => $doctors[$doctorId]['user_id']]
        );
        $summary['visits']++;
        if ($finalized) {
            $counters['prescription']++;
            $rx = Database::insert('INSERT INTO prescriptions (visit_id, patient_id, doctor_id, prescription_no, advice) VALUES (:v, :p, :d, :n, :a)',
                ['v' => $vid, 'p' => $patientId, 'd' => $doctorId, 'n' => sprintf('RX-%06d', $counters['prescription']), 'a' => 'Drink plenty of fluids and take adequate rest.']);
            foreach ($case[2] as $i => [$med, $dose, $freq, $dur, $ins]) {
                Database::run('INSERT INTO prescription_items (prescription_id, sort_order, medicine_name, dosage, frequency, duration, instructions) VALUES (:r, :o, :m, :d, :f, :du, :i)',
                    ['r' => $rx, 'o' => $i + 1, 'm' => $med, 'd' => $dose, 'f' => $freq, 'du' => $dur, 'i' => $ins]);
            }
        }

        return $vid;
    };

    $addAppointment = static function (int $doctorId, string $date, array $slot, string $status, string $type, ?int $queue) use (&$summary, &$usedSlots, &$patientDayDoctor, $patients, $recep1, $recep2): ?int {
        $key = $doctorId . '|' . $date . '|' . $slot[0];
        if (isset($usedSlots[$key])) {
            return null;
        }
        for ($try = 0; $try < 10; $try++) {
            $patientId = $patients[array_rand($patients)];
            if (!isset($patientDayDoctor[$patientId . '|' . $date . '|' . $doctorId])) {
                break;
            }
            $patientId = null;
        }
        if ($patientId === null) {
            return null;
        }
        $usedSlots[$key] = true;
        $patientDayDoctor[$patientId . '|' . $date . '|' . $doctorId] = true;
        $checkedIn = in_array($status, ['Waiting', 'In Progress', 'Completed'], true) ? $date . ' ' . $slot[0] . ':00' : null;
        $id = Database::insert(
            'INSERT INTO appointments (patient_id, doctor_id, appointment_date, start_time, end_time, booking_type, status, reason, cancellation_reason, queue_number, checked_in_at, created_by)
             VALUES (:p, :d, :date, :s, :e, :bt, :st, :r, :cr, :q, :ci, :by)',
            ['p' => $patientId, 'd' => $doctorId, 'date' => $date, 's' => $slot[0], 'e' => $slot[1], 'bt' => $type, 'st' => $status,
             'r' => ['Fever', 'Follow-up visit', 'Check-up', 'Pain', 'Skin problem', 'Routine review'][array_rand(['Fever', 'Follow-up visit', 'Check-up', 'Pain', 'Skin problem', 'Routine review'])],
             'cr' => $status === 'Cancelled' ? 'Patient asked to cancel (demo)' : null, 'q' => $queue, 'ci' => $checkedIn, 'by' => mt_rand(0, 1) ? $recep1 : $recep2]
        );
        $summary['appointments']++;

        return $id;
    };

    // Past 45 days: mostly completed, with some cancellations and no-shows.
    for ($daysAgo = 45; $daysAgo >= 1; $daysAgo--) {
        $date = $today->modify("-$daysAgo days");
        foreach ($doctors as $doctorId => $doctor) {
            $slots = $slotsFor($doctor, $date);
            shuffle($slots);
            foreach (array_slice($slots, 0, mt_rand(2, 6)) as $slot) {
                $roll = mt_rand(1, 100);
                $status = $roll <= 82 ? 'Completed' : ($roll <= 92 ? 'Cancelled' : 'No Show');
                $type = mt_rand(1, 100) <= 15 ? 'Walk-in' : 'Scheduled';
                $appointmentId = $addAppointment($doctorId, $date->format('Y-m-d'), $slot, $status, $type, null);
                if ($appointmentId === null || $status !== 'Completed') {
                    continue;
                }
                $patientId = (int) Database::value('SELECT patient_id FROM appointments WHERE id = :i', ['i' => $appointmentId]);
                $visitId = $createVisit($appointmentId, $patientId, $doctorId, $date->format('Y-m-d'), $slot[0], true);
                $createInvoice($patientId, $visitId, $doctorId, $date->format('Y-m-d'), $daysAgo);
                if (mt_rand(1, 100) <= 22) {
                    $due = $date->modify('+' . [7, 10, 14][array_rand([7, 10, 14])] . ' days');
                    $done = $due < $today && mt_rand(1, 100) <= 55;
                    Database::run('INSERT INTO follow_ups (patient_id, visit_id, doctor_id, follow_up_date, notes, status, completed_at, created_by) VALUES (:p, :v, :d, :date, :n, :st, :ca, :by)',
                        ['p' => $patientId, 'v' => $visitId, 'd' => $doctorId, 'date' => $due->format('Y-m-d'), 'n' => 'Review response to treatment (demo)', 'st' => $done ? 'Completed' : 'Pending', 'ca' => $done ? $due->format('Y-m-d') . ' 12:00:00' : null, 'by' => $doctor['user_id']]);
                    $summary['follow_ups']++;
                }
            }
        }
    }

    // Today: a realistic mix so the queue and dashboards are not empty.
    $todayStr = $today->format('Y-m-d');
    foreach ($doctors as $doctorId => $doctor) {
        $slots = $slotsFor($doctor, $today);
        $slots = array_slice($slots, 0, 8);
        $queueNo = 0;
        foreach ($slots as $i => $slot) {
            $status = match (true) {
                $i < 2 => 'Completed', $i === 2 => 'In Progress', $i === 3, $i === 4 => 'Waiting', $i === 5 => 'Confirmed', $i === 6 => 'Cancelled', default => 'Scheduled',
            };
            $queue = in_array($status, ['Completed', 'In Progress', 'Waiting'], true) ? ++$queueNo : null;
            $appointmentId = $addAppointment($doctorId, $todayStr, $slot, $status, 'Scheduled', $queue);
            if ($appointmentId === null) {
                continue;
            }
            $patientId = (int) Database::value('SELECT patient_id FROM appointments WHERE id = :i', ['i' => $appointmentId]);
            if ($status === 'Completed') {
                $visitId = $createVisit($appointmentId, $patientId, $doctorId, $todayStr, $slot[0], true);
                $createInvoice($patientId, $visitId, $doctorId, $todayStr, 0);
            } elseif ($status === 'In Progress') {
                $createVisit($appointmentId, $patientId, $doctorId, $todayStr, $slot[0], false);
            }
        }
    }

    // One walk-in waiting today (first doctor that works today).
    foreach ($doctors as $doctorId => $doctor) {
        $slots = $slotsFor($doctor, $today);
        if ($slots !== []) {
            $q = (int) Database::value('SELECT COALESCE(MAX(queue_number), 0) + 1 FROM appointments WHERE doctor_id = :d AND appointment_date = :date', ['d' => $doctorId, 'date' => $todayStr]);
            $now = date('H:i');
            $addAppointment($doctorId, $todayStr, [$now, date('H:i', strtotime("+{$doctor['slot']} minutes"))], 'Waiting', 'Walk-in', $q);
            break;
        }
    }

    // Next 7 days: upcoming bookings.
    for ($ahead = 1; $ahead <= 7; $ahead++) {
        $date = $today->modify("+$ahead days");
        foreach ($doctors as $doctorId => $doctor) {
            $slots = $slotsFor($doctor, $date);
            shuffle($slots);
            foreach (array_slice($slots, 0, mt_rand(1, 3)) as $slot) {
                $addAppointment($doctorId, $date->format('Y-m-d'), $slot, mt_rand(0, 1) ? 'Confirmed' : 'Scheduled', 'Scheduled', null);
            }
        }
    }

    // ---- Counters and a sample audit trail ------------------------------------------------------
    foreach ($counters as $name => $value) {
        Database::run('UPDATE number_sequences SET last_value = :v WHERE name = :n', ['v' => $value, 'n' => $name]);
    }
    foreach ([['user.created', 'user', 'Created demo staff accounts'], ['settings.updated', 'settings', 'Changed clinic settings'], ['patient.created', 'patient', 'Registered demo patients']] as [$action, $entity, $text]) {
        Database::run('INSERT INTO audit_logs (user_id, action, entity_type, summary, ip_address) VALUES (:u, :a, :e, :s, \'127.0.0.1\')', ['u' => $adminId, 'a' => $action, 'e' => $entity, 's' => $text . ' (seed)']);
    }

    $pdo->commit();

    return ['staff' => count($staffIds) + count($doctors), 'doctors' => count($doctors), 'patients' => count($patients)] + $summary;
}
