<?php
declare(strict_types=1);

use App\Config\AppointmentStatus as S;
use App\Core\Config;
use App\Services\AuthService;
use App\Services\DateRange;
use App\Services\ExportService;
use Tests\Support\Tester;

return static function (Tester $t): void {
    $t->group('Unit: appointment status rules');

    $t->test('allowed transitions', function () use ($t) {
        $t->true(S::canMove(S::SCHEDULED, S::CONFIRMED));
        $t->true(S::canMove(S::CONFIRMED, S::WAITING));
        $t->true(S::canMove(S::WAITING, S::IN_PROGRESS));
        $t->true(S::canMove(S::IN_PROGRESS, S::COMPLETED));
        $t->true(S::canMove(S::SCHEDULED, S::CANCELLED));
        $t->true(S::canMove(S::CONFIRMED, S::NO_SHOW));
    });
    $t->test('forbidden transitions', function () use ($t) {
        $t->false(S::canMove(S::COMPLETED, S::SCHEDULED));
        $t->false(S::canMove(S::CANCELLED, S::CONFIRMED));
        $t->false(S::canMove(S::SCHEDULED, S::COMPLETED), 'cannot skip the consultation');
        $t->false(S::canMove(S::IN_PROGRESS, S::CANCELLED));
        $t->false(S::canMove(S::NO_SHOW, S::WAITING));
        $t->false(S::canMove('Nonsense', S::WAITING));
    });
    $t->test('rescheduling is limited to appointments that have not started', function () use ($t) {
        $t->true(S::canReschedule(S::SCHEDULED) && S::canReschedule(S::CONFIRMED));
        $t->false(S::canReschedule(S::WAITING));
    });

    $t->group('Unit: password policy');
    $t->test('enforces length and character mix', function () use ($t) {
        $t->true(AuthService::passwordProblem('short1') !== null);
        $t->true(AuthService::passwordProblem('onlyletterslongpassword') !== null);
        $t->true(AuthService::passwordProblem('12345678901234') !== null);
        $t->same(null, AuthService::passwordProblem('GoodPassword2026'));
        $t->true(AuthService::passwordProblem(str_repeat('a1', 40)) !== null, 'over 72 bytes is refused (bcrypt limit)');
    });
    $t->test('temporary passwords satisfy the policy and differ each time', function () use ($t) {
        $a = AuthService::temporaryPassword();
        $b = AuthService::temporaryPassword();
        $t->same(null, AuthService::passwordProblem($a));
        $t->true($a !== $b);
    });

    $t->group('Unit: CSV export safety');
    $t->test('neutralises spreadsheet formulas', function () use ($t) {
        foreach (['=1+1', '+cmd', '-2+3', '@SUM(A1)', "\tTAB"] as $evil) {
            $t->true(str_starts_with(ExportService::safeCell($evil), "'"), "should neutralise $evil");
        }
        $t->same('Plain text', ExportService::safeCell('Plain text'));
        $t->same('-5.00', ExportService::safeCell('-5.00'), 'negative numbers stay numbers');
        $t->same('', ExportService::safeCell(null));
    });
    $t->test('CSV has a UTF-8 BOM and quotes commas', function () use ($t) {
        $csv = ExportService::toCsv(['A', 'B'], [['x, y', '=HYPERLINK("http://evil")']]);
        $t->true(str_starts_with($csv, "\xEF\xBB\xBF"));
        $t->contains('"x, y"', $csv);
        $t->contains("'=HYPERLINK", $csv);
    });

    $t->group('Unit: date ranges');
    $t->test('presets and custom ranges are validated', function () use ($t) {
        $r = DateRange::fromInput('7d', null, null);
        $t->same(7, $r->days());
        $t->same(date('Y-m-d'), $r->to);
        $c = DateRange::fromInput('custom', '2026-01-01', '2026-01-10');
        $t->same(10, $c->days());
        $t->same('30d', DateRange::fromInput('custom', '2026-02-01', '2026-01-01')->preset, 'reversed range falls back');
        $t->same('30d', DateRange::fromInput('custom', 'junk', '2026-01-01')->preset, 'invalid date falls back');
        $t->same('30d', DateRange::fromInput('custom', '2020-01-01', '2026-01-01')->preset, 'range over a year falls back');
    });
    $t->test('bucketing fills missing days with zero', function () use ($t) {
        $range = new DateRange('2026-01-01', '2026-01-03', 'custom');
        $out = App\Services\DashboardService::bucket($range, ['2026-01-02' => [4, 1]], 2, ['a', 'b']);
        $t->same([0, 4, 0], $out['a']);
        $t->same([0, 1, 0], $out['b']);
        $t->same(3, count($out['labels']));
    });
};
