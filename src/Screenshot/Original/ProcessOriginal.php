<?php

namespace App\Screenshot\Original;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final readonly class ProcessOriginal
{
    public function __construct(public int $originalId)
    {
    }
}
