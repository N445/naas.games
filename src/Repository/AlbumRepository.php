<?php

namespace App\Repository;

use App\Entity\Album;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Album>
 */
class AlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Album::class);
    }

    /**
     * @return list<Album>
     */
    public function findAllByTitle(): array
    {
        return $this->findBy([], ['title' => 'ASC']);
    }

    /**
     * @return list<Album>
     */
    public function findMostViewed(int $limit): array
    {
        return $this->findBy([], ['viewCount' => 'DESC', 'id' => 'DESC'], $limit);
    }

    public function incrementViews(Album $album): void
    {
        $this->createQueryBuilder('album')
            ->update()
            ->set('album.viewCount', 'album.viewCount + 1')
            ->where('album = :album')
            ->setParameter('album', $album)
            ->getQuery()
            ->execute();
    }
}
