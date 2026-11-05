<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Hands out the next number for patient codes, invoices, receipts and prescriptions.
 * The row is locked until the surrounding transaction ends, so two people can never get the same number
 * and a rolled-back transaction does not burn a number.
 */
final class Sequence
{
    public const PATIENT      = 'patient';
    public const INVOICE      = 'invoice';
    public const RECEIPT      = 'receipt';
    public const PRESCRIPTION = 'prescription';

    public static function next(string $name): int
    {
        Database::run('UPDATE number_sequences SET last_value = LAST_INSERT_ID(last_value + 1) WHERE name = :n', ['n' => $name]);

        return (int) Database::value('SELECT LAST_INSERT_ID()');
    }
}
