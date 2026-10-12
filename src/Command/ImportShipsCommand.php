<?php

namespace App\Command;

use App\Entity\Ship;
use App\Repository\ShipRepository;
use App\Vision\VisionClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'app:ships:import', description: 'Importe les vaisseaux de la Ship Matrix de Star Citizen et calcule leur empreinte visuelle')]
final readonly class ImportShipsCommand
{
    private const string SHIP_MATRIX_URL = 'https://robertsspaceindustries.com/ship-matrix/index';

    public function __construct(
        private HttpClientInterface $httpClient,
        private ShipRepository $ships,
        private VisionClient $vision,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $entries = $this->httpClient->request('GET', self::SHIP_MATRIX_URL)->toArray()['data'];
        $ships = array_map($this->upsert(...), $entries);
        $this->entityManager->flush();
        $io->success(sprintf('%d vaisseaux importés.', count($ships)));

        $toEmbed = array_values(array_filter($ships, static fn (Ship $ship): bool => $ship->needsEmbedding()));
        foreach ($io->progressIterate($toEmbed) as $ship) {
            $this->embed($ship, $io);
        }
        $this->entityManager->flush();
        $io->success(sprintf('%d empreintes visuelles calculées.', count($toEmbed)));

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function upsert(array $entry): Ship
    {
        $ship = $this->ships->findOrCreate((int) $entry['id'])->describe(
            trim($entry['name']),
            trim($entry['manufacturer']['name']),
            $entry['focus'] ?: null,
            $entry['size'] ?: null,
            $entry['media'][0]['images']['channel_item_full'] ?? null,
        );
        $this->entityManager->persist($ship);

        return $ship;
    }

    private function embed(Ship $ship, SymfonyStyle $io): void
    {
        try {
            $image = $this->httpClient->request('GET', (string) $ship->getImageUrl())->getContent();
            $ship->embedWith($this->vision->embedContent($image, basename((string) $ship->getImageUrl())));
        } catch (\Throwable $exception) {
            $io->warning(sprintf('%s : %s', $ship->getName(), $exception->getMessage()));
        }
    }
}
