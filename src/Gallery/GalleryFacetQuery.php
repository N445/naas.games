<?php

namespace App\Gallery;

use App\Entity\Album;
use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GalleryFacetQuery
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GalleryCriteria $criteria,
        private TagLineage $tagLineage,
    ) {
    }

    public function facets(GalleryFilters $filters): GalleryFacets
    {
        return new GalleryFacets(
            $this->games($filters->without('game')),
            $this->albums($filters->without('album')),
            $this->years($filters->without('year', 'month')),
            $this->colorCounts($filters->without('color')),
            $this->tags($filters->without('tag')),
            $this->labels($filters),
        );
    }

    /**
     * @return list<FacetOption>
     */
    private function games(GalleryFilters $filters): array
    {
        $rows = $this->criteria->select($filters)
            ->select('game.slug AS value', 'game.name AS label', 'COUNT(screenshot.id) AS total')
            ->andWhere('game.id IS NOT NULL')
            ->groupBy('game.id')
            ->addGroupBy('game.slug')
            ->addGroupBy('game.name')
            ->orderBy('game.name')
            ->getQuery()
            ->getArrayResult();

        return array_map(self::option(...), $rows);
    }

    /**
     * @return list<FacetOption>
     */
    private function albums(GalleryFilters $filters): array
    {
        $rows = $this->criteria->select($filters)
            ->select('album.slug AS value', 'album.title AS label', 'COUNT(screenshot.id) AS total')
            ->join('screenshot.albums', 'album', 'WITH', 'album.published = true')
            ->groupBy('album.id')
            ->addGroupBy('album.slug')
            ->addGroupBy('album.title')
            ->orderBy('album.title')
            ->getQuery()
            ->getArrayResult();

        return array_map(self::option(...), $rows);
    }

    /**
     * @return list<YearFacet>
     */
    private function years(GalleryFilters $filters): array
    {
        $rows = $this->criteria->select($filters)
            ->select('SUBSTRING(screenshot.takenAt, 1, 7) AS period', 'COUNT(screenshot.id) AS total')
            ->andWhere('screenshot.takenAt IS NOT NULL')
            ->groupBy('period')
            ->orderBy('period', 'DESC')
            ->getQuery()
            ->getArrayResult();
        $monthCounts = [];
        foreach ($rows as $row) {
            $monthCounts[substr($row['period'], 0, 4)][(int) substr($row['period'], 5, 2)] = (int) $row['total'];
        }

        return array_map(static fn (string $year, array $counts): YearFacet => new YearFacet($year, $counts), array_map(strval(...), array_keys($monthCounts)), $monthCounts);
    }

    /**
     * @return array<string, int>
     */
    private function colorCounts(GalleryFilters $filters): array
    {
        $palettes = $this->criteria->select($filters)
            ->select('screenshot.palette')
            ->andWhere('screenshot.palette IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();
        $buckets = array_merge([], ...array_map(ColorBucket::forStoredPalette(...), $palettes));

        return array_count_values(array_map(static fn (ColorBucket $bucket): string => $bucket->value, $buckets));
    }

    /**
     * @return list<TagFacet>
     */
    private function tags(GalleryFilters $filters): array
    {
        $pairs = $this->criteria->select($filters)
            ->select('screenshot.id AS screenshotId', 'tag.id AS tagId')
            ->join('screenshot.tags', 'tag')
            ->getQuery()
            ->getArrayResult();
        $screenshotIdsByTag = [];
        foreach ($pairs as $pair) {
            $screenshotIdsByTag[(int) $pair['tagId']][] = (int) $pair['screenshotId'];
        }
        $rowsByParent = [];
        foreach ($this->tagLineage->rows() as $row) {
            $rowsByParent[$row['parent_id'] ?? 0][] = $row;
        }

        return array_values(array_filter(array_map(
            static fn (array $root): ?TagFacet => TagFacet::grow($root, $rowsByParent, $screenshotIdsByTag),
            $rowsByParent[0] ?? [],
        )));
    }

    /**
     * @return array<string, string>
     */
    private function labels(GalleryFilters $filters): array
    {
        return array_filter([
            'album' => $this->albumTitle($filters->value('album')),
            'game' => $this->gameName($filters->value('game')),
            'tag' => $this->tagLineage->label($filters->value('tag')),
        ]);
    }

    private function albumTitle(string $slug): ?string
    {
        $album = '' === $slug ? null : $this->entityManager->getRepository(Album::class)->findOneBy(['slug' => $slug, 'published' => true]);

        return $album?->getTitle();
    }

    private function gameName(string $slug): ?string
    {
        $game = '' === $slug ? null : $this->entityManager->getRepository(Game::class)->findOneBy(['slug' => $slug]);

        return $game?->getName();
    }

    /**
     * @param array{value: string, label: string, total: int|string} $row
     */
    private static function option(array $row): FacetOption
    {
        return new FacetOption((string) $row['value'], (string) $row['label'], (int) $row['total']);
    }
}
