<?php

namespace App\Ai\Provider;

use App\Ai\TagSuggester;
use App\Ai\TagSuggestion;
use App\Entity\Screenshot;
use App\Vision\TagVote;
use App\Vision\VisionClient;
use App\Vision\VisualContext;

final readonly class LocalTagSuggester implements TagSuggester
{
    private const int MIN_VOTING_IMAGES = 2;

    public function __construct(private VisionClient $vision)
    {
    }

    public function isAvailable(): bool
    {
        return $this->vision->isAvailable();
    }

    public function suggest(Screenshot $screenshot, VisualContext $context): TagSuggestion
    {
        $agreed = array_filter($context->tagVotes(), static fn (TagVote $vote): bool => $vote->images >= self::MIN_VOTING_IMAGES);

        return TagSuggestion::of(null, array_values(array_map(static fn (TagVote $vote): string => $vote->name, $agreed)));
    }
}
