<?php

namespace App\Command;

use App\Entity\Screenshot;
use App\Repository\ScreenshotOriginalRepository;
use App\Repository\ScreenshotRepository;
use App\Screenshot\Original\OriginalLibrary;
use App\Screenshot\Original\OriginalName;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * Walks a capture library (read-only is fine: files are copied, never moved) and attaches every 32:9
 * image to the final(s) sharing its normalized name. Unmatched originals are skipped unless asked for,
 * so a multi-gigabyte library does not end up copied for nothing.
 */
#[AsCommand(name: 'app:originals:import', description: 'Rattache en masse les originaux 32:9 d’un dossier aux screenshots finaux de même nom')]
final readonly class ImportOriginalsCommand
{
    private const array EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'tif', 'tiff'];
    private const int SAMPLE = 15;

    public function __construct(
        private ScreenshotRepository $screenshots,
        private ScreenshotOriginalRepository $originals,
        private OriginalLibrary $library,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<string> $exclude
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('Dossier à parcourir récursivement (ex. Nextcloud/Photos/Screenshots)')] string $dir,
        #[Option('Simule sans rien copier ni enregistrer')] bool $dryRun = false,
        #[Option('Importe aussi les originaux sans final correspondant (en attente) — attention au volume')] bool $keepUnmatched = false,
        #[Option('Simulation : compare à des finals présents dans ce dossier plutôt qu’à la photothèque', 'finals-from')] ?string $finalsFrom = null,
        #[Option('Sous-dossiers à ignorer (nom exact)')] array $exclude = ['.dtrash', 'Final'],
    ): int {
        if (!is_dir($dir)) {
            $io->error(sprintf('Dossier introuvable : %s', $dir));

            return Command::FAILURE;
        }
        if (null !== $finalsFrom && !$dryRun) {
            $io->error('--finals-from n’est utilisable qu’avec --dry-run.');

            return Command::INVALID;
        }

        $finals = null === $finalsFrom ? $this->libraryFinals() : $this->folderFinals($finalsFrom);
        $knownHashes = $this->originals->hashIndex();
        $stats = ['scanned' => 0, 'not_panoramic' => 0, 'matched' => 0, 'by_suffix' => 0, 'unmatched' => 0, 'already' => 0, 'same_name' => 0, 'imported' => 0, 'pending' => 0];
        $covered = [];
        $claimedKeys = [];

        $files = iterator_to_array($this->files($dir, $exclude), false);
        $io->text(sprintf('%d image(s) trouvée(s) dans %s, %d final(s) de référence%s.', count($files), $dir, array_sum(array_map(count(...), $finals)), null === $finalsFrom ? ' dans la photothèque' : ' dans '.$finalsFrom));

        foreach ($io->progressIterate($files) as $file) {
            ++$stats['scanned'];
            [$width, $height] = @getimagesize($file->getPathname()) ?: [0, 0];
            if (!OriginalName::isPanoramic($width, $height)) {
                ++$stats['not_panoramic'];
                continue;
            }

            $key = OriginalName::key($file->getFilename());
            $matching = $finals[$key] ?? [];
            if ([] === $matching) {
                ++$stats['unmatched'];
                if ($keepUnmatched && !$dryRun && !isset($claimedKeys[$key])) {
                    $stats[$this->import($file->getPathname(), $knownHashes)] += 1;
                    $claimedKeys[$key] = true;
                }
                continue;
            }
            if (isset($claimedKeys[$key])) {
                ++$stats['same_name'];
                continue;
            }
            $claimedKeys[$key] = true;
            ++$stats['matched'];
            foreach ($matching as $label => $final) {
                $covered[$label] = true;
                $stats['by_suffix'] += OriginalName::version($final['name']) === OriginalName::version($file->getFilename()) ? 0 : 1;
            }
            if ([] === array_filter($matching, static fn (array $final): bool => !$final['linked'])) {
                ++$stats['already'];
                continue;
            }
            if (!$dryRun) {
                $stats[$this->import($file->getPathname(), $knownHashes)] += 1;
            }
        }

        $this->report($io, $stats, $finals, $covered, $dryRun);

        return Command::SUCCESS;
    }

    /**
     * @param array<string, true> $knownHashes
     *
     * @return 'imported'|'already'|'pending'
     */
    private function import(string $path, array &$knownHashes): string
    {
        $hash = (string) sha1_file($path);
        if (isset($knownHashes[$hash]) || null !== $this->screenshots->findOneByHash($hash)) {
            return 'already';
        }
        $knownHashes[$hash] = true;
        $original = $this->library->storeCopy($path, $hash);
        $this->entityManager->clear();

        return $original->isPending() ? 'pending' : 'imported';
    }

    /**
     * @param array<string, int>                                          $stats
     * @param array<string, array<string, array{name: string, linked: bool}>> $finals
     * @param array<string, true>                                         $covered
     */
    private function report(SymfonyStyle $io, array $stats, array $finals, array $covered, bool $dryRun): void
    {
        $allFinals = array_merge(...array_values($finals) ?: [[]]);
        $linked = array_filter($allFinals, static fn (array $final): bool => $final['linked']);
        $missing = array_keys(array_diff_key($allFinals, $covered, $linked));
        sort($missing);

        $io->section($dryRun ? 'Simulation (rien n’a été copié ni enregistré)' : 'Import terminé');
        $io->definitionList(
            ['Images parcourues' => $stats['scanned']],
            ['Ignorées (pas au format 32:9)' => $stats['not_panoramic']],
            ['Originaux 32:9 avec un final de même nom' => $stats['matched']],
            ['  dont via un suffixe darktable _NN' => $stats['by_suffix'].' final(s)'],
            ['Originaux en double (même nom, ignorés)' => $stats['same_name']],
            ['Originaux ignorés (déjà présents, ou finals déjà pourvus)' => $stats['already']],
            ['Originaux 32:9 sans final correspondant' => $stats['unmatched']],
            ['Finals couverts' => sprintf('%d / %d', count($covered), count($allFinals))],
            ['Finals déjà pourvus d’un original (avant cet import)' => count($linked)],
            ['Finals sans original trouvé par le nom' => count($missing)],
        );
        if (!$dryRun) {
            $io->text(sprintf('%d original(aux) copié(s) et rattaché(s), %d en attente de final, %d déjà présent(s).', $stats['imported'], $stats['pending'], $stats['already']));
        }
        if ([] !== $missing) {
            $io->text('Finals sans correspondance de nom (à rapprocher visuellement depuis leur fiche admin) :');
            $io->listing(array_slice($missing, 0, self::SAMPLE));
            if (count($missing) > self::SAMPLE) {
                $io->text(sprintf('… et %d autre(s).', count($missing) - self::SAMPLE));
            }
        }
    }

    /**
     * @return array<string, array<string, array{name: string, linked: bool}>> match key => [label => final]
     */
    private function libraryFinals(): array
    {
        $finals = [];
        foreach ($this->screenshots->findAll() as $screenshot) {
            $finals[$screenshot->getMatchKey()][$this->label($screenshot)] = ['name' => $screenshot->getFile()->getOriginalName(), 'linked' => $screenshot->hasOriginal()];
        }

        return $finals;
    }

    /**
     * @return array<string, array<string, array{name: string, linked: bool}>>
     */
    private function folderFinals(string $dir): array
    {
        $finals = [];
        foreach ($this->files($dir, ['.dtrash']) as $file) {
            $finals[OriginalName::key($file->getFilename())][$file->getRelativePathname()] = ['name' => $file->getFilename(), 'linked' => false];
        }

        return $finals;
    }

    private function label(Screenshot $screenshot): string
    {
        return sprintf('#%d %s', $screenshot->getId(), $screenshot->getFile()->getOriginalName());
    }

    /**
     * @param list<string> $exclude
     *
     * @return iterable<\Symfony\Component\Finder\SplFileInfo>
     */
    private function files(string $dir, array $exclude): iterable
    {
        return (new Finder())
            ->files()
            ->in($dir)
            ->exclude($exclude)
            ->ignoreDotFiles(true)
            ->name(array_map(static fn (string $extension): string => '/\.'.$extension.'$/i', self::EXTENSIONS))
            ->sortByName();
    }
}
