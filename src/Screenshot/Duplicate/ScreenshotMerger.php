<?php

namespace App\Screenshot\Duplicate;

use App\Entity\Screenshot;
use App\Repository\HomepageSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ScreenshotMerger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HomepageSettingsRepository $homepage,
    ) {
    }

    /**
     * Keeps one screenshot and deletes the others, after moving their tags, albums, statistics
     * (totals and daily history) and homepage slots onto it.
     *
     * @param list<Screenshot> $duplicates
     */
    public function merge(Screenshot $keeper, array $duplicates): int
    {
        $duplicates = array_values(array_filter($duplicates, static fn (Screenshot $duplicate): bool => $duplicate !== $keeper));
        if ([] === $duplicates) {
            return 0;
        }

        $this->entityManager->wrapInTransaction(function () use ($keeper, $duplicates): void {
            $settings = $this->homepage->current();
            $heroIds = $settings->getHeroScreenshotIds();
            foreach ($duplicates as $duplicate) {
                $this->moveDailyStatistics($duplicate, $keeper);
                $keeper->absorb($duplicate);
                $heroIds = array_map(static fn (int $id): int => $id === $duplicate->getId() ? (int) $keeper->getId() : $id, $heroIds);
                $this->entityManager->remove($duplicate);
            }
            $settings->setHeroScreenshotIds(array_values(array_unique($heroIds)));
            $this->entityManager->flush();
        });

        return count($duplicates);
    }

    /**
     * @param list<Screenshot> $screenshots
     */
    public function dismiss(array $screenshots): void
    {
        foreach ($screenshots as $screenshot) {
            foreach ($screenshots as $other) {
                if ($other !== $screenshot) {
                    $screenshot->markDistinctFrom($other);
                }
            }
        }
        $this->entityManager->flush();
    }

    private function moveDailyStatistics(Screenshot $from, Screenshot $to): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'INSERT INTO daily_statistic (screenshot_id, day, views, downloads)
             SELECT :to, source.day, source.views, source.downloads FROM daily_statistic source WHERE source.screenshot_id = :from
             ON DUPLICATE KEY UPDATE views = daily_statistic.views + VALUES(views), downloads = daily_statistic.downloads + VALUES(downloads)',
            ['to' => $to->getId(), 'from' => $from->getId()],
        );
    }
}
