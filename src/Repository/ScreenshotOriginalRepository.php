<?php

namespace App\Repository;

use App\Entity\ScreenshotOriginal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScreenshotOriginal>
 */
class ScreenshotOriginalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScreenshotOriginal::class);
    }

    public function findOneByHash(string $hash): ?ScreenshotOriginal
    {
        return $this->findOneBy(['file.hash' => $hash]);
    }

    public function findOneByPath(string $path): ?ScreenshotOriginal
    {
        return $this->findOneBy(['file.path' => $path]);
    }

    public function findOneByMatchKey(string $matchKey): ?ScreenshotOriginal
    {
        return $this->findOneBy(['matchKey' => $matchKey], ['id' => 'ASC']);
    }

    /**
     * @return list<ScreenshotOriginal>
     */
    public function searchByName(string $search, int $limit): array
    {
        return $this->createQueryBuilder('original')
            ->where('original.file.originalName LIKE :search')
            ->setParameter('search', '%'.addcslashes($search, '%_').'%')
            ->orderBy('original.takenAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ScreenshotOriginal>
     */
    public function findTakenBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('original')
            ->where('original.takenAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ScreenshotOriginal>
     */
    public function findEmbedded(): array
    {
        return $this->createQueryBuilder('original')
            ->where('original.embedding IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, true> hashes of every stored original, for bulk imports
     */
    public function hashIndex(): array
    {
        $hashes = $this->createQueryBuilder('original')
            ->select('original.file.hash')
            ->where('original.file.hash IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();

        return array_fill_keys($hashes, true);
    }
}
