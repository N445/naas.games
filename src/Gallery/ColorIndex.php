<?php

namespace App\Gallery;

use App\Entity\Screenshot;
use Doctrine\ORM\EntityManagerInterface;

final class ColorIndex
{
    /**
     * @var array<string, list<int>>
     */
    private array $screenshotIdsByBucket = [];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return list<int>
     */
    public function screenshotIds(ColorBucket $bucket): array
    {
        return $this->screenshotIdsByBucket[$bucket->value] ??= $this->findScreenshotIds($bucket);
    }

    /**
     * @return list<int>
     */
    private function findScreenshotIds(ColorBucket $bucket): array
    {
        $rows = $this->entityManager->createQueryBuilder()
            ->select('screenshot.id', 'screenshot.palette')
            ->from(Screenshot::class, 'screenshot')
            ->where('screenshot.published = true')
            ->andWhere('screenshot.palette IS NOT NULL')
            ->getQuery()
            ->getArrayResult();
        $matching = array_filter($rows, static fn (array $row): bool => in_array($bucket, ColorBucket::forStoredPalette($row['palette']), true));

        return array_map(intval(...), array_column($matching, 'id'));
    }
}
