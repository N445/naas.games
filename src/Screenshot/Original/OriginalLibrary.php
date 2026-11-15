<?php

namespace App\Screenshot\Original;

use App\Entity\Screenshot;
use App\Entity\ScreenshotOriginal;
use App\Repository\ScreenshotOriginalRepository;
use App\Repository\ScreenshotRepository;
use App\Screenshot\CaptureDate;
use App\Screenshot\ScreenshotStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Pairs finals with their 32:9 originals: by normalized file name first, by visual similarity
 * as a fallback that an admin confirms by hand.
 */
final readonly class OriginalLibrary
{
    private const string CAPTURE_WINDOW = '+36 hours';
    private const float MIN_VISUAL_SIMILARITY = 0.6;

    public function __construct(
        private ScreenshotOriginalRepository $originals,
        private ScreenshotRepository $screenshots,
        private ScreenshotStorage $storage,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $bus,
    ) {
    }

    /**
     * Finals an incoming panoramic image would be the original of. A final that is itself the same
     * 32:9 frame (identical file) is never its own original.
     *
     * @return list<Screenshot>
     */
    public function finalsNamed(string $fileName, ?string $hash = null): array
    {
        return array_values(array_filter(
            $this->screenshots->findByMatchKey(OriginalName::key($fileName)),
            static fn (Screenshot $final): bool => null === $hash || $final->getFile()->getHash() !== $hash,
        ));
    }

    public function storeUpload(UploadedFile $upload, string $hash): ScreenshotOriginal
    {
        $original = new ScreenshotOriginal($this->storage->storeOriginal($upload, $hash));
        $original->setTakenAt(CaptureDate::guess($this->storage->absolutePath($original), $upload->getClientOriginalName()));

        return $this->register($original);
    }

    public function storeCopy(string $sourcePath, string $hash): ScreenshotOriginal
    {
        $original = new ScreenshotOriginal($this->storage->copyOriginal($sourcePath, $hash));
        $original->setTakenAt(CaptureDate::guess($this->storage->absolutePath($original), basename($sourcePath)));

        return $this->register($original);
    }

    /**
     * When the final arrives after its original, the waiting original is picked up here.
     */
    public function attachWaitingOriginal(Screenshot $final): ?ScreenshotOriginal
    {
        if ($final->hasOriginal() || $final->isPanoramic()) {
            return null;
        }
        $original = $this->originals->findOneByMatchKey($final->getMatchKey());
        if (null === $original || $original->getFile()->getHash() === $final->getFile()->getHash()) {
            return null;
        }
        $final->linkOriginal($original);

        return $original;
    }

    public function link(Screenshot $final, ScreenshotOriginal $original): void
    {
        $final->linkOriginal($original);
        $this->entityManager->flush();
    }

    public function unlink(Screenshot $final): void
    {
        $final->unlinkOriginal();
        $this->entityManager->flush();
    }

    /**
     * Turns a 32:9 screenshot that was published by mistake into an original, handing its file over.
     *
     * @return list<Screenshot> finals now linked to it
     */
    public function demote(Screenshot $screenshot): array
    {
        $original = new ScreenshotOriginal($screenshot->getFile());
        $original->setTakenAt($screenshot->getTakenAt());
        $original->rememberPerceptualHash($screenshot->getPerceptualHash());
        if (null !== $screenshot->embedding()) {
            $original->embedWith($screenshot->embedding());
        }
        $screenshot->unlinkOriginal();
        $finals = array_values(array_filter(
            $this->screenshots->findByMatchKey($original->getMatchKey()),
            static fn (Screenshot $final): bool => $final !== $screenshot && !$final->hasOriginal(),
        ));
        array_walk($finals, static fn (Screenshot $final) => $final->linkOriginal($original));
        $this->entityManager->persist($original);
        $this->entityManager->remove($screenshot);
        $this->entityManager->flush();

        return $finals;
    }

    /**
     * @return list<OriginalCandidate>
     */
    public function candidatesFor(Screenshot $final, int $limit): array
    {
        $candidates = [];
        foreach ($this->originals->findBy(['matchKey' => $final->getMatchKey()]) as $original) {
            $candidates[(int) $original->getId()] = new OriginalCandidate($original, 'name');
        }

        $embedding = $final->embedding();
        $pool = null !== $final->getTakenAt()
            ? $this->originals->findTakenBetween($final->getTakenAt()->modify('-'.ltrim(self::CAPTURE_WINDOW, '+')), $final->getTakenAt()->modify(self::CAPTURE_WINDOW))
            : [];
        if (null !== $embedding) {
            $pool = [...$pool, ...$this->originals->findEmbedded()];
        }

        $visual = [];
        foreach ($pool as $original) {
            if (isset($candidates[(int) $original->getId()]) || isset($visual[(int) $original->getId()])) {
                continue;
            }
            $similarity = null === $embedding || null === $original->embedding() ? null : $embedding->similarity($original->embedding());
            if (null !== $similarity && $similarity < self::MIN_VISUAL_SIMILARITY) {
                continue;
            }
            $visual[(int) $original->getId()] = new OriginalCandidate($original, null === $similarity ? 'date' : 'visual', $similarity);
        }
        usort($visual, static fn (OriginalCandidate $left, OriginalCandidate $right): int => ($right->similarity ?? 0.0) <=> ($left->similarity ?? 0.0));

        return array_slice([...array_values($candidates), ...$visual], 0, $limit);
    }

    /**
     * Attaches the new original to every same-named final that has none yet.
     */
    private function register(ScreenshotOriginal $original): ScreenshotOriginal
    {
        $finals = array_values(array_filter(
            $this->finalsNamed($original->getFile()->getOriginalName(), $original->getFile()->getHash()),
            static fn (Screenshot $final): bool => !$final->hasOriginal(),
        ));
        array_walk($finals, static fn (Screenshot $final) => $final->linkOriginal($original));
        $this->entityManager->persist($original);
        $this->entityManager->flush();
        $this->bus->dispatch(new ProcessOriginal((int) $original->getId()));

        return $original;
    }
}
