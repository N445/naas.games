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
    private const int PAGE_SIZE = 48;

    #[LiveProp]
    public Album $album;

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp]
    public int $limit = self::PAGE_SIZE;

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

    #[LiveAction]
    public function delete(#[LiveArg] int $screenshotId): void
    {
        $this->entityManager->remove($this->find($screenshotId));
        $this->entityManager->flush();
        $this->entityManager->refresh($this->album);
    }

    #[LiveAction]
    public function showMore(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    /**
     * @return list<Screenshot>
     */
    public function getScreenshots(): array
    {
        return $this->screenshots->findInAlbum($this->album, $this->limit);
    }

    public function getRemaining(): int
    {
        return max(0, $this->album->countScreenshots() - $this->limit);
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
