<?php

namespace App\Repository;

use App\Entity\Link;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Link>
 */
class LinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Link::class);
    }

    public function nextPosition(): int
    {
        return 1 + (int) $this->createQueryBuilder('link')
            ->select('MAX(link.position)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Link>
     */
    public function findVisible(): array
    {
        return $this->findBy(['enabled' => true], ['position' => 'ASC']);
    }

    /**
     * @param list<int|string> $ids
     *
     * @return array<int, Link>
     */
    public function findIndexedByIds(array $ids): array
    {
        $links = [];
        foreach ($this->findBy(['id' => $ids]) as $link) {
            $links[(int) $link->getId()] = $link;
        }

        return $links;
    }
}
