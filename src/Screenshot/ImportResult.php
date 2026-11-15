<?php

namespace App\Screenshot;

use App\Entity\Screenshot;
use App\Entity\ScreenshotOriginal;

final readonly class ImportResult
{
    /**
     * @param list<Screenshot> $finals      finals an imported original was attached to
     * @param list<Screenshot> $lookalikes  existing screenshots that look the same (probable duplicates)
     */
    private function __construct(
        public ?Screenshot $screenshot,
        public bool $duplicate,
        public ?ScreenshotOriginal $original = null,
        public array $finals = [],
        public array $lookalikes = [],
    ) {
    }

    /**
     * @param list<Screenshot> $lookalikes
     */
    public static function imported(Screenshot $screenshot, array $lookalikes = []): self
    {
        return new self($screenshot, false, $screenshot->getOriginal(), lookalikes: $lookalikes);
    }

    public static function duplicateOf(Screenshot $screenshot): self
    {
        return new self($screenshot, true);
    }

    /**
     * @param list<Screenshot> $finals
     */
    public static function original(ScreenshotOriginal $original, array $finals, bool $duplicate = false): self
    {
        return new self(null, $duplicate, $original, $finals);
    }

    public function isOriginal(): bool
    {
        return null === $this->screenshot && null !== $this->original;
    }
}
