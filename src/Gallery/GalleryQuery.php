<?php

namespace App\Gallery;

use App\Entity\Screenshot;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GalleryQuery
{
    public const int PAGE_SIZE = 30;
    private const int MAXIMUM_PAGE = 1000;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GalleryCriteria $criteria,
    ) {
    }

    public function page(GalleryFilters $filters, int $number): GalleryPage
    {
        $number = max(1, min($number, self::MAXIMUM_PAGE));
        $sort = $filters->sort();
        $ids = $this->criteria->select($filters)
            ->select('screenshot.id')
            ->orderBy($sort->field(), 'DESC')
            ->addOrderBy('screenshot.id', 'DESC')
            ->setFirstResult(($number - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()
            ->getSingleColumnResult();

        return new GalleryPage($this->hydrate(array_map(intval(...), $ids)), $this->count($filters), $number, self::PAGE_SIZE);
    }

    public function count(GalleryFilters $filters): int
    {
        return (int) $this->criteria->select($filters)
            ->select('COUNT(screenshot.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findPublished(string $slug): ?Screenshot
    {
        $ids = $this->entityManager->createQueryBuilder()
            ->select('screenshot.id')
            ->from(Screenshot::class, 'screenshot')
            ->where('screenshot.slug = :slug')
            ->andWhere('screenshot.published = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getSingleColumnResult();

        return $this->hydrate(array_map(intval(...), $ids))[0] ?? null;
    }

    /**
     * @return list<Screenshot>
     */
    public function similarTo(Screenshot $screenshot, int $limit): array
    {
        if (!$screenshot->hasTags()) {
            return [];
        }
        $ids = $this->entityManager->createQueryBuilder()
            ->select('similar.id', 'COUNT(sharedTag.id) AS HIDDEN sharedTags', 'MAX(similar.takenAt) AS HIDDEN takenAt')
            ->from(Screenshot::class, 'similar')
            ->join('similar.tags', 'sharedTag')
            ->where('similar.published = true')
            ->andWhere('similar.id != :screenshot')
            ->andWhere('sharedTag IN (:tags)')
            ->setParameter('screenshot', $screenshot->getId())
            ->setParameter('tags', $screenshot->getTags())
            ->groupBy('similar.id')
            ->orderBy('sharedTags', 'DESC')
            ->addOrderBy('takenAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();

        return $this->hydrate(array_map(intval(...), $ids));
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Screenshot>
     */
    private function hydrate(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        $screenshots = $this->entityManager->createQueryBuilder()
            ->select('screenshot', 'game', 'tag', 'album')
            ->from(Screenshot::class, 'screenshot')
            ->leftJoin('screenshot.game', 'game')
            ->leftJoin('screenshot.tags', 'tag')
            ->leftJoin('screenshot.albums', 'album')
            ->where('screenshot.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
        $positions = array_flip($ids);
        usort($screenshots, static fn (Screenshot $left, Screenshot $right): int => $positions[$left->getId()] <=> $positions[$right->getId()]);

        return $screenshots;
    }
}
