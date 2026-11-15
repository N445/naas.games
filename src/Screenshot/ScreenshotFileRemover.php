<?php

namespace App\Screenshot;

use App\Entity\Screenshot;
use App\Entity\ScreenshotOriginal;
use App\Repository\ScreenshotOriginalRepository;
use App\Repository\ScreenshotRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postRemove, entity: Screenshot::class)]
#[AsEntityListener(event: Events::postRemove, entity: ScreenshotOriginal::class)]
final readonly class ScreenshotFileRemover
{
    public function __construct(
        private ScreenshotStorage $storage,
        private ScreenshotRepository $screenshots,
        private ScreenshotOriginalRepository $originals,
    ) {
    }

    public function postRemove(Screenshot|ScreenshotOriginal $image): void
    {
        // A screenshot demoted to an original hands its file over instead of losing it.
        if ($this->isStillReferenced($image)) {
            return;
        }

        $this->storage->delete($image);
    }

    private function isStillReferenced(Screenshot|ScreenshotOriginal $image): bool
    {
        return $image instanceof Screenshot
            ? null !== $this->originals->findOneByPath($image->getPath())
            : null !== $this->screenshots->findOneBy(['file.path' => $image->getPath()]);
    }
}
