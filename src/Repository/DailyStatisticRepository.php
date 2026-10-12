<?php

namespace App\Repository;

use App\Entity\DailyStatistic;
use App\Entity\Screenshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DailyStatistic>
 */
class DailyStatisticRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailyStatistic::class);
    }

    public function addView(Screenshot $screenshot, \DateTimeImmutable $day): void
    {
        $this->add($screenshot, $day, views: 1, downloads: 0);
    }

    public function addDownload(Screenshot $screenshot, \DateTimeImmutable $day): void
    {
        $this->add($screenshot, $day, views: 0, downloads: 1);
    }

    /**
     * @return array<string, array{views: int, downloads: int}>
     */
    public function sumByDay(\DateTimeImmutable $since, ?Screenshot $screenshot = null): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociativeIndexed(
            'SELECT day, SUM(views) AS views, SUM(downloads) AS downloads FROM daily_statistic
             WHERE day >= :since AND (:screenshot IS NULL OR screenshot_id = :screenshot) GROUP BY day',
            ['since' => $since->format('Y-m-d'), 'screenshot' => $screenshot?->getId()],
        );

        return array_map(static fn (array $row): array => array_map(intval(...), $row), $rows);
    }

    private function add(Screenshot $screenshot, \DateTimeImmutable $day, int $views, int $downloads): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO daily_statistic (screenshot_id, day, views, downloads) VALUES (:screenshot, :day, :views, :downloads)
             ON DUPLICATE KEY UPDATE views = views + VALUES(views), downloads = downloads + VALUES(downloads)',
            ['screenshot' => $screenshot->getId(), 'day' => $day->format('Y-m-d'), 'views' => $views, 'downloads' => $downloads],
        );
    }
}
