<?php

namespace App\Command;

use App\Entity\Screenshot;
use App\Repository\ScreenshotRepository;
use App\Screenshot\ScreenshotStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:screenshots:hash', description: 'Calcule l’empreinte des fichiers existants pour détecter les doublons à l’import')]
final readonly class HashScreenshotsCommand
{
    public function __construct(
        private ScreenshotRepository $screenshots,
        private ScreenshotStorage $storage,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $pending = array_values(array_filter($this->screenshots->findAll(), static fn (Screenshot $screenshot): bool => null === $screenshot->getFile()->getHash()));
        foreach ($io->progressIterate($pending) as $screenshot) {
            $screenshot->rememberHash((string) sha1_file($this->storage->absolutePath($screenshot)));
        }
        $this->entityManager->flush();

        $duplicates = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) - COUNT(DISTINCT file_hash) FROM screenshot WHERE file_hash IS NOT NULL');
        $io->success(sprintf('%d empreinte(s) calculée(s). %d doublon(s) déjà présent(s) dans la photothèque.', count($pending), $duplicates));

        return Command::SUCCESS;
    }
}
