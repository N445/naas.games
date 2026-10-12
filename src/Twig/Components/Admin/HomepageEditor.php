<?php

namespace App\Twig\Components\Admin;

use App\Entity\HomepageSettings;
use App\Entity\Screenshot;
use App\Homepage\AlbumHighlight;
use App\Homepage\HomepageCatalog;
use App\Repository\TagRepository;
use App\Tagging\TagTree;
use App\Repository\HomepageSettingsRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent(route: 'APP_ADMIN_LIVE_COMPONENT')]
final class HomepageEditor
{
    use DefaultActionTrait;

    private const int CANDIDATE_PAGE = 24;

    #[LiveProp(writable: true)]
    public string $heroTitleEn = '';

    #[LiveProp(writable: true)]
    public string $heroTitleFr = '';

    #[LiveProp(writable: true)]
    public string $heroSubtitleEn = '';

    #[LiveProp(writable: true)]
    public string $heroSubtitleFr = '';

    /**
     * @var list<int>
     */
    #[LiveProp]
    public array $heroScreenshotIds = [];

    /**
     * @var list<int>
     */
    #[LiveProp]
    public array $featuredAlbumIds = [];

    #[LiveProp(writable: true)]
    public bool $showStatistics = true;

    #[LiveProp(writable: true)]
    public bool $showLatest = true;

    #[LiveProp(writable: true)]
    public int $latestLimit = HomepageSettings::DEFAULT_LATEST_LIMIT;

    #[LiveProp(writable: true)]
    public bool $showAlbums = true;

    #[LiveProp(writable: true, onUpdated: 'resetCandidates')]
    public string $search = '';

    #[LiveProp(writable: true, onUpdated: 'resetCandidates')]
    public string $tagId = '';

    #[LiveProp]
    public int $candidateLimit = self::CANDIDATE_PAGE;

    #[LiveProp(writable: true)]
    public string $previewLocale = HomepageSettings::DEFAULT_LOCALE;

    public ?string $notice = null;

    public function __construct(
        private readonly HomepageSettingsRepository $settings,
        private readonly HomepageCatalog $catalog,
        private readonly TagRepository $tags,
    ) {
    }

    #[PostMount]
    public function load(): void
    {
        $settings = $this->settings->current();
        $this->heroTitleEn = $settings->getHeroTitles()['en'] ?? '';
        $this->heroTitleFr = $settings->getHeroTitles()['fr'] ?? '';
        $this->heroSubtitleEn = $settings->getHeroSubtitles()['en'] ?? '';
        $this->heroSubtitleFr = $settings->getHeroSubtitles()['fr'] ?? '';
        $this->heroScreenshotIds = $settings->getHeroScreenshotIds();
        $this->featuredAlbumIds = $settings->getFeaturedAlbumIds();
        $this->showStatistics = $settings->isShowStatistics();
        $this->showLatest = $settings->isShowLatest();
        $this->latestLimit = $settings->getLatestLimit();
        $this->showAlbums = $settings->isShowAlbums();
    }

    #[LiveAction]
    public function addScreenshot(#[LiveArg] int $screenshotId): void
    {
        $this->heroScreenshotIds = array_values(array_unique([...$this->heroScreenshotIds, $screenshotId]));
    }

    #[LiveAction]
    public function removeScreenshot(#[LiveArg] int $screenshotId): void
    {
        $this->heroScreenshotIds = self::without($this->heroScreenshotIds, $screenshotId);
    }

    #[LiveAction]
    public function moveScreenshot(#[LiveArg] int $screenshotId, #[LiveArg] int $offset): void
    {
        $this->heroScreenshotIds = self::moved($this->heroScreenshotIds, $screenshotId, $offset);
    }

    #[LiveAction]
    public function sortScreenshots(#[LiveArg] string $ids): void
    {
        $this->heroScreenshotIds = self::sorted($this->heroScreenshotIds, $ids);
    }

    #[LiveAction]
    public function clearScreenshots(): void
    {
        $this->heroScreenshotIds = [];
    }

    #[LiveAction]
    public function toggleAlbum(#[LiveArg] int $albumId): void
    {
        $this->featuredAlbumIds = in_array($albumId, $this->featuredAlbumIds, true)
            ? self::without($this->featuredAlbumIds, $albumId)
            : [...$this->featuredAlbumIds, $albumId];
    }

    #[LiveAction]
    public function moveAlbum(#[LiveArg] int $albumId, #[LiveArg] int $offset): void
    {
        $this->featuredAlbumIds = self::moved($this->featuredAlbumIds, $albumId, $offset);
    }

    #[LiveAction]
    public function sortAlbums(#[LiveArg] string $ids): void
    {
        $this->featuredAlbumIds = self::sorted($this->featuredAlbumIds, $ids);
    }

    #[LiveAction]
    public function save(): void
    {
        $settings = $this->settings->current();
        $this->applyTo($settings);
        $this->settings->save($settings);
        $this->load();
        $this->notice = 'Page d’accueil enregistrée.';
    }

    #[LiveAction]
    public function discard(): void
    {
        $this->load();
        $this->notice = 'Modifications annulées.';
    }

    public function hasUnsavedChanges(): bool
    {
        $draft = new HomepageSettings();
        $this->applyTo($draft);

        return self::fingerprint($draft) !== self::fingerprint($this->settings->current());
    }

    public function rankOf(int $albumId): ?int
    {
        $position = array_search($albumId, $this->featuredAlbumIds, true);

        return false === $position ? null : $position + 1;
    }

    public function previewTitle(): ?string
    {
        return $this->preview(['en' => $this->heroTitleEn, 'fr' => $this->heroTitleFr]);
    }

    public function previewSubtitle(): ?string
    {
        return $this->preview(['en' => $this->heroSubtitleEn, 'fr' => $this->heroSubtitleFr]);
    }

    public function getStoredSettings(): HomepageSettings
    {
        return $this->settings->current();
    }

    /**
     * @return list<Screenshot>
     */
    public function getHeroScreenshots(): array
    {
        return $this->catalog->screenshotsByIds($this->heroScreenshotIds);
    }

    /**
     * @return list<Screenshot>
     */
    public function getFallbackScreenshots(): array
    {
        return $this->catalog->heroScreenshots([]);
    }

    public function resetCandidates(): void
    {
        $this->candidateLimit = self::CANDIDATE_PAGE;
    }

    #[LiveAction]
    public function showMoreCandidates(): void
    {
        $this->candidateLimit += self::CANDIDATE_PAGE;
    }

    /**
     * @return list<Screenshot>
     */
    public function getCandidates(): array
    {
        return $this->catalog->searchPublishedScreenshots(trim($this->search), $this->selectedTagIds(), $this->heroScreenshotIds, $this->candidateLimit);
    }

    public function getCandidateCount(): int
    {
        return $this->catalog->countPublishedScreenshots(trim($this->search), $this->selectedTagIds(), $this->heroScreenshotIds);
    }

    /**
     * @return array<string, int>
     */
    public function getTagChoices(): array
    {
        return TagTree::fromRows($this->tags->findTreeRows())->labelledChoices();
    }

    /**
     * @return list<int>
     */
    private function selectedTagIds(): array
    {
        if (!ctype_digit($this->tagId)) {
            return [];
        }

        return TagTree::fromRows($this->tags->findTreeRows())->subtreeIds((int) $this->tagId) ?: [(int) $this->tagId];
    }

    /**
     * @return list<AlbumHighlight>
     */
    public function getAlbums(): array
    {
        return $this->catalog->publishedAlbumHighlights();
    }

    /**
     * @return list<AlbumHighlight>
     */
    public function getFeaturedAlbums(): array
    {
        $albums = [];
        foreach ($this->getAlbums() as $highlight) {
            $albums[$highlight->album()->getId()] = $highlight;
        }

        return array_values(array_filter(array_map(static fn (int $id): ?AlbumHighlight => $albums[$id] ?? null, $this->featuredAlbumIds)));
    }

    /**
     * @param array<string, string> $values
     */
    private function preview(array $values): ?string
    {
        $draft = (new HomepageSettings())->setHeroTitles($values);

        return $draft->heroTitle($this->previewLocale);
    }

    private function applyTo(HomepageSettings $settings): void
    {
        $settings
            ->setHeroTitles(['en' => $this->heroTitleEn, 'fr' => $this->heroTitleFr])
            ->setHeroSubtitles(['en' => $this->heroSubtitleEn, 'fr' => $this->heroSubtitleFr])
            ->setHeroScreenshotIds($this->heroScreenshotIds)
            ->setFeaturedAlbumIds($this->featuredAlbumIds)
            ->setShowStatistics($this->showStatistics)
            ->setShowLatest($this->showLatest)
            ->setLatestLimit($this->latestLimit)
            ->setShowAlbums($this->showAlbums);
    }

    /**
     * @return list<mixed>
     */
    private static function fingerprint(HomepageSettings $settings): array
    {
        return [
            $settings->getHeroTitles(),
            $settings->getHeroSubtitles(),
            $settings->getHeroScreenshotIds(),
            $settings->getFeaturedAlbumIds(),
            $settings->isShowStatistics(),
            $settings->isShowLatest(),
            $settings->getLatestLimit(),
            $settings->isShowAlbums(),
        ];
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private static function without(array $ids, int $removed): array
    {
        return array_values(array_diff($ids, [$removed]));
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private static function moved(array $ids, int $id, int $offset): array
    {
        $from = array_search($id, $ids, true);
        if (false === $from) {
            return $ids;
        }

        $to = max(0, min(count($ids) - 1, $from + $offset));
        array_splice($ids, $from, 1);
        array_splice($ids, $to, 0, [$id]);

        return $ids;
    }

    /**
     * @param list<int> $current
     *
     * @return list<int>
     */
    private static function sorted(array $current, string $ids): array
    {
        $requested = array_map(intval(...), array_filter(explode(',', $ids), is_numeric(...)));

        return [...array_values(array_intersect($requested, $current)), ...array_values(array_diff($current, $requested))];
    }
}
