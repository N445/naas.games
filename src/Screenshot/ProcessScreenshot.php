<?php

namespace App\Screenshot;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final readonly class ProcessScreenshot
{
    public function __construct(public int $screenshotId)
    {
    }
}
