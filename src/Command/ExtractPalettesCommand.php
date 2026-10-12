<?php

namespace App\Command;

use App\Entity\Screenshot;
use App\Repository\ScreenshotRepository;
use App\Screenshot\PaletteExtractor;
use App\Screenshot\ScreenshotStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:screenshots:palette', description: 'Calcule les 3 couleurs prédominantes des screenshots')]
final readonly class ExtractPalettesCommand
{
    public function __construct(
        private ScreenshotRepository $screenshots,
        private PaletteExtractor $extractor,
        private ScreenshotStorage $storage,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Recalcule aussi les palettes existantes')] bool $force = false): int
    {
        $pending = array_values(array_filter($this->screenshots->findAll(), static fn (Screenshot $screenshot): bool => $force || null === $screenshot->getPalette()));
        foreach ($io->progressIterate($pending) as $screenshot) {
            $screenshot->usePalette($this->extractor->extract($this->storage->absolutePath($screenshot)));
        }
        $this->entityManager->flush();
        $io->success(sprintf('%d palette(s) calculée(s).', count($pending)));

        return Command::SUCCESS;
    }
}
