<?php

namespace App\Statistics;

use App\Entity\Album;
use App\Entity\Screenshot;
use App\Repository\AlbumRepository;
use App\Repository\DailyStatisticRepository;
use App\Repository\ScreenshotRepository;
use Psr\Clock\ClockInterface;

final readonly class StatisticRecorder
{
    public function __construct(
        private ScreenshotRepository $screenshots,
        private AlbumRepository $albums,
        private DailyStatisticRepository $dailyStatistics,
        private ClockInterface $clock,
    ) {
    }

    public function recordView(Screenshot $screenshot): void
    {
        $this->screenshots->incrementViews($screenshot);
        $this->dailyStatistics->addView($screenshot, $this->clock->now());
    }

    public function recordDownload(Screenshot $screenshot): void
    {
        $this->screenshots->incrementDownloads($screenshot);
        $this->dailyStatistics->addDownload($screenshot, $this->clock->now());
    }

    public function recordAlbumView(Album $album): void
    {
        $this->albums->incrementViews($album);
    }
}
