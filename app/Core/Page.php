<?php
declare(strict_types=1);

namespace App\Core;

/** One page of a paginated list, plus what the pagination partial needs. */
final class Page
{
    /** @param array<int,array<string,mixed>> $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public function pages(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public static function offset(int $page, int $perPage): int
    {
        return (max(1, $page) - 1) * $perPage;
    }
}
