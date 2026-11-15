<?php

namespace App\Screenshot\Original;

use App\Repository\ScreenshotOriginalRepository;
use App\Screenshot\PerceptualHash;
use App\Screenshot\ScreenshotStorage;
use App\Vision\VisionClient;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Service\FilterService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Computes what visual matching needs (embedding, perceptual hash) and warms the admin thumbnails.
 */
#[AsMessageHandler]
final readonly class ProcessOriginalHandler
{
    private const array WARMED_FILTERS = ['screenshot_thumbnail', 'screenshot_preview'];

    public function __construct(
        private ScreenshotOriginalRepository $originals,
        private ScreenshotStorage $storage,
        private VisionClient $vision,
        private FilterService $imagine,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessOriginal $message): void
    {
        $original = $this->originals->find($message->originalId);
        if (null === $original) {
            return;
        }

        $path = $this->storage->absolutePath($original);
        if (null === $original->getPerceptualHash()) {
            $original->rememberPerceptualHash(PerceptualHash::compute($path));
        }
        if (null === $original->embedding() && $this->vision->isAvailable()) {
            try {
                $original->embedWith($this->vision->embedFile($path));
            } catch (\Throwable $exception) {
                $this->logger->warning('Empreinte visuelle impossible pour l’original {id}.', ['id' => $original->getId(), 'exception' => $exception]);
            }
        }
        $this->entityManager->flush();

        foreach (self::WARMED_FILTERS as $filter) {
            $this->imagine->getUrlOfFilteredImage($original->getPath(), $filter);
        }
    }
}
