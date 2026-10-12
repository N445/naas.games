<?php

namespace App\Twig\Components\Admin;

use App\Entity\Album;
use App\Entity\Screenshot;
use App\Repository\ScreenshotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(route: 'APP_ADMIN_LIVE_COMPONENT')]
final class AlbumScreenshots
{
    use DefaultActionTrait;

    private const int CANDIDATE_LIMIT = 18;

    #[LiveProp]
    public Album $album;

    #[LiveProp(writable: true)]
    public string $search = '';

    public function __construct(
        private readonly ScreenshotRepository $screenshots,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[LiveAction]
    public function useAsCover(#[LiveArg] int $screenshotId): void
    {
        $this->album->useAsCover($this->find($screenshotId));
        $this->entityManager->flush();
    }

    #[LiveAction]
    public function add(#[LiveArg] int $screenshotId): void
    {
        $this->find($screenshotId)->addAlbum($this->album);
        $this->entityManager->flush();
    }

    #[LiveAction]
    public function remove(#[LiveArg] int $screenshotId): void
    {
        $this->find($screenshotId)->removeAlbum($this->album);
        $this->entityManager->flush();
    }

    /**
     * @return list<Screenshot>
     */
    public function getCandidates(): array
    {
        return $this->screenshots->findOutsideAlbum($this->album, trim($this->search), self::CANDIDATE_LIMIT);
    }

    private function find(int $screenshotId): Screenshot
    {
        return $this->screenshots->find($screenshotId) ?? throw new \InvalidArgumentException('Screenshot introuvable.');
    }
}
