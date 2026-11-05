<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Config\Billing;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Response;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AuditService;
use App\Services\ExportService;

final class PaymentController extends Controller
{
    public function index(): Response
    {
        $filters = $this->filters();
        $page = Payment::paginate($filters, max(1, $this->request->int('page', 1)), 20);

        return $this->view('payments/index', ['title' => 'Payments', 'page' => $page, 'filters' => $filters, 'methods' => Billing::METHODS]);
    }

    public function receipt(int $id): Response
    {
        $payment = Payment::find($id) ?? throw new HttpException(404);
        $invoice = Invoice::find((int) $payment['invoice_id']) ?? throw new HttpException(404);

        return $this->view('payments/receipt', ['title' => 'Receipt ' . $payment['receipt_no'], 'p' => $payment, 'i' => $invoice], 'layouts/print');
    }

    public function export(): Response
    {
        [$headings, $rows] = ExportService::dataset('payments', $this->filters());
        AuditService::log('export.csv', 'payment', null, 'Exported ' . count($rows) . ' payment entries to CSV');

        return Response::download('payments-' . date('Y-m-d') . '.csv', ExportService::toCsv($headings, $rows), 'text/csv; charset=UTF-8');
    }

    /** @return array<string,string> */
    private function filters(): array
    {
        $date = fn (string $k): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->request->string($k)) === 1 ? $this->request->string($k) : '';
        $type = $this->request->string('entry_type');
        $method = $this->request->string('method');

        return [
            'q' => $this->request->string('q'), 'from' => $date('from'), 'to' => $date('to'),
            'method' => in_array($method, Billing::METHODS, true) ? $method : '',
            'entry_type' => in_array($type, [Billing::TYPE_PAYMENT, Billing::TYPE_REFUND, Billing::TYPE_ADJUSTMENT], true) ? $type : '',
        ];
    }
}
