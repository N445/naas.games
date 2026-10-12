<?php

namespace App\Homepage;

use App\Entity\Album;
use App\Entity\Screenshot;

final readonly class AlbumHighlight
{
    public function __construct(
        private Album $album,
        private int $screenshotCount,
    ) {
    }

    public function album(): Album
    {
        return $this->album;
    }

    public function screenshotCount(): int
    {
        return $this->screenshotCount;
    }

    public function cover(): ?Screenshot
    {
        $cover = $this->album->getCover();
        if (null !== $cover && $cover->isPublished()) {
            return $cover;
        }

        return $this->album->getScreenshots()->findFirst(static fn (int $key, Screenshot $screenshot): bool => $screenshot->isPublished());
    }
}
