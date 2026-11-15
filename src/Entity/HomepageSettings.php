<?php

namespace App\Entity;

use App\Repository\HomepageSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Blameable\Traits\BlameableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: HomepageSettingsRepository::class)]
class HomepageSettings
{
    use TimestampableEntity;
    use BlameableEntity;

    public const string DEFAULT_LOCALE = 'en';
    public const int DEFAULT_LATEST_LIMIT = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var list<int>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $heroScreenshotIds = [];

    /**
     * @var list<int>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $featuredAlbumIds = [];

    #[ORM\Column]
    private bool $showStatistics = true;

    #[ORM\Column]
    private bool $showLatest = true;

    #[ORM\Column]
    private int $latestLimit = self::DEFAULT_LATEST_LIMIT;

    #[ORM\Column]
    private bool $showAlbums = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return list<int>
     */
    public function getHeroScreenshotIds(): array
    {
        return $this->heroScreenshotIds;
    }

    /**
     * @param list<int> $heroScreenshotIds
     */
    public function setHeroScreenshotIds(array $heroScreenshotIds): static
    {
        $this->heroScreenshotIds = array_values(array_unique(array_map(intval(...), $heroScreenshotIds)));

        return $this;
    }

    /**
     * @return list<int>
     */
    public function getFeaturedAlbumIds(): array
    {
        return $this->featuredAlbumIds;
    }

    /**
     * @param list<int> $featuredAlbumIds
     */
    public function setFeaturedAlbumIds(array $featuredAlbumIds): static
    {
        $this->featuredAlbumIds = array_values(array_unique(array_map(intval(...), $featuredAlbumIds)));

        return $this;
    }

    public function isShowStatistics(): bool
    {
        return $this->showStatistics;
    }

    public function setShowStatistics(bool $showStatistics): static
    {
        $this->showStatistics = $showStatistics;

        return $this;
    }

    public function isShowLatest(): bool
    {
        return $this->showLatest;
    }

    public function setShowLatest(bool $showLatest): static
    {
        $this->showLatest = $showLatest;

        return $this;
    }

    public function getLatestLimit(): int
    {
        return $this->latestLimit;
    }

    public function setLatestLimit(int $latestLimit): static
    {
        $this->latestLimit = max(1, min(30, $latestLimit));

        return $this;
    }

    public function isShowAlbums(): bool
    {
        return $this->showAlbums;
    }

    public function setShowAlbums(bool $showAlbums): static
    {
        $this->showAlbums = $showAlbums;

        return $this;
    }
}
