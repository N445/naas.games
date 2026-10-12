<?php

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function findOrCreatePath(string $path): Tag
    {
        $tag = null;
        foreach (self::segments($path) as $name) {
            $tag = $this->findOrCreateChild($tag, $name);
        }

        return $tag ?? throw new \InvalidArgumentException('Chemin de tag vide.');
    }

    /**
     * @return list<string>
     */
    public function findMostUsedPaths(int $limit): array
    {
        $tags = $this->createQueryBuilder('tag')
            ->leftJoin('tag.screenshots', 'screenshot')
            ->groupBy('tag.id')
            ->orderBy('COUNT(screenshot.id)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(static fn (Tag $tag): string => $tag->getPath(), $tags);
    }

    /**
     * @return list<array{id: int, parent_id: ?int, name: string, screenshots: int}>
     */
    public function findTreeRows(): array
    {
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT tag.id, tag.parent_id, tag.name, COUNT(screenshot_tag.screenshot_id) AS screenshots
             FROM tag LEFT JOIN screenshot_tag ON screenshot_tag.tag_id = tag.id
             GROUP BY tag.id ORDER BY tag.name',
        );
    }

    /**
     * @return list<string>
     */
    public static function segments(string $path): array
    {
        return array_values(array_filter(array_map(trim(...), explode(Tag::PATH_SEPARATOR, $path))));
    }

    private function findOrCreateChild(?Tag $parent, string $name): Tag
    {
        $existing = $this->findPending($parent, $name) ?? $this->findPersisted($parent, $name);
        if (null !== $existing) {
            return $existing;
        }

        $tag = (new Tag())->setName($name)->setParent($parent);
        $this->getEntityManager()->persist($tag);

        return $tag;
    }

    private function findPersisted(?Tag $parent, string $name): ?Tag
    {
        if (null !== $parent && null === $parent->getId()) {
            return null;
        }

        return $this->findOneBy(['parent' => $parent, 'name' => $name]);
    }

    private function findPending(?Tag $parent, string $name): ?Tag
    {
        foreach ($this->getEntityManager()->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Tag && $entity->getParent() === $parent && 0 === strcasecmp((string) $entity->getName(), $name)) {
                return $entity;
            }
        }

        return null;
    }
}
