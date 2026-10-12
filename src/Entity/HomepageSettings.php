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
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $heroTitles = [];

    /**
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $heroSubtitles = [];

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

    public function heroTitle(string $locale): ?string
    {
        return self::translated($this->heroTitles, $locale);
    }

    public function heroSubtitle(string $locale): ?string
    {
        return self::translated($this->heroSubtitles, $locale);
    }

    /**
     * @return array<string, string>
     */
    public function getHeroTitles(): array
    {
        return $this->heroTitles;
    }

    /**
     * @param array<string, string> $heroTitles
     */
    public function setHeroTitles(array $heroTitles): static
    {
        $this->heroTitles = self::withoutBlanks($heroTitles);

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getHeroSubtitles(): array
    {
        return $this->heroSubtitles;
    }

    /**
     * @param array<string, string> $heroSubtitles
     */
    public function setHeroSubtitles(array $heroSubtitles): static
    {
        $this->heroSubtitles = self::withoutBlanks($heroSubtitles);

        return $this;
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



    /**
     * @param array<string, string> $values
     */
    private static function translated(array $values, string $locale): ?string
    {
        return $values[$locale] ?? $values[self::DEFAULT_LOCALE] ?? null;
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, string>
     */
    private static function withoutBlanks(array $values): array
    {
        return array_filter(array_map(trim(...), $values), static fn (string $value): bool => '' !== $value);
    }
}
