<?php

namespace App\Ai;

use App\Entity\Game;
use App\Entity\Screenshot;
use App\Repository\GameRepository;
use App\Repository\TagRepository;
use App\Vision\VisualContext;

final readonly class TagPrompt
{
    public const string UNKNOWN_GAME = 'inconnu';

    private const string INSTRUCTIONS = <<<'TXT'
        Tu indexes les screenshots d'un joueur pour sa photothèque en ligne.
        Pour chaque image, propose 3 ou 4 tags courts qui la décrivent et qui aideront à la retrouver.

        Règles :
        - Donne la priorité à ce qui est reconnaissable et propre au jeu : modèle exact de vaisseau, machine ou ennemi, lieu, faction.
        - N'avance un nom précis que si tu le reconnais avec confiance ; sinon reste générique (« vaisseau », « station spatiale », « combat »).
        - Complète avec un ou deux tags d'ambiance ou de scène (« coucher de soleil », « intérieur », « atterrissage », « panorama »).
        - Tags en français et en minuscules, sauf les noms propres du jeu, que tu écris comme dans le jeu.
        - Un à trois mots par niveau, sans « # ».
        - Les tags forment une arborescence écrite avec « / », par exemple « Vehicule/Drake/Ironclad Assault » ou « Location/Stanton/ArcCorp ». Quand un tag a sa place dans une branche existante, donne son chemin complet en suivant exactement la même arborescence ; sinon donne un tag simple sans « / ».
        - Réutilise un tag existant de la photothèque quand il convient, pour garder un vocabulaire cohérent.
        - Ne répète pas les tags déjà posés sur l'image : ils sont fiables, propose seulement des tags complémentaires.
        - Une reconnaissance visuelle locale peut te fournir des pistes : vaisseaux de la Ship Matrix ressemblants et tags de screenshots similaires déjà validés par l'utilisateur. Sers-t'en pour choisir le bon nom, mais ne retiens une piste que si l'image la confirme. Les tags validés par l'utilisateur sont fiables pour son vocabulaire.
        - Identifie aussi le jeu parmi la liste ci-dessous, ou réponds « inconnu ».

        Jeux connus et leur univers :
        TXT;

    private const int EXISTING_TAGS_LIMIT = 150;

    public function __construct(
        private GameRepository $games,
        private TagRepository $tags,
    ) {
    }

    public function instructions(): string
    {
        return self::INSTRUCTIONS."\n\n".implode("\n\n", array_map(static fn (Game $game): string => $game->describeForAi(), $this->games->findAllByName()));
    }

    public function request(Screenshot $screenshot, VisualContext $context): string
    {
        $game = $screenshot->getGame();

        return implode("\n", [
            null === $game ? 'Jeu : non précisé, identifie-le.' : sprintf('Jeu : %s (%s).', $game->getName(), $game->getSlug()),
            sprintf('Nom du fichier : %s', $screenshot->getFile()->getOriginalName()),
            sprintf('Tags déjà posés sur cette image par l\'utilisateur : %s', implode(', ', $screenshot->tagPaths()) ?: 'aucun'),
            sprintf('Arborescence des tags de la photothèque : %s', implode(', ', $this->tags->findMostUsedPaths(self::EXISTING_TAGS_LIMIT)) ?: 'aucun'),
            $context->describe(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $gameSlugs = array_map(static fn (Game $game): string => (string) $game->getSlug(), $this->games->findAllByName());

        return [
            'type' => 'object',
            'properties' => [
                'game' => ['type' => 'string', 'enum' => [...$gameSlugs, self::UNKNOWN_GAME]],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => '3 ou 4 tags'],
            ],
            'required' => ['game', 'tags'],
            'additionalProperties' => false,
        ];
    }
}
