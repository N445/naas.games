<?php

namespace App\Vision;

use App\Entity\Screenshot;
use App\Entity\Ship;
use App\Repository\ScreenshotRepository;
use App\Repository\ShipRepository;
use App\Screenshot\ScreenshotStorage;

final readonly class VisualContextBuilder
{
    private const int SHIP_CANDIDATES = 5;
    private const int NEIGHBOURS = 8;
    private const float MIN_NEIGHBOUR_SIMILARITY = 0.35;
    private const string SHIPS_GAME = 'star-citizen';

    public function __construct(
        private VisionClient $vision,
        private ScreenshotStorage $storage,
        private ShipRepository $ships,
        private ScreenshotRepository $screenshots,
    ) {
    }

    public function build(Screenshot $screenshot): VisualContext
    {
        if (!$this->vision->isAvailable()) {
            return VisualContext::empty();
        }

        $embedding = $this->embed($screenshot);

        return new VisualContext($this->shipCandidates($screenshot, $embedding), $this->neighbourVotes($screenshot, $embedding));
    }

    public function embed(Screenshot $screenshot): Embedding
    {
        $embedding = $screenshot->embedding() ?? $this->vision->embedFile($this->storage->absolutePath($screenshot));
        $screenshot->embedWith($embedding);

        return $embedding;
    }

    /**
     * @return list<ShipMatch>
     */
    private function shipCandidates(Screenshot $screenshot, Embedding $embedding): array
    {
        $game = $screenshot->getGame()?->getSlug();
        if (null !== $game && self::SHIPS_GAME !== $game) {
            return [];
        }

        $matches = array_map(static fn (Ship $ship): ShipMatch => new ShipMatch($ship, $embedding->similarity($ship->embedding())), $this->ships->findEmbedded());
        usort($matches, static fn (ShipMatch $left, ShipMatch $right): int => $right->score <=> $left->score);

        return array_slice($matches, 0, self::SHIP_CANDIDATES);
    }

    /**
     * @return list<TagVote>
     */
    private function neighbourVotes(Screenshot $screenshot, Embedding $embedding): array
    {
        $votes = [];
        foreach ($this->neighbours($screenshot, $embedding) as [$neighbour, $similarity]) {
            foreach ($neighbour->tagPaths() as $name) {
                $key = mb_strtolower($name);
                $votes[$key] = ($votes[$key] ?? new TagVote($name, 0, 0.0))->add($similarity);
            }
        }
        usort($votes, static fn (TagVote $left, TagVote $right): int => $right->weight <=> $left->weight);

        return array_values($votes);
    }

    /**
     * @return list<array{Screenshot, float}>
     */
    private function neighbours(Screenshot $screenshot, Embedding $embedding): array
    {
        $scored = [];
        foreach ($this->screenshots->findReviewedOthers($screenshot) as $candidate) {
            if ($this->isSameGame($screenshot, $candidate)) {
                $scored[] = [$candidate, $embedding->similarity($candidate->embedding())];
            }
        }
        $scored = array_filter($scored, static fn (array $pair): bool => $pair[1] >= self::MIN_NEIGHBOUR_SIMILARITY);
        usort($scored, static fn (array $left, array $right): int => $right[1] <=> $left[1]);

        return array_slice($scored, 0, self::NEIGHBOURS);
    }

    private function isSameGame(Screenshot $screenshot, Screenshot $candidate): bool
    {
        return null === $screenshot->getGame() || $screenshot->getGame() === $candidate->getGame();
    }
}
