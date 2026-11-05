<?php
declare(strict_types=1);

namespace Tests\Support;

/**
 * A tiny test runner (PHPUnit is not required, so students can run tests with just PHP).
 * Each test file returns a function that receives this object and registers tests with $t->test('name', fn).
 */
final class Tester
{
    public int $passed = 0;
    /** @var array<int,string> */
    public array $failures = [];
    private string $group = '';
    /** @var callable|null */
    private $beforeEach = null;
    /** @var callable|null */
    private $afterEach = null;

    public function group(string $name, ?callable $beforeEach = null, ?callable $afterEach = null): void
    {
        $this->group = $name;
        $this->beforeEach = $beforeEach;
        $this->afterEach = $afterEach;
        echo "\n\033[1m$name\033[0m\n";
    }

    public function test(string $name, callable $body): void
    {
        $label = $this->group . ' :: ' . $name;
        try {
            if ($this->beforeEach) {
                ($this->beforeEach)();
            }
            $body();
            $this->passed++;
            echo "  \033[32m✓\033[0m $name\n";
        } catch (\Throwable $e) {
            $this->failures[] = $label . "\n      " . get_class($e) . ': ' . $e->getMessage() . "\n      at " . basename($e->getFile()) . ':' . $e->getLine();
            echo "  \033[31m✗ $name\033[0m\n      " . $e->getMessage() . "\n";
        } finally {
            if ($this->afterEach) {
                try {
                    ($this->afterEach)();
                } catch (\Throwable) {
                }
            }
        }
    }

    public function same(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new \RuntimeException(($message !== '' ? $message . ': ' : '') . 'expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true));
        }
    }

    public function true(mixed $value, string $message = 'expected true'): void
    {
        if ($value !== true) {
            throw new \RuntimeException($message . ' (got ' . var_export($value, true) . ')');
        }
    }

    public function false(mixed $value, string $message = 'expected false'): void
    {
        if ($value !== false) {
            throw new \RuntimeException($message . ' (got ' . var_export($value, true) . ')');
        }
    }

    public function contains(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new \RuntimeException(($message !== '' ? $message . ': ' : '') . "expected to find \"$needle\" in output (" . substr(strip_tags($haystack), 0, 160) . '...)');
        }
    }

    public function notContains(string $needle, string $haystack, string $message = ''): void
    {
        if (str_contains($haystack, $needle)) {
            throw new \RuntimeException(($message !== '' ? $message . ': ' : '') . "did not expect to find \"$needle\"");
        }
    }

    /** Asserts that $body throws $class (optionally with a message fragment). */
    public function throws(string $class, callable $body, string $messagePart = ''): void
    {
        try {
            $body();
        } catch (\Throwable $e) {
            if (!($e instanceof $class)) {
                throw new \RuntimeException("expected $class but got " . get_class($e) . ': ' . $e->getMessage());
            }
            if ($messagePart !== '' && !str_contains($e->getMessage(), $messagePart)) {
                throw new \RuntimeException("exception message \"{$e->getMessage()}\" does not contain \"$messagePart\"");
            }

            return;
        }
        throw new \RuntimeException("expected $class to be thrown, but nothing was thrown");
    }
}
