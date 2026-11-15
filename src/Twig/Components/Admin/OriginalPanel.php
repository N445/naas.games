<?php

namespace App\Twig\Components\Admin;

use App\Entity\Screenshot;
use App\Entity\ScreenshotOriginal;
use App\Repository\ScreenshotOriginalRepository;
use App\Repository\ScreenshotRepository;
use App\Screenshot\Original\OriginalCandidate;
use App\Screenshot\Original\OriginalLibrary;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Original 32:9 of a final, on its admin page: shows the linked one, or suggests candidates to link.
 */
#[AsLiveComponent(route: 'APP_ADMIN_LIVE_COMPONENT')]
final class OriginalPanel
{
    use DefaultActionTrait;

    private const int CANDIDATES = 6;
    private const int SEARCH_RESULTS = 8;

    #[LiveProp]
    public Screenshot $screenshot;

    #[LiveProp(writable: true)]
    public string $search = '';

    public function __construct(
        private readonly OriginalLibrary $library,
        private readonly ScreenshotOriginalRepository $originals,
        private readonly ScreenshotRepository $screenshots,
    ) {
    }

    #[LiveAction]
    public function link(#[LiveArg] int $originalId): void
    {
        $original = $this->originals->find($originalId) ?? throw new \InvalidArgumentException('Original introuvable.');
        $this->library->link($this->screenshot, $original);
        $this->search = '';
    }

    #[LiveAction]
    public function unlink(): void
    {
        $this->library->unlink($this->screenshot);
    }

    /**
     * @return list<OriginalCandidate>
     */
    public function getCandidates(): array
    {
        return $this->screenshot->hasOriginal() ? [] : $this->library->candidatesFor($this->screenshot, self::CANDIDATES);
    }

    /**
     * @return list<ScreenshotOriginal>
     */
    public function getSearchResults(): array
    {
        return mb_strlen(trim($this->search)) < 3 ? [] : $this->originals->searchByName(trim($this->search), self::SEARCH_RESULTS);
    }

    /**
     * Finals that would get this screenshot as original if it were demoted.
     *
     * @return list<Screenshot>
     */
    public function getDemotionTargets(): array
    {
        if (!$this->screenshot->isPanoramic()) {
            return [];
        }

        return array_values(array_filter(
            $this->screenshots->findByMatchKey($this->screenshot->getMatchKey()),
            fn (Screenshot $final): bool => $final !== $this->screenshot && !$final->hasOriginal(),
        ));
    }
}
