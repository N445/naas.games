<?php

namespace App\Twig\Components\Admin;

use App\Ai\TagScreenshot;
use App\Ai\TagSuggester;
use App\Entity\Album;
use App\Entity\Game;
use App\Entity\Screenshot;
use App\Repository\AlbumRepository;
use App\Repository\GameRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\ValidatableComponentTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsLiveComponent(route: 'APP_ADMIN_LIVE_COMPONENT')]
final class ScreenshotQuickEdit
{
    use DefaultActionTrait;
    use ValidatableComponentTrait;

    #[LiveProp(writable: ['title', 'published'])]
    #[Assert\Valid]
    public Screenshot $screenshot;

    #[LiveProp(writable: true)]
    public string $newTag = '';

    #[LiveProp(writable: true, onUpdated: 'changeGame')]
    public string $gameId = '';

    /**
     * @var list<string>
     */
    #[LiveProp(writable: true, onUpdated: 'changeAlbums')]
    public array $albumIds = [];

    public bool $saved = false;

    public function __construct(
        private readonly AlbumRepository $albums,
        private readonly GameRepository $games,
        private readonly MessageBusInterface $bus,
        private readonly TagRepository $tags,
        private readonly EntityManagerInterface $entityManager,
        private readonly TagSuggester $tagSuggester,
        private readonly ClockInterface $clock,
    ) {
    }

    #[PostMount]
    public function initializeSelections(): void
    {
        $this->gameId = (string) $this->screenshot->getGame()?->getId();
        $this->albumIds = array_values($this->screenshot->getAlbums()->map(static fn (Album $album): string => (string) $album->getId())->toArray());
    }

    public function changeAlbums(): void
    {
        foreach ($this->albums->findAllByTitle() as $album) {
            $this->syncAlbum($album, in_array((string) $album->getId(), $this->albumIds, true));
        }
        $this->save();
    }

    public function changeGame(): void
    {
        $this->screenshot->setGame(ctype_digit($this->gameId) ? $this->games->find((int) $this->gameId) : null);
        $this->save();
    }

    #[LiveAction]
    public function retag(): void
    {
        $this->screenshot->requestAiTagging($this->clock->now());
        $this->save();
        $this->bus->dispatch(new TagScreenshot((int) $this->screenshot->getId(), explicitlyRequested: true));
    }

    #[LiveAction]
    public function save(): void
    {
        $this->validate();
        $this->entityManager->flush();
        $this->saved = true;
    }

    private function syncAlbum(Album $album, bool $selected): void
    {
        if ($selected) {
            $this->screenshot->addAlbum($album);

            return;
        }

        $this->screenshot->removeAlbum($album);
    }

    #[LiveAction]
    public function addTag(): void
    {
        $name = trim($this->newTag);
        if ('' === $name) {
            return;
        }

        $this->screenshot->addTag($this->tags->findOrCreatePath($name));
        $this->newTag = '';
        $this->save();
    }

    #[LiveAction]
    public function removeTag(#[LiveArg] int $tagId): void
    {
        $tag = $this->tags->find($tagId) ?? throw new \InvalidArgumentException('Tag introuvable.');
        $this->screenshot->removeTag($tag);
        $this->save();
    }

    public function isAwaitingAi(): bool
    {
        return $this->tagSuggester->isAvailable() && $this->screenshot->isAwaitingAiTagging($this->clock->now());
    }

    /**
     * @return list<Game>
     */
    public function getGames(): array
    {
        return $this->games->findAllByName();
    }

    /**
     * @return list<Album>
     */
    public function getAlbums(): array
    {
        return $this->albums->findAllByTitle();
    }
}
