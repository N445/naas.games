<?php

namespace App\Homepage;

use App\Entity\Album;
use App\Entity\Screenshot;
use App\Repository\AlbumRepository;
use App\Repository\ScreenshotRepository;
use Doctrine\ORM\QueryBuilder;

final readonly class HomepageCatalog
{
    private const int HERO_FALLBACK_LIMIT = 5;
    private const int ALBUM_FALLBACK_LIMIT = 4;

    public function __construct(
        private ScreenshotRepository $screenshots,
        private AlbumRepository $albums,
    ) {
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Screenshot>
     */
    public function heroScreenshots(array $ids): array
    {
        $selected = $this->publishedScreenshotsByIds($ids);
        if ([] !== $selected) {
            return $selected;
        }

        return $this->newestFirst($this->publishedScreenshots()->orderBy('screenshot.viewCount', 'DESC'))
            ->setMaxResults(self::HERO_FALLBACK_LIMIT)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Screenshot>
     */
    public function latestScreenshots(int $limit): array
    {
        return $this->newestFirst($this->publishedScreenshots())
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Screenshot>
     */
    public function publishedScreenshotsByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $screenshots = $this->publishedScreenshots()
            ->andWhere('screenshot.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        return self::orderedLike($ids, $screenshots);
    }

    /**
     * @param list<int> $excludedIds
     *
     * @return list<Screenshot>
     */
    /**
     * @param list<int> $tagIds
     * @param list<int> $excludedIds
     *
     * @return list<Screenshot>
     */
    public function searchPublishedScreenshots(string $search, array $tagIds, array $excludedIds, int $limit): array
    {
        return $this->newestFirst($this->candidates($search, $tagIds, $excludedIds))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $tagIds
     * @param list<int> $excludedIds
     */
    public function countPublishedScreenshots(string $search, array $tagIds, array $excludedIds): int
    {
        return (int) $this->candidates($search, $tagIds, $excludedIds)
            ->select('COUNT(DISTINCT screenshot.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Screenshot>
     */
    public function screenshotsByIds(array $ids): array
    {
        return self::orderedLike($ids, $this->screenshots->findByIds($ids));
    }

    /**
     * @return list<AlbumHighlight>
     */
    public function publishedAlbumHighlights(): array
    {
        return $this->highlight($this->albums->findBy(['published' => true], ['title' => 'ASC']));
    }

    /**
     * @param list<int> $ids
     *
     * @return list<AlbumHighlight>
     */
    public function featuredAlbums(array $ids): array
    {
        $albums = self::orderedLike($ids, $this->publishedAlbums($ids));
        if ([] === $albums) {
            $albums = $this->mostPopulatedAlbums($this->publishedCountsByAlbum());
        }

        return $this->highlight($albums);
    }

    /**
     * @return array<string, int>
     */
    public function statistics(): array
    {
        $totals = $this->publishedScreenshots()
            ->select('COUNT(screenshot.id) AS screenshots')
            ->addSelect('COALESCE(SUM(screenshot.viewCount), 0) AS views')
            ->getQuery()
            ->getSingleResult();

        $statistics = [
            'screenshots' => (int) $totals['screenshots'],
            'albums' => $this->albums->count(['published' => true]),
            'games' => count($this->publishedCountsByGame()),
            'views' => (int) $totals['views'],
        ];

        return array_filter($statistics);
    }

    /**
     * @param list<Album> $albums
     *
     * @return list<AlbumHighlight>
     */
    private function highlight(array $albums): array
    {
        $counts = $this->publishedCountsByAlbum();

        return array_map(static fn (Album $album): AlbumHighlight => new AlbumHighlight($album, $counts[$album->getId()] ?? 0), $albums);
    }

    private function publishedScreenshots(): QueryBuilder
    {
        return $this->screenshots->createQueryBuilder('screenshot')
            ->addSelect('game')
            ->leftJoin('screenshot.game', 'game')
            ->where('screenshot.published = true');
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Album>
     */
    private function publishedAlbums(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->albums->findBy(['id' => $ids, 'published' => true]);
    }

    /**
     * @param array<int, int> $counts
     *
     * @return list<Album>
     */
    private function mostPopulatedAlbums(array $counts): array
    {
        $albums = $this->albums->findBy(['published' => true], ['viewCount' => 'DESC', 'title' => 'ASC']);
        usort($albums, static fn (Album $first, Album $second): int => ($counts[$second->getId()] ?? 0) <=> ($counts[$first->getId()] ?? 0));

        return array_slice($albums, 0, self::ALBUM_FALLBACK_LIMIT);
    }

    /**
     * @return array<int, int>
     */
    private function publishedCountsByAlbum(): array
    {
        $rows = $this->albums->createQueryBuilder('album')
            ->select('album.id AS id', 'COUNT(screenshot.id) AS total')
            ->innerJoin('album.screenshots', 'screenshot')
            ->where('screenshot.published = true')
            ->groupBy('album.id')
            ->getQuery()
            ->getArrayResult();

        return array_map(intval(...), array_column($rows, 'total', 'id'));
    }

    /**
     * @return array<int, int>
     */
    private function publishedCountsByGame(): array
    {
        $rows = $this->screenshots->createQueryBuilder('screenshot')
            ->select('IDENTITY(screenshot.game) AS id', 'COUNT(screenshot.id) AS total')
            ->where('screenshot.published = true')
            ->andWhere('screenshot.game IS NOT NULL')
            ->groupBy('screenshot.game')
            ->getQuery()
            ->getArrayResult();

        return array_map(intval(...), array_column($rows, 'total', 'id'));
    }

    /**
     * @template T of Screenshot|Album
     *
     * @param list<int> $ids
     * @param list<T>   $entities
     *
     * @return list<T>
     */
    private static function orderedLike(array $ids, array $entities): array
    {
        $positions = array_flip($ids);
        usort($entities, static fn (Screenshot|Album $first, Screenshot|Album $second): int => $positions[$first->getId()] <=> $positions[$second->getId()]);

        return $entities;
    }

    private function newestFirst(QueryBuilder $query): QueryBuilder
    {
        return $query
            ->addOrderBy('screenshot.takenAt', 'DESC')
            ->addOrderBy('screenshot.createdAt', 'DESC')
            ->addOrderBy('screenshot.id', 'DESC');
    }

    /**
     * @param list<int> $tagIds
     * @param list<int> $excludedIds
     */
    private function candidates(string $search, array $tagIds, array $excludedIds): QueryBuilder
    {
        $query = $this->publishedScreenshots()
            ->andWhere('screenshot.title LIKE :search OR game.name LIKE :search')
            ->setParameter('search', '%'.addcslashes($search, '%_').'%');
        if ([] !== $tagIds) {
            $query->andWhere(sprintf('screenshot.id IN (SELECT tagged.id FROM %s tagged JOIN tagged.tags tagged_tag WHERE tagged_tag.id IN (:tags))', Screenshot::class))->setParameter('tags', $tagIds);
        }
        if ([] !== $excludedIds) {
            $query->andWhere('screenshot.id NOT IN (:excluded)')->setParameter('excluded', $excludedIds);
        }

        return $query;
    }
}
