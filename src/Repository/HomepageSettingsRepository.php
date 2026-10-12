<?php

namespace App\Repository;

use App\Entity\HomepageSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HomepageSettings>
 */
class HomepageSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HomepageSettings::class);
    }

    public function current(): HomepageSettings
    {
        return $this->findOneBy([], ['id' => 'ASC']) ?? new HomepageSettings();
    }

    public function save(HomepageSettings $settings): void
    {
        $this->getEntityManager()->persist($settings);
        $this->getEntityManager()->flush();
    }
}
