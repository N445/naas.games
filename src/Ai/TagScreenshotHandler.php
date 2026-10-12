<?php

namespace App\Ai;

use App\Entity\Screenshot;
use App\Repository\GameRepository;
use App\Repository\ScreenshotRepository;
use App\Repository\TagRepository;
use App\Vision\VisualContextBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TagScreenshotHandler
{
    public function __construct(
        private ScreenshotRepository $screenshots,
        private GameRepository $games,
        private TagRepository $tags,
        private TagSuggester $suggester,
        private VisualContextBuilder $visualContext,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(TagScreenshot $message): void
    {
        $screenshot = $this->screenshots->find($message->screenshotId);
        if (null === $screenshot || !$this->suggester->isAvailable()) {
            return;
        }

        $this->visualContext->embed($screenshot);
        if ($screenshot->hasTags() && !$message->explicitlyRequested) {
            $this->entityManager->flush();

            return;
        }

        $suggestion = $this->suggester->suggest($screenshot, $this->visualContext->build($screenshot));
        $this->assignGame($screenshot, $suggestion);
        $this->addTags($screenshot, $suggestion);
        $screenshot->markAiTagged($this->clock->now(), $suggestion->tags());
        $this->entityManager->flush();
    }

    private function assignGame(Screenshot $screenshot, TagSuggestion $suggestion): void
    {
        if (null === $screenshot->getGame() && null !== $suggestion->gameSlug()) {
            $screenshot->setGame($this->games->findOneBySlug($suggestion->gameSlug()));
        }
    }

    private function addTags(Screenshot $screenshot, TagSuggestion $suggestion): void
    {
        foreach ($suggestion->tags() as $path) {
            $screenshot->addTag($this->tags->findOrCreatePath($path));
        }
    }
}
