<?php

namespace App\Repository;

use App\Entity\Ship;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ship>
 */
class ShipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ship::class);
    }

    public function findOrCreate(int $rsiId): Ship
    {
        return $this->findOneBy(['rsiId' => $rsiId]) ?? new Ship($rsiId);
    }

    /**
     * @return list<Ship>
     */
    public function findEmbedded(): array
    {
        return $this->createQueryBuilder('ship')->where('ship.embedding IS NOT NULL')->getQuery()->getResult();
    }
}
