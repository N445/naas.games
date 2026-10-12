<?php

namespace App\Tagging;

final readonly class TagBranch
{
    /**
     * @param list<int> $tagIds the selected tag followed by all its descendants
     */
    public function __construct(public array $tagIds)
    {
    }

    public function rootId(): ?int
    {
        return $this->tagIds[0] ?? null;
    }
}
