<?php

namespace App\Command;

use App\Repository\ScreenshotRepository;
use App\Repository\TagRepository;
use App\Screenshot\EmbeddedTags;
use App\Screenshot\ScreenshotStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:screenshots:embedded-tags', description: 'Importe les tags digiKam / XMP contenus dans les fichiers des screenshots existants')]
final readonly class ImportEmbeddedTagsCommand
{
    public function __construct(
        private ScreenshotRepository $screenshots,
        private TagRepository $tags,
        private EmbeddedTags $embeddedTags,
        private ScreenshotStorage $storage,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $tagged = 0;
        foreach ($io->progressIterate($this->screenshots->findAll()) as $screenshot) {
            $paths = $this->embeddedTags->read($this->storage->absolutePath($screenshot));
            foreach ($paths as $path) {
                $screenshot->addTag($this->tags->findOrCreatePath($path));
            }
            $tagged += [] === $paths ? 0 : 1;
        }
        $this->entityManager->flush();
        $io->success(sprintf('Tags embarqués importés sur %d screenshot(s).', $tagged));

        return Command::SUCCESS;
    }
}
