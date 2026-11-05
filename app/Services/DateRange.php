<?php
declare(strict_types=1);

namespace App\Services;

/** A validated from/to date range chosen with the dashboard and report filters. */
final class DateRange
{
    public const MAX_DAYS = 366;

    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $preset,
    ) {
    }

    /** Builds a safe range from query parameters. Invalid input falls back to the last 30 days. */
    public static function fromInput(?string $preset, ?string $from, ?string $to, string $defaultPreset = '30d'): self
    {
        $today = date('Y-m-d');
        $preset = $preset ?: $defaultPreset;

        if ($preset === 'custom' && self::isDate($from) && self::isDate($to) && $from <= $to) {
            $days = (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
            if ($days <= self::MAX_DAYS) {
                return new self($from, $to, 'custom');
            }
        }

        return match ($preset) {
            'today' => new self($today, $today, 'today'),
            '7d'    => new self(date('Y-m-d', strtotime('-6 days')), $today, '7d'),
            'month' => new self(date('Y-m-01'), $today, 'month'),
            '90d'   => new self(date('Y-m-d', strtotime('-89 days')), $today, '90d'),
            default => new self(date('Y-m-d', strtotime('-29 days')), $today, '30d'),
        };
    }

    public function days(): int
    {
        return (int) ((strtotime($this->to) - strtotime($this->from)) / 86400) + 1;
    }

    public function label(): string
    {
        return $this->from === $this->to
            ? format_date($this->from)
            : format_date($this->from) . ' - ' . format_date($this->to);
    }

    /** @return string[] Every date (Y-m-d) in the range. */
    public function dates(): array
    {
        $dates = [];
        for ($t = strtotime($this->from); $t <= strtotime($this->to); $t += 86400) {
            $dates[] = date('Y-m-d', $t);
        }

        return $dates;
    }

    private static function isDate(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value;
    }
}
