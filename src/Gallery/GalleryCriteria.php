<?php

namespace App\Gallery;

use App\Entity\Album;
use App\Entity\Screenshot;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class GalleryCriteria
{
    private const string NOTHING = '1 = 0';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TagLineage $tagLineage,
        private ColorIndex $colorIndex,
    ) {
    }

    public function select(GalleryFilters $filters): QueryBuilder
    {
        $builder = $this->entityManager->createQueryBuilder()
            ->from(Screenshot::class, 'screenshot')
            ->leftJoin('screenshot.game', 'game')
            ->where('screenshot.published = true');

        $this->matchSearch($builder, $filters->searchTerms());
        $this->matchAlbum($builder, $filters->value('album'));
        $this->matchTag($builder, $filters->value('tag'));
        $this->matchGame($builder, $filters->value('game'));
        $this->matchPeriod($builder, $filters->period());
        $this->matchColor($builder, $filters->color());

        return $builder;
    }

    /**
     * @param list<string> $terms
     */
    private function matchSearch(QueryBuilder $builder, array $terms): void
    {
        foreach ($terms as $index => $term) {
            $this->matchTerm($builder, $term, $index);
        }
    }

    private function matchTerm(QueryBuilder $builder, string $term, int $index): void
    {
        $parameter = "term$index";
        $tagged = sprintf('SELECT termTagged%1$d.id FROM %2$s termTag%1$d JOIN termTag%1$d.screenshots termTagged%1$d WHERE termTag%1$d.name LIKE :%3$s', $index, Tag::class, $parameter);
        $filed = sprintf('SELECT termFiled%1$d.id FROM %2$s termAlbum%1$d JOIN termAlbum%1$d.screenshots termFiled%1$d WHERE termAlbum%1$d.published = true AND termAlbum%1$d.title LIKE :%3$s', $index, Album::class, $parameter);

        $builder
            ->andWhere(sprintf('screenshot.title LIKE :%1$s OR screenshot.description LIKE :%1$s OR game.name LIKE :%1$s OR screenshot.id IN (%2$s) OR screenshot.id IN (%3$s)', $parameter, $tagged, $filed))
            ->setParameter($parameter, '%'.addcslashes($term, '%_\\').'%');
    }

    private function matchAlbum(QueryBuilder $builder, string $slug): void
    {
        if ('' === $slug) {
            return;
        }

        $builder
            ->andWhere(sprintf('screenshot.id IN (SELECT filedScreenshot.id FROM %s filterAlbum JOIN filterAlbum.screenshots filedScreenshot WHERE filterAlbum.slug = :album AND filterAlbum.published = true)', Album::class))
            ->setParameter('album', $slug);
    }

    private function matchTag(QueryBuilder $builder, string $reference): void
    {
        if ('' === $reference) {
            return;
        }
        $tagIds = $this->tagLineage->branchIds($reference);
        if ([] === $tagIds) {
            $builder->andWhere(self::NOTHING);

            return;
        }

        $builder
            ->andWhere(sprintf('screenshot.id IN (SELECT taggedScreenshot.id FROM %s filterTag JOIN filterTag.screenshots taggedScreenshot WHERE filterTag.id IN (:tagIds))', Tag::class))
            ->setParameter('tagIds', $tagIds);
    }

    private function matchGame(QueryBuilder $builder, string $slug): void
    {
        if ('' === $slug) {
            return;
        }

        $builder->andWhere('game.slug = :game')->setParameter('game', $slug);
    }

    private function matchPeriod(QueryBuilder $builder, TakenPeriod $period): void
    {
        if (null !== $period->start()) {
            $builder->andWhere('screenshot.takenAt >= :takenFrom')->setParameter('takenFrom', $period->start());
        }
        if (null !== $period->end()) {
            $builder->andWhere('screenshot.takenAt < :takenBefore')->setParameter('takenBefore', $period->end());
        }
    }

    private function matchColor(QueryBuilder $builder, ?ColorBucket $color): void
    {
        if (null === $color) {
            return;
        }
        $screenshotIds = $this->colorIndex->screenshotIds($color);
        if ([] === $screenshotIds) {
            $builder->andWhere(self::NOTHING);

            return;
        }

        $builder->andWhere('screenshot.id IN (:colorIds)')->setParameter('colorIds', $screenshotIds);
    }
}
