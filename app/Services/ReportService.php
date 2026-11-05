<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Money;

/** Report queries. Every number comes from persisted records in the chosen date range. */
final class ReportService
{
    /** @return array<string,mixed> */
    public static function financial(DateRange $range): array
    {
        $p = ['f' => $range->from, 't' => $range->to];

        $ledger = Database::one(
            "SELECT COALESCE(SUM(CASE WHEN entry_type = 'Payment' THEN amount END), 0) AS payments,
                    COALESCE(SUM(CASE WHEN entry_type = 'Refund' THEN amount END), 0) AS refunds,
                    COALESCE(SUM(CASE WHEN entry_type = 'Adjustment' THEN amount END), 0) AS adjustments
             FROM payments WHERE paid_at >= :f AND paid_at < DATE_ADD(:t, INTERVAL 1 DAY)",
            $p
        );
        $invoiced = Database::one(
            "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS total FROM invoices WHERE status <> 'Void' AND issue_date BETWEEN :f AND :t",
            $p
        );

        $byStatus = Database::all(
            'SELECT status, COUNT(*) AS n, COALESCE(SUM(total), 0) AS total, COALESCE(SUM(balance), 0) AS balance
             FROM invoices WHERE issue_date BETWEEN :f AND :t GROUP BY status ORDER BY FIELD(status, \'Paid\', \'Partial\', \'Unpaid\', \'Void\')',
            $p
        );

        $byDoctor = Database::all(
            "SELECT COALESCE(u.full_name, 'Front desk / no visit') AS doctor, COUNT(i.id) AS invoices, COALESCE(SUM(i.total), 0) AS total
             FROM invoices i
             LEFT JOIN visits v ON v.id = i.visit_id
             LEFT JOIN doctors d ON d.id = v.doctor_id
             LEFT JOIN users u ON u.id = d.user_id
             WHERE i.status <> 'Void' AND i.issue_date BETWEEN :f AND :t
             GROUP BY u.full_name ORDER BY total DESC",
            $p
        );

        $topServices = Database::all(
            "SELECT it.description, SUM(it.quantity) AS qty, SUM(it.line_total) AS total
             FROM invoice_items it JOIN invoices i ON i.id = it.invoice_id
             WHERE i.status <> 'Void' AND it.item_type = 'Service' AND i.issue_date BETWEEN :f AND :t
             GROUP BY it.description ORDER BY total DESC LIMIT 8",
            $p
        );

        $payments = Money::fromDb((string) $ledger['payments']);
        $refunds = Money::fromDb((string) $ledger['refunds']);

        return [
            'collected'   => $payments - $refunds,
            'payments'    => $payments,
            'refunds'     => $refunds,
            'adjustments' => Money::fromDb((string) $ledger['adjustments']),
            'invoiced'    => Money::fromDb((string) $invoiced['total']),
            'invoice_count' => (int) $invoiced['n'],
            'outstanding' => DashboardService::outstanding(),
            'trend'       => DashboardService::revenueTrend($range),
            'by_method'   => DashboardService::collectedByMethod($range),
            'by_status'   => $byStatus,
            'by_doctor'   => $byDoctor,
            'top_services' => $topServices,
        ];
    }

    /** @return array<string,mixed> */
    public static function operational(DateRange $range): array
    {
        $p = ['f' => $range->from, 't' => $range->to];

        $totals = Database::one(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = 'Completed'), 0) AS completed,
                    COALESCE(SUM(status = 'Cancelled'), 0) AS cancelled,
                    COALESCE(SUM(status = 'No Show'), 0) AS no_show,
                    COALESCE(SUM(booking_type = 'Walk-in'), 0) AS walk_ins
             FROM appointments WHERE appointment_date BETWEEN :f AND :t",
            $p
        );
        $byStatus = Database::all(
            'SELECT status, COUNT(*) AS n FROM appointments WHERE appointment_date BETWEEN :f AND :t GROUP BY status ORDER BY n DESC',
            $p
        );
        $byDoctor = Database::all(
            "SELECT u.full_name AS doctor, s.name AS specialty, COUNT(a.id) AS total,
                    COALESCE(SUM(a.status = 'Completed'), 0) AS completed,
                    COALESCE(SUM(a.status = 'No Show'), 0) AS no_show,
                    COALESCE(SUM(a.status = 'Cancelled'), 0) AS cancelled
             FROM appointments a JOIN doctors d ON d.id = a.doctor_id JOIN users u ON u.id = d.user_id JOIN specialties s ON s.id = d.specialty_id
             WHERE a.appointment_date BETWEEN :f AND :t GROUP BY d.id, u.full_name, s.name ORDER BY total DESC",
            $p
        );
        $newPatients = Database::all(
            'SELECT registered_on AS d, COUNT(*) AS n FROM patients WHERE registered_on BETWEEN :f AND :t GROUP BY registered_on',
            $p
        );
        $byDate = [];
        foreach ($newPatients as $row) {
            $byDate[$row['d']] = [(int) $row['n']];
        }
        $followUps = Database::one(
            "SELECT COALESCE(SUM(status = 'Completed'), 0) AS completed, COALESCE(SUM(status = 'Pending'), 0) AS pending,
                    COALESCE(SUM(status = 'Pending' AND follow_up_date < CURDATE()), 0) AS overdue
             FROM follow_ups WHERE follow_up_date BETWEEN :f AND :t",
            $p
        );

        return [
            'totals'       => array_map('intval', $totals),
            'by_status'    => $byStatus,
            'by_doctor'    => $byDoctor,
            'trend'        => DashboardService::bookingTrend($range),
            'new_patients' => DashboardService::bucket($range, $byDate, 1, ['registrations']),
            'follow_ups'   => array_map('intval', $followUps),
        ];
    }
}
