<?php

namespace App\Repository;

use App\Entity\Album;
use App\Entity\Screenshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Screenshot>
 */
class ScreenshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Screenshot::class);
    }

    /**
     * @return array{screenshots: int, views: int, downloads: int, storage: int, unsorted: int}
     */
    public function summarize(): array
    {
        $totals = $this->createQueryBuilder('screenshot')
            ->select('COUNT(screenshot.id) AS screenshots')
            ->addSelect('COALESCE(SUM(screenshot.viewCount), 0) AS views')
            ->addSelect('COALESCE(SUM(screenshot.downloadCount), 0) AS downloads')
            ->addSelect('COALESCE(SUM(screenshot.file.size), 0) AS storage')
            ->getQuery()
            ->getSingleResult();

        return array_map(intval(...), $totals) + ['unsorted' => $this->countUnsorted()];
    }

    public function findOneByHash(string $hash): ?Screenshot
    {
        return $this->findOneBy(['file.hash' => $hash]);
    }

    public function findRandomPublished(): ?Screenshot
    {
        $ids = $this->createQueryBuilder('screenshot')
            ->select('screenshot.id')
            ->where('screenshot.published = true')
            ->getQuery()
            ->getSingleColumnResult();

        return [] === $ids ? null : $this->find($ids[array_rand($ids)]);
    }

    /**
     * @return list<Screenshot>
     */
    public function findMostViewed(int $limit): array
    {
        return $this->findBy([], ['viewCount' => 'DESC', 'id' => 'DESC'], $limit);
    }

    /**
     * @return list<Screenshot>
     */
    public function findMostDownloaded(int $limit): array
    {
        return $this->findBy([], ['downloadCount' => 'DESC', 'id' => 'DESC'], $limit);
    }

    /**
     * @return list<Screenshot>
     */
    public function findLatest(int $limit): array
    {
        return $this->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC'], $limit);
    }

    /**
     * @return array<string, int>
     */
    public function countUploadsByMonth(\DateTimeImmutable $since): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllKeyValue(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) FROM screenshot WHERE created_at >= :since GROUP BY month",
            ['since' => $since->format('Y-m-d')],
        );

        return array_map(intval(...), $rows);
    }

    /**
     * @return list<Screenshot>
     */
    public function findOutsideAlbum(Album $album, string $search, int $limit): array
    {
        return $this->createQueryBuilder('screenshot')
            ->where(':album NOT MEMBER OF screenshot.albums')
            ->andWhere('screenshot.title LIKE :search')
            ->setParameter('album', $album)
            ->setParameter('search', '%'.addcslashes($search, '%_').'%')
            ->orderBy('screenshot.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Screenshot>
     */
    /**
     * @return list<Screenshot>
     */
    public function findInAlbum(Album $album, int $limit): array
    {
        return $this->createQueryBuilder('screenshot')
            ->innerJoin('screenshot.albums', 'album', 'WITH', 'album = :album')
            ->setParameter('album', $album)
            ->orderBy('screenshot.takenAt', 'DESC')
            ->addOrderBy('screenshot.createdAt', 'DESC')
            ->addOrderBy('screenshot.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findReviewedOthers(Screenshot $screenshot): array
    {
        $candidates = $this->createQueryBuilder('screenshot')
            ->where('screenshot.embedding IS NOT NULL')
            ->andWhere('screenshot != :screenshot')
            ->setParameter('screenshot', $screenshot)
            ->getQuery()
            ->getResult();

        return array_values(array_filter($candidates, static fn (Screenshot $candidate): bool => $candidate->hasReviewedTags()));
    }

    /**
     * @param list<int|string> $ids
     *
     * @return list<Screenshot>
     */
    public function findByIds(array $ids): array
    {
        return $this->findBy(['id' => $ids]);
    }

    public function incrementViews(Screenshot $screenshot): void
    {
        $this->increment($screenshot, 'viewCount');
    }

    public function incrementDownloads(Screenshot $screenshot): void
    {
        $this->increment($screenshot, 'downloadCount');
    }

    private function increment(Screenshot $screenshot, string $counter): void
    {
        $this->createQueryBuilder('screenshot')
            ->update()
            ->set("screenshot.$counter", "screenshot.$counter + 1")
            ->where('screenshot = :screenshot')
            ->setParameter('screenshot', $screenshot)
            ->getQuery()
            ->execute();
    }

    private function countUnsorted(): int
    {
        return (int) $this->createQueryBuilder('screenshot')
            ->select('COUNT(screenshot.id)')
            ->where('screenshot.albums IS EMPTY')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
