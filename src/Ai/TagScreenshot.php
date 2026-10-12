<?php

namespace App\Ai;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final class TagScreenshot
{
    // Declared outside the constructor so messages queued before this flag existed still unserialize with a default.
    public bool $explicitlyRequested = false;

    public function __construct(
        public readonly int $screenshotId,
        bool $explicitlyRequested = false,
    ) {
        $this->explicitlyRequested = $explicitlyRequested;
    }
}
