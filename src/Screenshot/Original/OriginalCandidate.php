<?php

namespace App\Screenshot\Original;

use App\Entity\ScreenshotOriginal;

final readonly class OriginalCandidate
{
    public function __construct(
        public ScreenshotOriginal $original,
        public string $reason,
        public ?float $similarity = null,
    ) {
    }

    public function isNameMatch(): bool
    {
        return 'name' === $this->reason;
    }
}
