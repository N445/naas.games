<?php

namespace App\Gallery;

final readonly class FacetOption
{
    public function __construct(
        private string $value,
        private string $label,
        private int $count,
    ) {
    }

    public function value(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function count(): int
    {
        return $this->count;
    }
}
