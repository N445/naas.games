<?php

namespace App\Statistics;

use App\Entity\Screenshot;
use App\Repository\DailyStatisticRepository;
use Psr\Clock\ClockInterface;

final readonly class DailySeries
{
    public function __construct(
        private DailyStatisticRepository $dailyStatistics,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{labels: list<string>, views: list<int>, downloads: list<int>}
     */
    public function lastDays(int $days, ?Screenshot $screenshot = null): array
    {
        $since = $this->clock->now()->modify(sprintf('-%d days', $days - 1))->setTime(0, 0);
        $sums = $this->dailyStatistics->sumByDay($since, $screenshot);
        $series = ['labels' => [], 'views' => [], 'downloads' => []];

        foreach (new \DatePeriod($since, new \DateInterval('P1D'), $days - 1) as $day) {
            $sum = $sums[$day->format('Y-m-d')] ?? ['views' => 0, 'downloads' => 0];
            $series['labels'][] = $day->format('d/m');
            $series['views'][] = $sum['views'];
            $series['downloads'][] = $sum['downloads'];
        }

        return $series;
    }
}
