<?php
declare(strict_types=1);

use App\Core\BusinessRuleException;
use App\Core\Database;
use App\Core\Money;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Setting;
use App\Services\BillingService;
use Tests\Support\Fixtures;
use Tests\Support\Tester;

return static function (Tester $t): void {
    [$before, $after] = $GLOBALS['__test_hooks'];
    $t->group('Integration: billing, payments, refunds', $before, $after);

    $make = function (string $fee = '1500.00', array $services = [], string $discount = '', ?string $tax = null) {
        $user = Fixtures::actAs(Fixtures::user('accountant'));
        $doctor = Fixtures::doctor(15, $fee);
        $patient = Fixtures::patient();
        if ($tax !== null) { Setting::set('tax_rate_percent', $tax); }
        $id = BillingService::createInvoice((int) $patient['id'], ['consultation_doctor_id' => (int) $doctor['id'], 'services' => $services, 'discount' => $discount, 'notes' => null, 'visit_id' => null], (int) $user['id']);

        return [$id, (int) $user['id'], $doctor, $patient];
    };
    $service = fn (string $name, string $price, bool $active = true) => Service::find(Service::create($name . Fixtures::unique(), null, $price)) + ['active' => $active];

    $t->test('totals: consultation + services - discount + tax, exact to the paisa', function () use ($t, $make) {
        $s = Service::create('Blood test ' . Fixtures::unique(), null, '800.00');
        [$id] = $make('1500.00', [['service_id' => $s, 'quantity' => 2]], '100', '5');
        $i = Invoice::find($id);
        $t->same('3100.00', $i['subtotal']);   // 1500 + 2 x 800
        $t->same('100.00', $i['discount_amount']);
        $t->same('150.00', $i['tax_amount']);  // 5% of 3000
        $t->same('3150.00', $i['total']);
        $t->same('3150.00', $i['balance']);
        $t->same('Unpaid', $i['status']);
        $t->true((bool) preg_match('/^INV-\d{4}-\d{6}$/', $i['invoice_no']));
        $items = Invoice::items($id);
        $t->same(2, count($items));
        $t->same('Consultation', $items[0]['item_type']);
        $t->same('1600.00', $items[1]['line_total']);
    });
    $t->test('three items at 0.10 total exactly 0.30 (no floating point drift)', function () use ($t, $make) {
        $s = Service::create('Tiny ' . Fixtures::unique(), null, '0.10');
        [$id] = $make('0.00', [['service_id' => $s, 'quantity' => 3]]);
        $t->same('0.30', Invoice::find($id)['total']);
    });
    $t->test('tax rounds half up per invoice', function () use ($t, $make) {
        $s = Service::create('Odd ' . Fixtures::unique(), null, '0.30');
        [$id] = $make('0.00', [['service_id' => $s, 'quantity' => 1]], '', '5');   // 5% of 0.30 = 0.015 -> 0.02
        $i = Invoice::find($id);
        $t->same('0.02', $i['tax_amount']);
        $t->same('0.32', $i['total']);
    });
    $t->test('prices come from the database; invalid invoices are refused', function () use ($t, $make) {
        $user = Fixtures::user('accountant');
        $patient = Fixtures::patient();
        $base = ['consultation_doctor_id' => null, 'services' => [], 'discount' => '', 'notes' => null, 'visit_id' => null];
        $t->throws(BusinessRuleException::class, fn () => BillingService::createInvoice((int) $patient['id'], $base, (int) $user['id']), 'at least one');
        $s = Service::create('Cheap ' . Fixtures::unique(), null, '100.00');
        $t->throws(BusinessRuleException::class, fn () => BillingService::createInvoice((int) $patient['id'], ['services' => [['service_id' => $s, 'quantity' => 1]], 'discount' => '101'] + $base, (int) $user['id']), 'larger than the subtotal');
        $t->throws(BusinessRuleException::class, fn () => BillingService::createInvoice((int) $patient['id'], ['services' => [['service_id' => $s, 'quantity' => 0]]] + $base, (int) $user['id']), 'Quantity');
        $t->throws(BusinessRuleException::class, fn () => BillingService::createInvoice((int) $patient['id'], ['services' => [['service_id' => $s, 'quantity' => 100]]] + $base, (int) $user['id']), 'Quantity');
        $t->throws(BusinessRuleException::class, fn () => BillingService::createInvoice((int) $patient['id'], ['services' => [['service_id' => $s, 'quantity' => 1]], 'discount' => 'abc'] + $base, (int) $user['id']), 'discount');
        $t->throws(BusinessRuleException::class, fn () => BillingService::createInvoice((int) $patient['id'], ['services' => [['service_id' => 999999, 'quantity' => 1]]] + $base, (int) $user['id']), 'not found');
        Service::setActive($s, false);
        $t->throws(BusinessRuleException::class, fn () => BillingService::createInvoice((int) $patient['id'], ['services' => [['service_id' => $s, 'quantity' => 1]]] + $base, (int) $user['id']), 'no longer active');
        $t->throws(BusinessRuleException::class, fn () => BillingService::createInvoice(999999, $base, (int) $user['id']), 'Patient not found');
    });
    $t->test('a free invoice (zero total) is immediately Paid', function () use ($t, $make) {
        [$id] = $make('0.00', []);
        $t->same('Paid', Invoice::find($id)['status']);
    });
    $t->test('partial then full payment updates balance and status from the ledger', function () use ($t, $make) {
        [$id, $uid] = $make('3000.00');
        BillingService::recordPayment($id, '1000', 'Cash', null, $uid);
        $i = Invoice::find($id);
        $t->same(['Partial', '1000.00', '2000.00'], [$i['status'], $i['amount_paid'], $i['balance']]);
        BillingService::recordPayment($id, '2000.00', 'Card', 'slip 42', $uid);
        $i = Invoice::find($id);
        $t->same(['Paid', '3000.00', '0.00'], [$i['status'], $i['amount_paid'], $i['balance']]);
        $t->same(2, count(Payment::forInvoice($id)));
        $t->same('3000.00', Payment::totalsForInvoice($id)['Payment']);
    });
    $t->test('overpayment, zero, junk amounts and unknown methods are refused; paid invoices accept nothing more', function () use ($t, $make) {
        [$id, $uid] = $make('500.00');
        $t->throws(BusinessRuleException::class, fn () => BillingService::recordPayment($id, '500.01', 'Cash', null, $uid), 'Overpayments');
        $t->throws(BusinessRuleException::class, fn () => BillingService::recordPayment($id, '0', 'Cash', null, $uid), 'greater than zero');
        $t->throws(BusinessRuleException::class, fn () => BillingService::recordPayment($id, '-5', 'Cash', null, $uid), 'greater than zero');
        $t->throws(BusinessRuleException::class, fn () => BillingService::recordPayment($id, '12.345', 'Cash', null, $uid), 'greater than zero');
        $t->throws(BusinessRuleException::class, fn () => BillingService::recordPayment($id, 'abc', 'Cash', null, $uid), 'greater than zero');
        $t->throws(BusinessRuleException::class, fn () => BillingService::recordPayment($id, '100', 'Bitcoin', null, $uid), 'method');
        $t->same(0, count(Payment::forInvoice($id)), 'nothing was recorded');
        BillingService::recordPayment($id, '500', 'Cash', null, $uid);
        $t->throws(BusinessRuleException::class, fn () => BillingService::recordPayment($id, '1', 'Cash', null, $uid), 'Overpayments');
    });
    $t->test('refunds: bounded by money received, reason required, balance restored', function () use ($t, $make) {
        [$id, $uid] = $make('1000.00');
        BillingService::recordPayment($id, '1000', 'Cash', null, $uid);
        $t->throws(BusinessRuleException::class, fn () => BillingService::recordRefund($id, '1000.01', 'Cash', 'x', $uid), 'larger than the amount');
        $t->throws(BusinessRuleException::class, fn () => BillingService::recordRefund($id, '100', 'Cash', ' ', $uid), 'reason');
        BillingService::recordRefund($id, '400', 'Cash', 'Service not provided', $uid);
        $i = Invoice::find($id);
        $t->same(['Partial', '600.00', '400.00'], [$i['status'], $i['amount_paid'], $i['balance']]);
        BillingService::recordRefund($id, '600', 'Cash', 'Rest', $uid);
        $i = Invoice::find($id);
        $t->same(['Unpaid', '0.00', '1000.00'], [$i['status'], $i['amount_paid'], $i['balance']]);
    });
    $t->test('adjustments write off balance without cash; they need a reason and cannot exceed the balance', function () use ($t, $make) {
        [$id, $uid] = $make('1000.00');
        BillingService::recordPayment($id, '700', 'Cash', null, $uid);
        $t->throws(BusinessRuleException::class, fn () => BillingService::recordAdjustment($id, '300.01', 'goodwill', $uid), 'larger than the outstanding');
        $t->throws(BusinessRuleException::class, fn () => BillingService::recordAdjustment($id, '100', '', $uid), 'reason');
        BillingService::recordAdjustment($id, '300', 'Goodwill discount', $uid);
        $i = Invoice::find($id);
        $t->same(['Paid', '700.00', '300.00', '0.00'], [$i['status'], $i['amount_paid'], $i['adjusted_amount'], $i['balance']]);
    });
    $t->test('voiding: refused while money is held, allowed after refund, blocks later entries', function () use ($t, $make) {
        [$id, $uid] = $make('1000.00');
        BillingService::recordPayment($id, '250', 'Cash', null, $uid);
        $t->throws(BusinessRuleException::class, fn () => BillingService::voidInvoice($id, 'Mistake', $uid), 'Refund it first');
        BillingService::recordRefund($id, '250', 'Cash', 'Cancelled', $uid);
        $t->throws(BusinessRuleException::class, fn () => BillingService::voidInvoice($id, '', $uid), 'reason');
        BillingService::voidInvoice($id, 'Entered for wrong patient', $uid);
        $i = Invoice::find($id);
        $t->same(['Void', '0.00'], [$i['status'], $i['balance']]);
        $t->throws(BusinessRuleException::class, fn () => BillingService::recordPayment($id, '10', 'Cash', null, $uid), 'void');
        $t->throws(BusinessRuleException::class, fn () => BillingService::voidInvoice($id, 'again', $uid), 'already void');
    });
    $t->test('a finalized visit can be invoiced only once (until the invoice is voided)', function () use ($t) {
        $user = Fixtures::actAs(Fixtures::user('accountant'));
        $d = Fixtures::doctor();
        $p = Fixtures::patient();
        $vid = Database::insert("INSERT INTO visits (patient_id, doctor_id, visited_at, status, created_by) VALUES (:p, :d, NOW(), 'Finalized', :u)", ['p' => $p['id'], 'd' => $d['id'], 'u' => $user['id']]);
        $draft = Database::insert("INSERT INTO visits (patient_id, doctor_id, visited_at, status, created_by) VALUES (:p, :d, NOW(), 'Draft', :u)", ['p' => $p['id'], 'd' => $d['id'], 'u' => $user['id']]);
        $input = fn (int $visit) => ['consultation_doctor_id' => (int) $d['id'], 'services' => [], 'discount' => '', 'notes' => null, 'visit_id' => $visit];
        $t->same(1, count(Invoice::uninvoicedVisits((int) $p['id'])));
        $first = BillingService::createInvoice((int) $p['id'], $input($vid), (int) $user['id']);
        $t->throws(BusinessRuleException::class, fn () => BillingService::createInvoice((int) $p['id'], $input($vid), (int) $user['id']), 'already has invoice');
        $t->throws(BusinessRuleException::class, fn () => BillingService::createInvoice((int) $p['id'], $input($draft), (int) $user['id']), 'finalized');
        $t->throws(BusinessRuleException::class, fn () => BillingService::createInvoice((int) Fixtures::patient()['id'], $input($vid), (int) $user['id']), 'does not belong');
        BillingService::voidInvoice($first, 'Wrong fee', (int) $user['id']);
        BillingService::createInvoice((int) $p['id'], $input($vid), (int) $user['id']);
        $t->true(true);
    });
    $t->test('invoice and receipt numbers are unique and sequential', function () use ($t, $make) {
        [$a, $uid] = $make('100.00');
        [$b] = $make('100.00');
        $na = Invoice::find($a)['invoice_no']; $nb = Invoice::find($b)['invoice_no'];
        $t->true($na !== $nb);
        $t->same(1, (int) substr($nb, -6) - (int) substr($na, -6));
        $p1 = BillingService::recordPayment($a, '10', 'Cash', null, $uid);
        $p2 = BillingService::recordPayment($a, '10', 'Cash', null, $uid);
        $r1 = Payment::find($p1)['receipt_no']; $r2 = Payment::find($p2)['receipt_no'];
        $t->same('RCT-', substr($r1, 0, 4));
        $t->same(1, (int) substr($r2, -6) - (int) substr($r1, -6));
    });
    $t->test('a failed payment leaves no trace (transaction rolls back)', function () use ($t, $make) {
        [$id, $uid] = $make('100.00');
        $seqBefore = (int) Database::value("SELECT last_value FROM number_sequences WHERE name = 'receipt'");
        try { BillingService::recordPayment($id, '999', 'Cash', null, $uid); } catch (BusinessRuleException) {}
        $t->same($seqBefore, (int) Database::value("SELECT last_value FROM number_sequences WHERE name = 'receipt'"));
        $t->same('100.00', Invoice::find($id)['balance']);
    });
};
