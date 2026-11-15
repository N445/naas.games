<?php

namespace App\Command;

use App\Entity\Screenshot;
use App\Repository\ScreenshotRepository;
use App\Screenshot\Duplicate\DuplicateFinder;
use App\Screenshot\PerceptualHash;
use App\Screenshot\ScreenshotStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fills in what duplicate detection and original matching rely on for screenshots imported before
 * they existed: SHA-1 of the file (exact duplicates), perceptual hash (lookalikes) and name key (originals).
 */
#[AsCommand(name: 'app:screenshots:hash', description: 'Calcule les empreintes (fichier, perceptuelle, nom) des screenshots existants pour détecter doublons et originaux')]
final readonly class HashScreenshotsCommand
{
    public function __construct(
        private ScreenshotRepository $screenshots,
        private ScreenshotStorage $storage,
        private DuplicateFinder $duplicates,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $pending = array_values(array_filter($this->screenshots->findAll(), static fn (Screenshot $screenshot): bool => null === $screenshot->getFile()->getHash() || null === $screenshot->getPerceptualHash()));
        foreach ($io->progressIterate($pending) as $screenshot) {
            $path = $this->storage->absolutePath($screenshot);
            if (null === $screenshot->getFile()->getHash()) {
                $screenshot->rememberHash((string) sha1_file($path));
            }
            $screenshot->rememberPerceptualHash(PerceptualHash::compute($path));
        }
        foreach ($this->screenshots->findAll() as $screenshot) {
            $screenshot->rememberMatchKey();
        }
        $this->entityManager->flush();

        $groups = $this->duplicates->groups();
        $identical = count(array_filter($groups, static fn ($group): bool => $group->identical));
        $io->success(sprintf('%d screenshot(s) mis à jour. %d groupe(s) de doublons identiques, %d groupe(s) de quasi-doublons probables.', count($pending), $identical, count($groups) - $identical));

        return Command::SUCCESS;
    }
}
