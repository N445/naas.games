<?php

namespace App\Screenshot;

use App\Entity\Album;
use App\Entity\Screenshot;
use App\Entity\Game;
use App\Repository\ScreenshotRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ScreenshotImporter
{
    public function __construct(
        private ScreenshotStorage $storage,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
        private EmbeddedTags $embeddedTags,
        private TagRepository $tags,
        private ScreenshotRepository $screenshots,
        private ClockInterface $clock,
    ) {
    }

    public function import(UploadedFile $upload, ?Album $album, ?Game $game): ImportResult
    {
        $hash = (string) sha1_file($upload->getPathname());
        $duplicate = $this->screenshots->findOneByHash($hash);
        if (null !== $duplicate) {
            return $this->attachDuplicate($duplicate, $album);
        }

        $screenshot = new Screenshot($this->storage->store($upload, $hash));
        $screenshot->setTitle(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME));
        $screenshot->setTakenAt(CaptureDate::guess($this->storage->absolutePath($screenshot), $upload->getClientOriginalName()));
        $screenshot->setGame($game);
        foreach ($this->embeddedTags->read($this->storage->absolutePath($screenshot)) as $path) {
            $screenshot->addTag($this->tags->findOrCreatePath($path));
        }
        if ($album) {
            $screenshot->addAlbum($album);
        }
        if (!$screenshot->hasTags()) {
            $screenshot->requestAiTagging($this->clock->now());
        }

        $this->entityManager->persist($screenshot);
        $this->entityManager->flush();
        $this->bus->dispatch(new ProcessScreenshot((int) $screenshot->getId()));

        return ImportResult::imported($screenshot);
    }

    private function attachDuplicate(Screenshot $duplicate, ?Album $album): ImportResult
    {
        if ($album) {
            $duplicate->addAlbum($album);
            $this->entityManager->flush();
        }

        return ImportResult::duplicateOf($duplicate);
    }
}
