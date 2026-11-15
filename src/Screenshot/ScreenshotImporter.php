<?php

namespace App\Screenshot;

use App\Entity\Album;
use App\Entity\Game;
use App\Entity\Screenshot;
use App\Repository\ScreenshotOriginalRepository;
use App\Repository\ScreenshotRepository;
use App\Repository\TagRepository;
use App\Screenshot\Duplicate\DuplicateFinder;
use App\Screenshot\Original\OriginalLibrary;
use App\Screenshot\Original\OriginalName;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ScreenshotImporter
{
    public function __construct(
        private ScreenshotStorage $storage,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
        private EmbeddedTags $embeddedTags,
        private TagRepository $tags,
        private ScreenshotRepository $screenshots,
        private ScreenshotOriginalRepository $originals,
        private OriginalLibrary $originalLibrary,
        private DuplicateFinder $duplicates,
        private ClockInterface $clock,
    ) {
    }

    public function import(UploadedFile $upload, ?Album $album, ?Game $game, ImportMode $mode = ImportMode::Auto): ImportResult
    {
        $hash = (string) sha1_file($upload->getPathname());
        $duplicate = $this->screenshots->findOneByHash($hash);
        if (null !== $duplicate) {
            return $this->attachDuplicate($duplicate, $album);
        }
        $knownOriginal = $this->originals->findOneByHash($hash);
        if (null !== $knownOriginal) {
            return ImportResult::original($knownOriginal, $knownOriginal->getFinals()->toArray(), duplicate: true);
        }
        if ($this->isOriginal($upload, $hash, $mode)) {
            $original = $this->originalLibrary->storeUpload($upload, $hash);

            return ImportResult::original($original, $original->getFinals()->toArray());
        }

        $screenshot = new Screenshot($this->storage->store($upload, $hash));
        $screenshot->setTitle(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME));
        $screenshot->setTakenAt(CaptureDate::guess($this->storage->absolutePath($screenshot), $upload->getClientOriginalName()));
        $screenshot->setGame($game);
        $screenshot->rememberPerceptualHash(PerceptualHash::compute($this->storage->absolutePath($screenshot)));
        foreach ($this->embeddedTags->read($this->storage->absolutePath($screenshot)) as $path) {
            $screenshot->addTag($this->tags->findOrCreatePath($path));
        }
        if ($album) {
            $screenshot->addAlbum($album);
        }
        if (!$screenshot->hasTags()) {
            $screenshot->requestAiTagging($this->clock->now());
        }
        $this->originalLibrary->attachWaitingOriginal($screenshot);

        $this->entityManager->persist($screenshot);
        $this->entityManager->flush();
        $this->bus->dispatch(new ProcessScreenshot((int) $screenshot->getId()));

        return ImportResult::imported($screenshot, $this->duplicates->lookalikesOf($screenshot));
    }

    private function isOriginal(UploadedFile $upload, string $hash, ImportMode $mode): bool
    {
        if (ImportMode::Auto !== $mode) {
            return ImportMode::Originals === $mode;
        }
        [$width, $height] = getimagesize($upload->getPathname()) ?: [0, 0];

        return OriginalName::isPanoramic($width, $height) && [] !== $this->originalLibrary->finalsNamed($upload->getClientOriginalName(), $hash);
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
