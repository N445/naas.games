<?php

namespace App\Screenshot;

use App\Entity\Screenshot;

final readonly class ImportResult
{
    private function __construct(
        public Screenshot $screenshot,
        public bool $duplicate,
    ) {
    }

    public static function imported(Screenshot $screenshot): self
    {
        return new self($screenshot, false);
    }

    public static function duplicateOf(Screenshot $screenshot): self
    {
        return new self($screenshot, true);
    }
}
