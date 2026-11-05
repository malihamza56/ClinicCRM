<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Page;

/** The payments ledger. Rows are only ever inserted (the database blocks UPDATE and DELETE). */
final class Payment
{
    private const SELECT = "SELECT pm.*, i.invoice_no, i.patient_id, CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            p.patient_code, u.full_name AS recorded_by_name
        FROM payments pm
        JOIN invoices i ON i.id = pm.invoice_id
        JOIN patients p ON p.id = i.patient_id
        JOIN users u ON u.id = pm.recorded_by";

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE pm.id = :id', ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function forInvoice(int $invoiceId): array
    {
        return Database::all(self::SELECT . ' WHERE pm.invoice_id = :i ORDER BY pm.paid_at, pm.id', ['i' => $invoiceId]);
    }

    /** Sums per entry type for one invoice, in minor-unit-safe DECIMAL strings. @return array<string,string> */
    public static function totalsForInvoice(int $invoiceId): array
    {
        $totals = ['Payment' => '0.00', 'Refund' => '0.00', 'Adjustment' => '0.00'];
        foreach (Database::all('SELECT entry_type, SUM(amount) AS total FROM payments WHERE invoice_id = :i GROUP BY entry_type', ['i' => $invoiceId]) as $row) {
            $totals[$row['entry_type']] = (string) $row['total'];
        }

        return $totals;
    }

    public static function create(array $data, string $receiptNo, int $recordedBy): int
    {
        return Database::insert(
            'INSERT INTO payments (invoice_id, receipt_no, entry_type, amount, method, reference, reason, paid_at, recorded_by)
             VALUES (:invoice, :receipt, :type, :amount, :method, :reference, :reason, NOW(), :by)',
            [
                'invoice'   => $data['invoice_id'],
                'receipt'   => $receiptNo,
                'type'      => $data['entry_type'],
                'amount'    => $data['amount'],
                'method'    => $data['method'],
                'reference' => $data['reference'],
                'reason'    => $data['reason'],
                'by'        => $recordedBy,
            ]
        );
    }

    /** @param array{from?:string,to?:string,method?:string,entry_type?:string,q?:string} $filters */
    public static function paginate(array $filters, int $page, int $perPage): Page
    {
        [$where, $params] = self::filterSql($filters);
        $total = (int) Database::value(
            'SELECT COUNT(*) FROM payments pm JOIN invoices i ON i.id = pm.invoice_id JOIN patients p ON p.id = i.patient_id WHERE ' . $where,
            $params
        );
        $items = Database::all(
            self::SELECT . " WHERE $where ORDER BY pm.paid_at DESC, pm.id DESC LIMIT " . (int) $perPage . ' OFFSET ' . Page::offset($page, $perPage),
            $params
        );

        return new Page($items, $total, $page, $perPage);
    }

    /** @return array<int,array<string,mixed>> */
    public static function exportRows(array $filters): array
    {
        [$where, $params] = self::filterSql($filters);

        return Database::all(self::SELECT . " WHERE $where ORDER BY pm.id", $params);
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private static function filterSql(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['from'])) {
            $where[] = 'DATE(pm.paid_at) >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'DATE(pm.paid_at) <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['method'])) {
            $where[] = 'pm.method = :method';
            $params['method'] = $filters['method'];
        }
        if (!empty($filters['entry_type'])) {
            $where[] = 'pm.entry_type = :type';
            $params['type'] = $filters['entry_type'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . Database::escapeLike($q) . '%';
            $where[] = "(pm.receipt_no LIKE :q1 OR i.invoice_no LIKE :q2 OR CONCAT(p.first_name, ' ', p.last_name) LIKE :q3 OR pm.reference LIKE :q4)";
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }

        return [implode(' AND ', $where), $params];
    }
}
