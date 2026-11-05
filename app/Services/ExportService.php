<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;

/** Builds CSV downloads. Cell values are neutralised against spreadsheet formula injection. */
final class ExportService
{
    /** @return array{0:string[],1:array<int,array<int,mixed>>} headings and rows */
    public static function dataset(string $name, array $filters): array
    {
        return match ($name) {
            'patients' => [
                ['Patient ID', 'First name', 'Last name', 'Gender', 'Date of birth', 'Phone', 'Email', 'City', 'Registered on', 'Archived'],
                array_map(static fn (array $r): array => [
                    $r['patient_code'], $r['first_name'], $r['last_name'], $r['gender'], $r['date_of_birth'], $r['phone'],
                    $r['email'], $r['city'], $r['registered_on'], $r['is_archived'] ? 'Yes' : 'No',
                ], Patient::exportRows($filters)),
            ],
            'appointments' => [
                ['Date', 'Start', 'End', 'Patient ID', 'Patient', 'Doctor', 'Specialty', 'Type', 'Status', 'Reason'],
                array_map(static fn (array $r): array => [
                    $r['appointment_date'], substr((string) $r['start_time'], 0, 5), substr((string) $r['end_time'], 0, 5), $r['patient_code'],
                    $r['patient_name'], $r['doctor_name'], $r['specialty_name'], $r['booking_type'], $r['status'], $r['reason'],
                ], Appointment::exportRows($filters)),
            ],
            'invoices' => [
                ['Invoice', 'Date', 'Patient ID', 'Patient', 'Subtotal', 'Discount', 'Tax', 'Total', 'Paid', 'Adjusted', 'Balance', 'Status'],
                array_map(static fn (array $r): array => [
                    $r['invoice_no'], $r['issue_date'], $r['patient_code'], $r['patient_name'], $r['subtotal'], $r['discount_amount'],
                    $r['tax_amount'], $r['total'], $r['amount_paid'], $r['adjusted_amount'], $r['balance'], $r['status'],
                ], Invoice::exportRows($filters)),
            ],
            'payments' => [
                ['Receipt', 'Date', 'Invoice', 'Patient ID', 'Patient', 'Type', 'Amount', 'Method', 'Reference', 'Reason', 'Recorded by'],
                array_map(static fn (array $r): array => [
                    $r['receipt_no'], $r['paid_at'], $r['invoice_no'], $r['patient_code'], $r['patient_name'], $r['entry_type'],
                    $r['amount'], $r['method'], $r['reference'], $r['reason'], $r['recorded_by_name'],
                ], Payment::exportRows($filters)),
            ],
            default => throw new \InvalidArgumentException('Unknown export'),
        };
    }

    /** @param string[] $headings @param array<int,array<int,mixed>> $rows */
    public static function toCsv(array $headings, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headings, ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($handle, array_map([self::class, 'safeCell'], $row), ',', '"', '\\');
        }
        rewind($handle);

        // The BOM makes Excel open the file as UTF-8 so names with accents display correctly.
        return "\xEF\xBB\xBF" . stream_get_contents($handle);
    }

    /**
     * A cell starting with = + - @ (or tab / carriage return) can run as a formula in Excel.
     * Prefixing an apostrophe makes the spreadsheet treat it as plain text.
     */
    public static function safeCell(mixed $value): string
    {
        $text = (string) ($value ?? '');
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($text)) {
            return "'" . $text;
        }

        return $text;
    }
}
