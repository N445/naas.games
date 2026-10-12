<?php

namespace App\Vision;

final readonly class VisualContext
{
    /**
     * @param list<ShipMatch> $ships
     * @param list<TagVote>   $tagVotes
     */
    public function __construct(
        private array $ships,
        private array $tagVotes,
    ) {
    }

    public static function empty(): self
    {
        return new self([], []);
    }

    public function bestShip(): ?ShipMatch
    {
        return $this->ships[0] ?? null;
    }

    /**
     * @return list<TagVote>
     */
    public function tagVotes(): array
    {
        return $this->tagVotes;
    }

    public function describe(): string
    {
        $lines = [];
        if ([] !== $this->ships) {
            $lines[] = "Vaisseaux de la Ship Matrix visuellement proches (pistes à confirmer, elles peuvent être fausses) :\n- ".implode("\n- ", array_map(static fn (ShipMatch $match): string => $match->describe(), $this->ships));
        }
        if ([] !== $this->tagVotes) {
            $lines[] = "Tags des screenshots les plus ressemblants, validés par l'utilisateur : ".implode(', ', array_map(static fn (TagVote $vote): string => $vote->describe(), $this->tagVotes));
        }

        return implode("\n", $lines);
    }
}
