<?php
declare(strict_types=1);

use App\Core\Money;
use Tests\Support\Tester;

return static function (Tester $t): void {
    $t->group('Unit: Money (integer minor units, no floats)');

    $t->test('parses whole and decimal amounts to minor units', function () use ($t) {
        $t->same(150000, Money::parse('1500'));
        $t->same(150050, Money::parse('1500.5'));
        $t->same(150005, Money::parse('1500.05'));
        $t->same(0, Money::parse('0'));
    });
    $t->test('rejects invalid money text', function () use ($t) {
        foreach (['', 'abc', '-5', '1,500', '12.345', '1e3', '.5', '5.', ' ', '1 2'] as $bad) {
            $t->same(null, Money::parse($bad), "should reject \"$bad\"");
        }
    });
    $t->test('formats back to a database decimal exactly', function () use ($t) {
        $t->same('1500.50', Money::toDecimal(150050));
        $t->same('0.07', Money::toDecimal(7));
        $t->same('-3.20', Money::toDecimal(-320));
    });
    $t->test('0.1 + 0.2 style sums are exact', function () use ($t) {
        $sum = Money::parse('0.10') + Money::parse('0.20');
        $t->same('0.30', Money::toDecimal($sum));
        $t->same(30, $sum);
    });
    $t->test('percentage rounds half up to the nearest minor unit', function () use ($t) {
        $t->same(75, Money::percentOf(1500, '5'));      // 5% of 15.00 = 0.75
        $t->same(1, Money::percentOf(25, '5'));         // 1.25 -> 1
        $t->same(2, Money::percentOf(30, '5'));         // 1.5 -> rounds half up to 2
        $t->same(1, Money::percentOf(10, '5'));         // 0.5 -> rounds up to 1
        $t->same(0, Money::percentOf(9, '5'));          // 0.45 -> 0
        $t->same(1050, Money::percentOf(10000, '10.5'));
    });
    $t->test('formats for display with thousands separators', function () use ($t) {
        $t->same('Rs. 1,234,567.89', Money::format(123456789));
        $t->same('PKR 0.05', Money::format(5, 'PKR'));
    });
    $t->test('fromDb reads DECIMAL strings', function () use ($t) {
        $t->same(12345, Money::fromDb('123.45'));
        $t->same(0, Money::fromDb(null));
        $t->same(-500, Money::fromDb('-5.00'));
    });
};
