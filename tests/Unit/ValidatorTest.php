<?php
declare(strict_types=1);

use App\Core\Validator;
use Tests\Support\Tester;

return static function (Tester $t): void {
    $t->group('Unit: Validator');

    $rules = ['name' => 'required|name|max:20', 'email' => 'email', 'dob' => 'required|date|date_not_future', 'phone' => 'phone', 'qty' => 'int', 'pct' => 'number:0,100', 'amt' => 'money_positive', 'g' => 'in:A,B'];

    $t->test('accepts valid input and trims values', function () use ($t, $rules) {
        $v = Validator::make(['name' => '  Ali Raza ', 'email' => 'a@b.co', 'dob' => '1990-01-31', 'phone' => '0300 1234567', 'qty' => '5', 'pct' => '12.5', 'amt' => '10.50', 'g' => 'A'], $rules);
        $t->false($v->fails());
        $t->same('Ali Raza', $v->validated()['name']);
    });
    $t->test('required fields report errors; optional empty fields become null', function () use ($t, $rules) {
        $v = Validator::make(['name' => '', 'dob' => ''], $rules);
        $t->true($v->fails());
        $t->true(isset($v->errors()['name']) && isset($v->errors()['dob']));
        $t->same(null, $v->validated()['email']);
    });
    $t->test('rejects impossible and future dates', function () use ($t, $rules) {
        $t->true(Validator::make(['name' => 'A', 'dob' => '2023-02-30'], $rules)->fails());
        $t->true(Validator::make(['name' => 'A', 'dob' => '2999-01-01'], $rules)->fails());
        $t->true(Validator::make(['name' => 'A', 'dob' => 'yesterday'], $rules)->fails());
    });
    $t->test('rejects bad names, emails, phones, numbers, enums', function () use ($t, $rules) {
        $base = ['name' => 'A', 'dob' => '1990-01-01'];
        $t->true(isset(Validator::make(array_merge($base, ['name' => '<script>']), $rules)->errors()['name']));
        $t->true(isset(Validator::make(array_merge($base, ['email' => 'nope']), $rules)->errors()['email']));
        $t->true(isset(Validator::make(array_merge($base, ['phone' => 'abc']), $rules)->errors()['phone']));
        $t->true(isset(Validator::make(array_merge($base, ['qty' => '-1']), $rules)->errors()['qty']));
        $t->true(isset(Validator::make(array_merge($base, ['pct' => '101']), $rules)->errors()['pct']));
        $t->true(isset(Validator::make(array_merge($base, ['amt' => '0']), $rules)->errors()['amt']));
        $t->true(isset(Validator::make(array_merge($base, ['g' => 'C']), $rules)->errors()['g']));
    });
    $t->test('max length counts characters, not bytes', function () use ($t) {
        $t->false(Validator::make(['n' => str_repeat('é', 10)], ['n' => 'max:10'])->fails());
        $t->true(Validator::make(['n' => str_repeat('é', 11)], ['n' => 'max:10'])->fails());
    });
    $t->test('array input where text is expected is rejected', function () use ($t) {
        $t->true(Validator::make(['name' => ['x']], ['name' => 'required'])->fails());
    });
    $t->test('same: rule compares two fields', function () use ($t) {
        $r = ['p' => 'required', 'c' => 'required|same:p'];
        $t->false(Validator::make(['p' => 'abc', 'c' => 'abc'], $r)->fails());
        $t->true(Validator::make(['p' => 'abc', 'c' => 'abd'], $r)->fails());
    });
    $t->test('an unknown rule is a programming error, not silently ignored', function () use ($t) {
        $t->throws(LogicException::class, fn () => Validator::make(['x' => '1'], ['x' => 'bogus']));
    });
};
