<?php

namespace App\Screenshot\Duplicate;

use App\Entity\Screenshot;

final readonly class DuplicateGroup
{
    /**
     * @param list<Screenshot> $screenshots oldest first
     */
    public function __construct(
        public array $screenshots,
        public bool $identical,
        public int $distance,
    ) {
    }

    public function key(): string
    {
        return implode('-', array_map(static fn (Screenshot $screenshot): int => (int) $screenshot->getId(), $this->screenshots));
    }

    /**
     * The best candidate to keep: the most viewed, then the heaviest (least compressed) file.
     */
    public function suggestedKeeper(): Screenshot
    {
        $screenshots = $this->screenshots;
        usort($screenshots, static fn (Screenshot $left, Screenshot $right): int => [$right->getViewCount() + $right->getDownloadCount(), $right->getFile()->getSize()]
            <=> [$left->getViewCount() + $left->getDownloadCount(), $left->getFile()->getSize()]);

        return $screenshots[0];
    }
}
