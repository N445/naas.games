<?php

namespace App\Screenshot;

use App\Entity\Album;
use App\Entity\Screenshot;
use App\Ai\TagScreenshot;
use App\Entity\Game;
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
        private PaletteExtractor $paletteExtractor,
        private EmbeddedTags $embeddedTags,
        private TagRepository $tags,
        private ClockInterface $clock,
    ) {
    }

    public function import(UploadedFile $upload, ?Album $album, ?Game $game): Screenshot
    {
        $screenshot = new Screenshot($this->storage->store($upload));
        $screenshot->setTitle(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME));
        $screenshot->setTakenAt(CaptureDate::guess($this->storage->absolutePath($screenshot), $upload->getClientOriginalName()));
        $screenshot->setGame($game);
        $screenshot->usePalette($this->paletteExtractor->extract($this->storage->absolutePath($screenshot)));
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
        $this->bus->dispatch(new TagScreenshot((int) $screenshot->getId()));

        return $screenshot;
    }
}
