<?php

namespace App\Ai;

use App\Entity\Screenshot;
use App\Vision\VisualContext;

interface TagSuggester
{
    public function isAvailable(): bool;

    public function suggest(Screenshot $screenshot, VisualContext $context): TagSuggestion;
}
