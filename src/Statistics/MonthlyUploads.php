<?php

namespace App\Statistics;

use App\Repository\ScreenshotRepository;
use Psr\Clock\ClockInterface;

final readonly class MonthlyUploads
{
    public function __construct(
        private ScreenshotRepository $screenshots,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{labels: list<string>, uploads: list<int>}
     */
    public function lastMonths(int $months): array
    {
        $since = $this->clock->now()->modify(sprintf('first day of -%d months', $months - 1))->setTime(0, 0);
        $counts = $this->screenshots->countUploadsByMonth($since);
        $series = ['labels' => [], 'uploads' => []];

        foreach (new \DatePeriod($since, new \DateInterval('P1M'), $months - 1) as $month) {
            $series['labels'][] = $month->format('m/Y');
            $series['uploads'][] = $counts[$month->format('Y-m')] ?? 0;
        }

        return $series;
    }
}
