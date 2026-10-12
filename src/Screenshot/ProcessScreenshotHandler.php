<?php

namespace App\Screenshot;

use App\Ai\TagScreenshot;
use App\Entity\Screenshot;
use App\Repository\ScreenshotRepository;
use App\Vision\VisualContextBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Service\FilterService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ProcessScreenshotHandler
{
    private const array WARMED_FILTERS = ['screenshot_thumbnail', 'front_lqip', 'front_thumb', 'front_card'];

    public function __construct(
        private ScreenshotRepository $screenshots,
        private ScreenshotStorage $storage,
        private PaletteExtractor $paletteExtractor,
        private VisualContextBuilder $visualContext,
        private FilterService $imagine,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessScreenshot $message): void
    {
        $screenshot = $this->screenshots->find($message->screenshotId);
        if (null === $screenshot) {
            return;
        }

        if (null === $screenshot->getPalette()) {
            $screenshot->usePalette($this->paletteExtractor->extract($this->storage->absolutePath($screenshot)));
        }
        $this->embed($screenshot);
        $this->entityManager->flush();
        $this->warmThumbnails($screenshot);

        if (!$screenshot->hasTags()) {
            $this->bus->dispatch(new TagScreenshot((int) $screenshot->getId()));
        }
    }

    private function embed(Screenshot $screenshot): void
    {
        try {
            $this->visualContext->embed($screenshot);
        } catch (\Throwable $exception) {
            $this->logger->warning('Empreinte visuelle impossible pour le screenshot {id}.', ['id' => $screenshot->getId(), 'exception' => $exception]);
        }
    }

    private function warmThumbnails(Screenshot $screenshot): void
    {
        foreach (self::WARMED_FILTERS as $filter) {
            $this->imagine->getUrlOfFilteredImage($screenshot->getPath(), $filter);
        }
    }
}
