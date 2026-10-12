<?php

namespace App\Gallery;

final readonly class YearFacet
{
    /**
     * @param array<int, int> $monthCounts
     */
    public function __construct(
        private string $year,
        private array $monthCounts,
    ) {
    }

    public function year(): string
    {
        return $this->year;
    }

    public function count(): int
    {
        return array_sum($this->monthCounts);
    }

    public function monthCount(int $month): int
    {
        return $this->monthCounts[$month] ?? 0;
    }
}
