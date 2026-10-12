<?php

namespace App\Entity;

use App\Repository\DailyStatisticRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DailyStatisticRepository::class, readOnly: true)]
#[ORM\UniqueConstraint(fields: ['screenshot', 'day'])]
#[ORM\Index(fields: ['day'])]
class DailyStatistic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Screenshot::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Screenshot $screenshot;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $day;

    #[ORM\Column]
    private int $views = 0;

    #[ORM\Column]
    private int $downloads = 0;
}
