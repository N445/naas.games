<?php

namespace App\Repository;

use App\Entity\Game;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * @return list<Game>
     */
    public function findAllByName(): array
    {
        return $this->findBy([], ['name' => 'ASC']);
    }

    public function findOneBySlug(string $slug): ?Game
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
