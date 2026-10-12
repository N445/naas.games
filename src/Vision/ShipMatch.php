<?php

namespace App\Vision;

use App\Entity\Ship;

final readonly class ShipMatch
{
    public function __construct(
        public Ship $ship,
        public float $score,
    ) {
    }

    public function describe(): string
    {
        return sprintf('%s (%s, %s) — similarité %.2f', $this->ship->getFullName(), $this->ship->getFocus() ?? 'rôle inconnu', $this->ship->getSize() ?? 'taille inconnue', $this->score);
    }
}
