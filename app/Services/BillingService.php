<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Billing;
use App\Core\BusinessRuleException;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Money;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Sequence;
use App\Models\Service;

/**
 * Invoices and the payment ledger.
 *
 * Rules:
 *  - All arithmetic uses integer minor units (Money), never floats.
 *  - Prices always come from the database (doctor fee / service price), never from the browser.
 *  - Every money-changing operation runs in ONE transaction that locks the invoice row.
 *  - Payments are append-only. Mistakes are corrected with a Refund or Adjustment entry.
 *  - Overpayment is not allowed: a payment may not exceed the outstanding balance.
 */
final class BillingService
{
    public const MAX_LINES = 30;

    /**
     * @param array{consultation_doctor_id:?int,services:array<int,array{service_id:int,quantity:int}>,discount:string,notes:?string,visit_id:?int} $input
     * @throws BusinessRuleException
     */
    public static function createInvoice(int $patientId, array $input, int $userId): int
    {
        Patient::find($patientId) ?? throw new BusinessRuleException('Patient not found.');

        return Database::transaction(static function () use ($patientId, $input, $userId): int {
            $visitId = $input['visit_id'];
            if ($visitId !== null) {
                self::assertVisitBillable($visitId, $patientId);
            }

            $lines = self::buildLines($input);
            if ($lines === []) {
                throw new BusinessRuleException('Add at least one consultation fee or service to the invoice.');
            }
            if (count($lines) > self::MAX_LINES) {
                throw new BusinessRuleException('An invoice can have at most ' . self::MAX_LINES . ' lines.');
            }

            $subtotal = array_sum(array_column($lines, 'line_minor'));
            $discount = Money::parse($input['discount'] === '' ? '0' : $input['discount']);
            if ($discount === null) {
                throw new BusinessRuleException('The discount must be an amount such as 200 or 200.50.');
            }
            if ($discount > $subtotal) {
                throw new BusinessRuleException('The discount cannot be larger than the subtotal.');
            }
            $taxRate = setting('tax_rate_percent', '0.00');
            $tax = Money::percentOf($subtotal - $discount, $taxRate);
            $total = $subtotal - $discount + $tax;

            $number = sprintf('INV-%s-%06d', date('Y'), Sequence::next(Sequence::INVOICE));
            $invoiceId = Invoice::create([
                'patient_id'      => $patientId,
                'visit_id'        => $visitId,
                'subtotal'        => Money::toDecimal($subtotal),
                'discount_amount' => Money::toDecimal($discount),
                'tax_rate'        => $taxRate,
                'tax_amount'      => Money::toDecimal($tax),
                'total'           => Money::toDecimal($total),
                'notes'           => $input['notes'],
            ], $number, $userId);
            foreach ($lines as $line) {
                Invoice::addItem($invoiceId, $line['row']);
            }
            AuditService::log('invoice.created', 'invoice', $invoiceId, "Created invoice $number", ['total' => Money::toDecimal($total)], $userId);

            return $invoiceId;
        });
    }

    /** @throws BusinessRuleException */
    public static function recordPayment(int $invoiceId, string $amount, string $method, ?string $reference, int $userId): int
    {
        return self::postEntry($invoiceId, Billing::TYPE_PAYMENT, $amount, $method, $reference, null, $userId);
    }

    /** @throws BusinessRuleException */
    public static function recordRefund(int $invoiceId, string $amount, string $method, string $reason, int $userId): int
    {
        return self::postEntry($invoiceId, Billing::TYPE_REFUND, $amount, $method, null, $reason, $userId);
    }

    /** Write-off / goodwill discount after the invoice was issued. @throws BusinessRuleException */
    public static function recordAdjustment(int $invoiceId, string $amount, string $reason, int $userId): int
    {
        return self::postEntry($invoiceId, Billing::TYPE_ADJUSTMENT, $amount, null, null, $reason, $userId);
    }

    /** An invoice can be voided only when nothing is left paid on it (refund first). @throws BusinessRuleException */
    public static function voidInvoice(int $invoiceId, string $reason, int $userId): void
    {
        Database::transaction(static function () use ($invoiceId, $reason, $userId): void {
            $invoice = Invoice::findForUpdate($invoiceId) ?? throw new HttpException(404);
            if (trim($reason) === '') {
                throw new BusinessRuleException('A reason is required to void an invoice.');
            }
            if ($invoice['status'] === Billing::STATUS_VOID) {
                throw new BusinessRuleException('This invoice is already void.');
            }
            if (Money::fromDb($invoice['amount_paid']) !== 0) {
                throw new BusinessRuleException('Money has been received on this invoice. Refund it first, then void the invoice.');
            }
            Invoice::void($invoiceId, $userId, mb_substr(trim($reason), 0, 255));
            AuditService::log('invoice.voided', 'invoice', $invoiceId, 'Voided invoice ' . $invoice['invoice_no'], ['reason' => $reason], $userId);
        });
    }

    /**
     * Recomputes paid amount, adjustments, balance and status from the ledger (the single source of truth).
     * Must be called inside the transaction that inserted the ledger row.
     */
    public static function recalculate(int $invoiceId): void
    {
        $invoice = Invoice::findForUpdate($invoiceId) ?? throw new HttpException(404);
        if ($invoice['status'] === Billing::STATUS_VOID) {
            return;
        }
        $totals = Payment::totalsForInvoice($invoiceId);
        $paid = Money::fromDb($totals[Billing::TYPE_PAYMENT]) - Money::fromDb($totals[Billing::TYPE_REFUND]);
        $adjusted = Money::fromDb($totals[Billing::TYPE_ADJUSTMENT]);
        $balance = Money::fromDb($invoice['total']) - $paid - $adjusted;

        $status = match (true) {
            $balance <= 0           => Billing::STATUS_PAID,
            $paid > 0 || $adjusted > 0 => Billing::STATUS_PARTIAL,
            default                 => Billing::STATUS_UNPAID,
        };
        Invoice::updateAmounts($invoiceId, Money::toDecimal($paid), Money::toDecimal($adjusted), Money::toDecimal(max(0, $balance)), $status);
    }

    private static function postEntry(int $invoiceId, string $type, string $amountText, ?string $method, ?string $reference, ?string $reason, int $userId): int
    {
        return Database::transaction(static function () use ($invoiceId, $type, $amountText, $method, $reference, $reason, $userId): int {
            $invoice = Invoice::findForUpdate($invoiceId) ?? throw new HttpException(404);
            if ($invoice['status'] === Billing::STATUS_VOID) {
                throw new BusinessRuleException('This invoice is void and cannot receive entries.');
            }

            $amount = Money::parse($amountText);
            if ($amount === null || $amount <= 0) {
                throw new BusinessRuleException('Enter an amount greater than zero, for example 1500 or 1500.50.');
            }
            if ($type !== Billing::TYPE_ADJUSTMENT && !in_array($method, Billing::METHODS, true)) {
                throw new BusinessRuleException('Please choose a payment method.');
            }
            if ($type !== Billing::TYPE_PAYMENT && ($reason === null || trim($reason) === '')) {
                throw new BusinessRuleException('A reason is required for refunds and adjustments.');
            }

            $balance = Money::fromDb($invoice['balance']);
            $netPaid = Money::fromDb($invoice['amount_paid']);
            if ($type === Billing::TYPE_REFUND && $amount > $netPaid) {
                throw new BusinessRuleException('A refund cannot be larger than the amount currently paid (' . money($invoice['amount_paid']) . ').');
            }
            if ($type !== Billing::TYPE_REFUND && $amount > $balance) {
                $what = $type === Billing::TYPE_PAYMENT ? 'payment' : 'adjustment';
                throw new BusinessRuleException("The $what is larger than the outstanding balance (" . money($invoice['balance']) . '). Overpayments are not accepted.');
            }

            $prefix = ['Payment' => 'RCT', 'Refund' => 'RFD', 'Adjustment' => 'ADJ'][$type];
            $paymentId = Payment::create([
                'invoice_id' => $invoiceId,
                'entry_type' => $type,
                'amount'     => Money::toDecimal($amount),
                'method'     => $type === Billing::TYPE_ADJUSTMENT ? null : $method,
                'reference'  => $reference !== null && $reference !== '' ? mb_substr($reference, 0, 100) : null,
                'reason'     => $reason !== null ? mb_substr(trim($reason), 0, 255) : null,
            ], sprintf('%s-%06d', $prefix, Sequence::next(Sequence::RECEIPT)), $userId);

            self::recalculate($invoiceId);
            AuditService::log('payment.' . strtolower($type), 'invoice', $invoiceId, "$type of " . Money::toDecimal($amount) . ' on ' . $invoice['invoice_no'], ['payment_id' => $paymentId, 'method' => $method], $userId);

            return $paymentId;
        });
    }

    /**
     * Turns the form input into priced invoice lines using database prices.
     *
     * @return array<int,array{line_minor:int,row:array<string,mixed>}>
     * @throws BusinessRuleException
     */
    private static function buildLines(array $input): array
    {
        $lines = [];

        if ($input['consultation_doctor_id'] !== null) {
            $doctor = Doctor::find($input['consultation_doctor_id']) ?? throw new BusinessRuleException('The selected doctor was not found.');
            $fee = Money::fromDb($doctor['consultation_fee']);
            $lines[] = [
                'line_minor' => $fee,
                'row' => [
                    'item_type' => Billing::ITEM_CONSULTATION, 'service_id' => null, 'quantity' => 1,
                    'description' => 'Consultation - ' . $doctor['full_name'] . ' (' . $doctor['specialty_name'] . ')',
                    'unit_price' => Money::toDecimal($fee), 'line_total' => Money::toDecimal($fee),
                ],
            ];
        }

        foreach ($input['services'] as $requested) {
            $service = Service::find($requested['service_id']) ?? throw new BusinessRuleException('A selected service was not found.');
            if (!(bool) $service['is_active']) {
                throw new BusinessRuleException('The service "' . $service['name'] . '" is no longer active.');
            }
            $quantity = $requested['quantity'];
            if ($quantity < 1 || $quantity > 99) {
                throw new BusinessRuleException('Quantity must be between 1 and 99.');
            }
            $unit = Money::fromDb($service['price']);
            $lines[] = [
                'line_minor' => $unit * $quantity,
                'row' => [
                    'item_type' => Billing::ITEM_SERVICE, 'service_id' => (int) $service['id'], 'quantity' => $quantity,
                    'description' => $service['name'], 'unit_price' => Money::toDecimal($unit), 'line_total' => Money::toDecimal($unit * $quantity),
                ],
            ];
        }

        return $lines;
    }

    /** @throws BusinessRuleException */
    private static function assertVisitBillable(int $visitId, int $patientId): void
    {
        $visit = Database::one('SELECT id, patient_id, status FROM visits WHERE id = :id', ['id' => $visitId]);
        if ($visit === null || (int) $visit['patient_id'] !== $patientId) {
            throw new BusinessRuleException('That visit does not belong to this patient.');
        }
        if ($visit['status'] !== 'Finalized') {
            throw new BusinessRuleException('Only finalized consultations can be invoiced.');
        }
        $existing = Invoice::activeForVisit($visitId);
        if ($existing !== null) {
            throw new BusinessRuleException('This visit already has invoice ' . $existing['invoice_no'] . '.');
        }
    }
}
