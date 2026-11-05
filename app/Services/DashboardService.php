<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Money;
use App\Models\Appointment;
use App\Models\FollowUp;
use App\Models\Invoice;
use App\Models\Visit;

/** Numbers for the role dashboards. Every figure is computed from the database at request time. */
final class DashboardService
{
    private const GROUP_COMPLETED = ['Completed'];
    private const GROUP_PENDING   = ['Scheduled', 'Confirmed', 'Waiting', 'In Progress'];
    private const GROUP_CANCELLED = ['Cancelled', 'No Show'];

    /** @return array<string,mixed> */
    public static function admin(DateRange $range): array
    {
        $today = date('Y-m-d');
        $todayStats = self::appointmentGroups($today, $today);

        return [
            'kpis' => [
                'revenue'     => self::collected($range),
                'today_total' => array_sum($todayStats),
                'ongoing'     => self::countStatuses($today, ['Waiting', 'In Progress']),
                'completed'   => $todayStats['completed'],
                'cancelled'   => $todayStats['cancelled'],
            ],
            'secondary' => [
                'patients'    => (int) Database::value('SELECT COUNT(*) FROM patients WHERE is_archived = 0'),
                'new_patients' => (int) Database::value('SELECT COUNT(*) FROM patients WHERE registered_on BETWEEN :f AND :t', ['f' => $range->from, 't' => $range->to]),
                'doctors'     => (int) Database::value('SELECT COUNT(*) FROM doctors d JOIN users u ON u.id = d.user_id WHERE d.is_active = 1 AND u.is_active = 1'),
                'outstanding' => self::outstanding(),
            ],
            'trend'      => self::bookingTrend($range),
            'attendance' => $todayStats,
            'schedule'   => array_slice(Appointment::forDay($today), 0, 8),
            'follow_ups' => FollowUp::counts(),
            'outstanding_invoices' => Invoice::paginate(['outstanding' => true], 1, 5)->items,
        ];
    }

    /** @return array<string,mixed> */
    public static function doctor(int $doctorId): array
    {
        $today = date('Y-m-d');
        $stats = self::appointmentGroups($today, $today, $doctorId);

        return [
            'kpis' => [
                'today_total' => array_sum($stats),
                'waiting'     => self::countStatuses($today, ['Waiting'], $doctorId),
                'in_progress' => self::countStatuses($today, ['In Progress'], $doctorId),
                'completed'   => $stats['completed'],
            ],
            'queue'      => Appointment::queue($today, $doctorId),
            'schedule'   => Appointment::forDay($today, $doctorId),
            'follow_ups' => FollowUp::counts($doctorId),
            'due_follow_ups' => FollowUp::paginate(['bucket' => 'due', 'doctor_id' => $doctorId], 1, 5)->items,
            'overdue_follow_ups' => FollowUp::paginate(['bucket' => 'overdue', 'doctor_id' => $doctorId], 1, 5)->items,
            'recent_visits' => Visit::recentForDoctor($doctorId, 6),
            'attendance' => $stats,
        ];
    }

    /** @return array<string,mixed> */
    public static function receptionist(): array
    {
        $today = date('Y-m-d');
        $stats = self::appointmentGroups($today, $today);

        return [
            'kpis' => [
                'today_total'  => array_sum($stats),
                'waiting'      => self::countStatuses($today, ['Waiting', 'In Progress']),
                'expected'     => self::countStatuses($today, ['Scheduled', 'Confirmed']),
                'new_patients' => (int) Database::value('SELECT COUNT(*) FROM patients WHERE registered_on = :d', ['d' => $today]),
            ],
            'queue'      => Appointment::queue($today),
            'schedule'   => Appointment::forDay($today),
            'follow_ups' => FollowUp::counts(),
            'attendance' => $stats,
        ];
    }

    /** @return array<string,mixed> */
    public static function accountant(DateRange $range): array
    {
        return [
            'kpis' => [
                'revenue'     => self::collected($range),
                'outstanding' => self::outstanding(),
                'invoices'    => (int) Database::value("SELECT COUNT(*) FROM invoices WHERE status <> 'Void' AND issue_date BETWEEN :f AND :t", ['f' => $range->from, 't' => $range->to]),
                'refunds'     => Money::fromDb((string) Database::value(
                    "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE entry_type = 'Refund' AND paid_at >= :f AND paid_at < DATE_ADD(:t, INTERVAL 1 DAY)",
                    ['f' => $range->from, 't' => $range->to]
                )),
            ],
            'revenue_trend' => self::revenueTrend($range),
            'by_method'     => self::collectedByMethod($range),
            'outstanding_invoices' => Invoice::paginate(['outstanding' => true], 1, 6)->items,
        ];
    }

    /** Net money collected in the range (payments minus refunds), in minor units. */
    public static function collected(DateRange $range): int
    {
        return Money::fromDb((string) Database::value(
            "SELECT COALESCE(SUM(CASE entry_type WHEN 'Payment' THEN amount WHEN 'Refund' THEN -amount ELSE 0 END), 0)
             FROM payments WHERE paid_at >= :f AND paid_at < DATE_ADD(:t, INTERVAL 1 DAY)",
            ['f' => $range->from, 't' => $range->to]
        ));
    }

    /** Total still owed on live invoices, in minor units. */
    public static function outstanding(): int
    {
        return Money::fromDb((string) Database::value("SELECT COALESCE(SUM(balance), 0) FROM invoices WHERE status IN ('Unpaid','Partial')"));
    }

    /** @return array{completed:int,pending:int,cancelled:int} */
    public static function appointmentGroups(string $from, string $to, ?int $doctorId = null): array
    {
        $sql = "SELECT
                  COALESCE(SUM(status IN ('Completed')), 0) AS completed,
                  COALESCE(SUM(status IN ('Scheduled','Confirmed','Waiting','In Progress')), 0) AS pending,
                  COALESCE(SUM(status IN ('Cancelled','No Show')), 0) AS cancelled
                FROM appointments WHERE appointment_date BETWEEN :f AND :t";
        $params = ['f' => $from, 't' => $to];
        if ($doctorId !== null) {
            $sql .= ' AND doctor_id = :d';
            $params['d'] = $doctorId;
        }
        $row = Database::one($sql, $params);

        return ['completed' => (int) $row['completed'], 'pending' => (int) $row['pending'], 'cancelled' => (int) $row['cancelled']];
    }

    /**
     * Completed / pending / cancelled appointments per day (or per week for long ranges).
     *
     * @return array{labels:string[],completed:int[],pending:int[],cancelled:int[]}
     */
    public static function bookingTrend(DateRange $range): array
    {
        $rows = Database::all(
            "SELECT appointment_date AS d,
                    SUM(status = 'Completed') AS completed,
                    SUM(status IN ('Scheduled','Confirmed','Waiting','In Progress')) AS pending,
                    SUM(status IN ('Cancelled','No Show')) AS cancelled
             FROM appointments WHERE appointment_date BETWEEN :f AND :t GROUP BY appointment_date",
            ['f' => $range->from, 't' => $range->to]
        );
        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r['d']] = [(int) $r['completed'], (int) $r['pending'], (int) $r['cancelled']];
        }

        return self::bucket($range, $byDate, 3, ['completed', 'pending', 'cancelled']);
    }

    /** @return array{labels:string[],collected:float[]} Net collected money per day/week (plain numbers for the chart only). */
    public static function revenueTrend(DateRange $range): array
    {
        $rows = Database::all(
            "SELECT DATE(paid_at) AS d, SUM(CASE entry_type WHEN 'Payment' THEN amount WHEN 'Refund' THEN -amount ELSE 0 END) AS net
             FROM payments WHERE paid_at >= :f AND paid_at < DATE_ADD(:t, INTERVAL 1 DAY) GROUP BY DATE(paid_at)",
            ['f' => $range->from, 't' => $range->to]
        );
        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r['d']] = [Money::fromDb((string) $r['net'])];
        }
        $series = self::bucket($range, $byDate, 1, ['collected']);
        // Charts need plain numbers; convert minor units to major units for display only.
        $series['collected'] = array_map(static fn (int $minor): float => $minor / 100, $series['collected']);

        return $series;
    }

    /** @return array<int,array{method:string,amount:int}> */
    public static function collectedByMethod(DateRange $range): array
    {
        $rows = Database::all(
            "SELECT method, SUM(CASE entry_type WHEN 'Payment' THEN amount ELSE -amount END) AS net
             FROM payments WHERE entry_type IN ('Payment','Refund') AND paid_at >= :f AND paid_at < DATE_ADD(:t, INTERVAL 1 DAY)
             GROUP BY method ORDER BY net DESC",
            ['f' => $range->from, 't' => $range->to]
        );

        return array_map(static fn (array $r): array => ['method' => (string) $r['method'], 'amount' => Money::fromDb((string) $r['net'])], $rows);
    }

    /**
     * Fills missing days with zero and groups into weeks when the range is longer than 31 days.
     *
     * @param array<string,int[]> $byDate date => list of numbers
     * @param string[] $names series names
     * @return array<string,array<int,int|string>>
     */
    public static function bucket(DateRange $range, array $byDate, int $width, array $names): array
    {
        $weekly = $range->days() > 31;
        $labels = [];
        $values = array_fill(0, $width, []);
        $index = [];

        foreach ($range->dates() as $date) {
            $key = $weekly ? date('Y-m-d', strtotime('monday this week', strtotime($date))) : $date;
            if (!isset($index[$key])) {
                $index[$key] = count($labels);
                $labels[] = $weekly ? 'Wk ' . date('d M', strtotime($key)) : date('d M', strtotime($key));
                for ($i = 0; $i < $width; $i++) {
                    $values[$i][] = 0;
                }
            }
            foreach ($byDate[$date] ?? [] as $i => $n) {
                $values[$i][$index[$key]] += $n;
            }
        }

        $out = ['labels' => $labels];
        foreach ($names as $i => $name) {
            $out[$name] = $values[$i];
        }

        return $out;
    }

    private static function countStatuses(string $date, array $statuses, ?int $doctorId = null): int
    {
        $placeholders = [];
        $params = ['d' => $date];
        foreach ($statuses as $i => $status) {
            $placeholders[] = ':s' . $i;
            $params['s' . $i] = $status;
        }
        $sql = 'SELECT COUNT(*) FROM appointments WHERE appointment_date = :d AND status IN (' . implode(',', $placeholders) . ')';
        if ($doctorId !== null) {
            $sql .= ' AND doctor_id = :doc';
            $params['doc'] = $doctorId;
        }

        return (int) Database::value($sql, $params);
    }
}
