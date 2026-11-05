<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Billing;
use App\Core\Auth;
use App\Core\BusinessRuleException;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Models\Doctor;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Services\AuditService;
use App\Services\BillingService;
use App\Services\ExportService;

final class InvoiceController extends Controller
{
    public function index(): Response
    {
        $filters = $this->filters();
        $page = Invoice::paginate($filters, max(1, $this->request->int('page', 1)), 15);

        return $this->view('invoices/index', ['title' => 'Invoices', 'page' => $page, 'filters' => $filters]);
    }

    public function create(): Response
    {
        $patientId = $this->request->int('patient_id');
        $patient = $patientId > 0 ? Patient::find($patientId) : null;
        $visitId = $this->request->int('visit_id');
        $visits = $patient ? Invoice::uninvoicedVisits((int) $patient['id']) : [];
        $preselected = null;
        foreach ($visits as $visit) {
            if ((int) $visit['id'] === $visitId) {
                $preselected = $visit;
            }
        }

        return $this->view('invoices/form', [
            'title' => 'New invoice', 'patient' => $patient, 'visits' => $visits, 'visit' => $preselected,
            'doctors' => Doctor::all(true), 'services' => Service::all(true), 'taxRate' => setting('tax_rate_percent', '0.00'),
        ]);
    }

    public function store(): Response
    {
        $patientId = $this->request->int('patient_id');
        if ($patientId === 0) {
            return $this->backWithErrors('/invoices/create', ['patient_id' => 'Please choose a patient.']);
        }
        $body = $this->request->body();

        $services = [];
        $ids = is_array($body['service_id'] ?? null) ? array_values($body['service_id']) : [];
        $qtys = is_array($body['service_qty'] ?? null) ? array_values($body['service_qty']) : [];
        foreach ($ids as $i => $sid) {
            if (is_string($sid) && ctype_digit($sid) && (int) $sid > 0) {
                $qty = $qtys[$i] ?? '1';
                $services[] = ['service_id' => (int) $sid, 'quantity' => is_string($qty) && ctype_digit($qty) ? (int) $qty : 0];
            }
        }
        $doctorId = !empty($body['include_consultation']) ? $this->request->int('consultation_doctor_id') : 0;
        $visitId = $this->request->int('visit_id');
        $notes = $this->request->string('notes');
        $back = '/invoices/create?patient_id=' . $patientId . ($visitId > 0 ? '&visit_id=' . $visitId : '');

        if (mb_strlen($notes) > 500) {
            return $this->backWithErrors($back, ['notes' => 'Notes must not be longer than 500 characters.']);
        }

        try {
            $id = BillingService::createInvoice($patientId, [
                'consultation_doctor_id' => $doctorId > 0 ? $doctorId : null,
                'services' => $services,
                'discount' => $this->request->string('discount'),
                'notes' => $notes !== '' ? $notes : null,
                'visit_id' => $visitId > 0 ? $visitId : null,
            ], (int) Auth::id());
        } catch (BusinessRuleException $e) {
            return $this->backWithErrors($back, ['items' => $e->getMessage()]);
        }

        return $this->redirectWith("/invoices/$id", 'success', 'Invoice created.');
    }

    public function show(int $id): Response
    {
        $invoice = Invoice::find($id) ?? throw new HttpException(404);

        return $this->view('invoices/show', [
            'title' => $invoice['invoice_no'], 'i' => $invoice, 'items' => Invoice::items($id), 'payments' => Payment::forInvoice($id),
            'methods' => Billing::METHODS,
        ]);
    }

    public function print(int $id): Response
    {
        $invoice = Invoice::find($id) ?? throw new HttpException(404);

        return $this->view('invoices/print', [
            'title' => 'Invoice ' . $invoice['invoice_no'], 'i' => $invoice, 'items' => Invoice::items($id), 'payments' => Payment::forInvoice($id),
        ], 'layouts/print');
    }

    public function recordPayment(int $id): Response
    {
        return $this->entry($id, fn () => BillingService::recordPayment($id, $this->request->string('amount'), $this->request->string('method'), $this->request->string('reference') ?: null, (int) Auth::id()), 'Payment recorded.');
    }

    public function refund(int $id): Response
    {
        return $this->entry($id, fn () => BillingService::recordRefund($id, $this->request->string('amount'), $this->request->string('method'), $this->request->string('reason'), (int) Auth::id()), 'Refund recorded.');
    }

    public function adjust(int $id): Response
    {
        return $this->entry($id, fn () => BillingService::recordAdjustment($id, $this->request->string('amount'), $this->request->string('reason'), (int) Auth::id()), 'Adjustment recorded.');
    }

    public function void(int $id): Response
    {
        return $this->entry($id, fn () => BillingService::voidInvoice($id, $this->request->string('reason'), (int) Auth::id()), 'Invoice voided.');
    }

    public function export(): Response
    {
        [$headings, $rows] = ExportService::dataset('invoices', $this->filters());
        AuditService::log('export.csv', 'invoice', null, 'Exported ' . count($rows) . ' invoices to CSV');

        return Response::download('invoices-' . date('Y-m-d') . '.csv', ExportService::toCsv($headings, $rows), 'text/csv; charset=UTF-8');
    }

    /** Runs a money-changing action and reports the outcome back on the invoice page. */
    private function entry(int $invoiceId, callable $action, string $successMessage): Response
    {
        Invoice::find($invoiceId) ?? throw new HttpException(404);
        try {
            $action();
        } catch (BusinessRuleException $e) {
            return $this->redirectWith("/invoices/$invoiceId", 'error', $e->getMessage());
        }

        return $this->redirectWith("/invoices/$invoiceId", 'success', $successMessage);
    }

    /** @return array<string,mixed> */
    private function filters(): array
    {
        $status = $this->request->string('status');

        return [
            'q' => $this->request->string('q'),
            'status' => in_array($status, [Billing::STATUS_UNPAID, Billing::STATUS_PARTIAL, Billing::STATUS_PAID, Billing::STATUS_VOID], true) ? $status : '',
            'from' => $this->dateOrEmpty('from'), 'to' => $this->dateOrEmpty('to'),
            'outstanding' => $this->request->string('outstanding') === '1',
        ];
    }

    private function dateOrEmpty(string $key): string
    {
        $v = $this->request->string($key);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : '';
    }
}
