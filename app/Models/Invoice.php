<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Page;

final class Invoice
{
    private const SELECT = "SELECT i.*, CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.patient_code, p.phone AS patient_phone,
            p.address AS patient_address, cu.full_name AS created_by_name
        FROM invoices i
        JOIN patients p ON p.id = i.patient_id
        JOIN users cu ON cu.id = i.created_by";

    public static function find(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE i.id = :id', ['id' => $id]);
    }

    /** Locks the invoice row so payments on the same invoice are processed one at a time. */
    public static function findForUpdate(int $id): ?array
    {
        return Database::one('SELECT * FROM invoices WHERE id = :id FOR UPDATE', ['id' => $id]);
    }

    /** @return array<int,array<string,mixed>> */
    public static function items(int $invoiceId): array
    {
        return Database::all('SELECT * FROM invoice_items WHERE invoice_id = :i ORDER BY id', ['i' => $invoiceId]);
    }

    /** @param array{q?:string,status?:string,from?:string,to?:string,patient_id?:int,outstanding?:bool} $filters */
    public static function paginate(array $filters, int $page, int $perPage): Page
    {
        [$where, $params] = self::filterSql($filters);
        $total = (int) Database::value('SELECT COUNT(*) FROM invoices i JOIN patients p ON p.id = i.patient_id WHERE ' . $where, $params);
        $items = Database::all(
            self::SELECT . " WHERE $where ORDER BY i.issue_date DESC, i.id DESC LIMIT " . (int) $perPage . ' OFFSET ' . Page::offset($page, $perPage),
            $params
        );

        return new Page($items, $total, $page, $perPage);
    }

    /** @return array<int,array<string,mixed>> */
    public static function exportRows(array $filters): array
    {
        [$where, $params] = self::filterSql($filters);

        return Database::all(self::SELECT . " WHERE $where ORDER BY i.id", $params);
    }

    /** @return array<int,array<string,mixed>> */
    public static function forPatient(int $patientId): array
    {
        return Database::all(self::SELECT . ' WHERE i.patient_id = :p ORDER BY i.issue_date DESC, i.id DESC', ['p' => $patientId]);
    }

    /** The live (not void) invoice already raised for a visit, if any. */
    public static function activeForVisit(int $visitId): ?array
    {
        return Database::one("SELECT id, invoice_no FROM invoices WHERE visit_id = :v AND status <> 'Void'", ['v' => $visitId]);
    }

    /**
     * Finalized visits of a patient that have no live invoice yet.
     * Only billing-relevant columns are selected on purpose: no clinical content reaches billing screens.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function uninvoicedVisits(int $patientId): array
    {
        return Database::all(
            "SELECT v.id, v.visited_at, d.id AS doctor_id, u.full_name AS doctor_name, d.consultation_fee
             FROM visits v JOIN doctors d ON d.id = v.doctor_id JOIN users u ON u.id = d.user_id
             WHERE v.patient_id = :p AND v.status = 'Finalized'
               AND NOT EXISTS (SELECT 1 FROM invoices i WHERE i.visit_id = v.id AND i.status <> 'Void')
             ORDER BY v.visited_at DESC",
            ['p' => $patientId]
        );
    }

    public static function create(array $h, string $number, int $createdBy): int
    {
        return Database::insert(
            'INSERT INTO invoices (invoice_no, patient_id, visit_id, issue_date, subtotal, discount_amount, tax_rate, tax_amount,
                                   total, balance, status, notes, created_by)
             VALUES (:no, :patient, :visit, CURDATE(), :subtotal, :discount, :rate, :tax, :total, :balance, :status, :notes, :by)',
            [
                'no'       => $number,
                'patient'  => $h['patient_id'],
                'visit'    => $h['visit_id'],
                'subtotal' => $h['subtotal'],
                'discount' => $h['discount_amount'],
                'rate'     => $h['tax_rate'],
                'tax'      => $h['tax_amount'],
                'total'    => $h['total'],
                'balance'  => $h['total'],
                'status'   => $h['total'] === '0.00' ? 'Paid' : 'Unpaid',
                'notes'    => $h['notes'],
                'by'       => $createdBy,
            ]
        );
    }

    public static function addItem(int $invoiceId, array $item): void
    {
        Database::run(
            'INSERT INTO invoice_items (invoice_id, item_type, service_id, description, quantity, unit_price, line_total)
             VALUES (:i, :type, :service, :desc, :qty, :price, :line)',
            [
                'i'       => $invoiceId,
                'type'    => $item['item_type'],
                'service' => $item['service_id'],
                'desc'    => $item['description'],
                'qty'     => $item['quantity'],
                'price'   => $item['unit_price'],
                'line'    => $item['line_total'],
            ]
        );
    }

    public static function updateAmounts(int $id, string $amountPaid, string $adjusted, string $balance, string $status): void
    {
        Database::run(
            'UPDATE invoices SET amount_paid = :paid, adjusted_amount = :adj, balance = :bal, status = :s WHERE id = :id',
            ['paid' => $amountPaid, 'adj' => $adjusted, 'bal' => $balance, 's' => $status, 'id' => $id]
        );
    }

    public static function void(int $id, int $userId, string $reason): void
    {
        Database::run(
            "UPDATE invoices SET status = 'Void', balance = 0.00, voided_at = NOW(), voided_by = :u, void_reason = :r WHERE id = :id",
            ['u' => $userId, 'r' => $reason, 'id' => $id]
        );
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private static function filterSql(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'i.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['outstanding'])) {
            $where[] = "i.status IN ('Unpaid','Partial') AND i.balance > 0";
        }
        if (!empty($filters['from'])) {
            $where[] = 'i.issue_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'i.issue_date <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['patient_id'])) {
            $where[] = 'i.patient_id = :patient';
            $params['patient'] = (int) $filters['patient_id'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . Database::escapeLike($q) . '%';
            $where[] = "(i.invoice_no LIKE :q1 OR p.patient_code LIKE :q2 OR CONCAT(p.first_name, ' ', p.last_name) LIKE :q3)";
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }

        return [implode(' AND ', $where), $params];
    }
}
