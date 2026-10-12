<?php

namespace App\Screenshot;

use App\Entity\Screenshot;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postRemove, entity: Screenshot::class)]
final readonly class ScreenshotFileRemover
{
    public function __construct(private ScreenshotStorage $storage)
    {
    }

    public function postRemove(Screenshot $screenshot): void
    {
        $this->storage->delete($screenshot);
    }
}
