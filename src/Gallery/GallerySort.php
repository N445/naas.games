<?php

namespace App\Gallery;

enum GallerySort: string
{
    case LatestTaken = 'taken';
    case LatestUploaded = 'uploaded';
    case MostViewed = 'views';
    case MostDownloaded = 'downloads';

    public function field(): string
    {
        return match ($this) {
            self::LatestTaken => 'screenshot.takenAt',
            self::LatestUploaded => 'screenshot.createdAt',
            self::MostViewed => 'screenshot.viewCount',
            self::MostDownloaded => 'screenshot.downloadCount',
        };
    }

    public function isDefault(): bool
    {
        return self::LatestTaken === $this;
    }
}
