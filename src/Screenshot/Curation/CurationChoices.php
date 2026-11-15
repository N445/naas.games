<?php

namespace App\Screenshot\Curation;

use App\Entity\Album;
use App\Entity\Game;
use App\Entity\Tag;
use App\Repository\AlbumRepository;
use App\Repository\GameRepository;
use App\Repository\TagRepository;
use Twig\Attribute\AsTwigFunction;

/**
 * Options of the front editing selects, loaded once per request (admin pages only).
 */
final class CurationChoices
{
    /** @var array{tags: list<Tag>, albums: list<Album>, games: list<Game>}|null */
    private ?array $choices = null;

    public function __construct(
        private readonly TagRepository $tags,
        private readonly AlbumRepository $albums,
        private readonly GameRepository $games,
    ) {
    }

    /**
     * @return array{tags: list<Tag>, albums: list<Album>, games: list<Game>}
     */
    #[AsTwigFunction('curation_choices')]
    public function all(): array
    {
        if (null === $this->choices) {
            $tags = $this->tags->findAll();
            usort($tags, static fn (Tag $left, Tag $right): int => strnatcasecmp($left->getPath(), $right->getPath()));
            $this->choices = ['tags' => $tags, 'albums' => $this->albums->findAllByTitle(), 'games' => $this->games->findAllByName()];
        }

        return $this->choices;
    }
}
