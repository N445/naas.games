<?php

namespace App\Screenshot\Curation;

use App\Entity\Album;
use App\Entity\Game;
use App\Entity\Screenshot;
use App\Entity\Tag;
use App\Repository\AlbumRepository;
use App\Repository\GameRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Edits screenshots from the public gallery (admins only): the same operations as the admin, applied
 * to one screenshot (inline) or to a selection (batch). Choices come from tom-select, where an existing
 * entity is sent by id and a newly typed one as "new:<name>".
 */
final readonly class ScreenshotCurator
{
    private const string NEW_PREFIX = 'new:';

    public function __construct(
        private TagRepository $tags,
        private AlbumRepository $albums,
        private GameRepository $games,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Replaces whatever the payload mentions; absent keys are left untouched.
     *
     * @param array<string, mixed> $payload
     */
    public function update(Screenshot $screenshot, array $payload): void
    {
        if (array_key_exists('published', $payload)) {
            $screenshot->setPublished((bool) $payload['published']);
        }
        if (array_key_exists('game', $payload)) {
            $screenshot->setGame($this->game($payload['game']));
        }
        if (array_key_exists('albums', $payload)) {
            $albums = $this->albumList((array) $payload['albums']);
            foreach ($screenshot->getAlbums()->toArray() as $album) {
                if (!in_array($album, $albums, true)) {
                    $screenshot->removeAlbum($album);
                }
            }
            array_walk($albums, static fn (Album $album) => $screenshot->addAlbum($album));
        }
        if (array_key_exists('tags', $payload)) {
            $tags = $this->tagList((array) $payload['tags']);
            foreach ($screenshot->getTags()->toArray() as $tag) {
                if (!in_array($tag, $tags, true)) {
                    $screenshot->removeTag($tag);
                }
            }
            array_walk($tags, static fn (Tag $tag) => $screenshot->addTag($tag));
        }
        $this->entityManager->flush();
    }

    /**
     * @param list<Screenshot>     $screenshots
     * @param array<string, mixed> $payload
     *
     * @return array{tags: list<Tag>, album: ?Album} what the operation resolved (created entities included)
     */
    public function apply(CurationOperation $operation, array $screenshots, array $payload): array
    {
        $tags = in_array($operation, [CurationOperation::AddTags, CurationOperation::RemoveTags], true)
            ? $this->tagList((array) ($payload['tags'] ?? []), create: CurationOperation::AddTags === $operation)
            : [];
        $album = in_array($operation, [CurationOperation::AddToAlbum, CurationOperation::RemoveFromAlbum], true)
            ? $this->requiredAlbum($payload['album'] ?? null, create: CurationOperation::AddToAlbum === $operation)
            : null;

        match ($operation) {
            CurationOperation::AddTags => $this->each($screenshots, $tags, static fn (Screenshot $screenshot, Tag $tag) => $screenshot->addTag($tag)),
            CurationOperation::RemoveTags => $this->each($screenshots, $tags, static fn (Screenshot $screenshot, Tag $tag) => $screenshot->removeTag($tag)),
            CurationOperation::AddToAlbum => $this->each($screenshots, [$album], static fn (Screenshot $screenshot, Album $album) => $screenshot->addAlbum($album)),
            CurationOperation::RemoveFromAlbum => $this->each($screenshots, [$album], static fn (Screenshot $screenshot, Album $album) => $screenshot->removeAlbum($album)),
            CurationOperation::SetGame => array_walk($screenshots, fn (Screenshot $screenshot) => $screenshot->setGame($this->game($payload['game'] ?? null))),
            CurationOperation::Publish => array_walk($screenshots, static fn (Screenshot $screenshot) => $screenshot->setPublished(true)),
            CurationOperation::Unpublish => array_walk($screenshots, static fn (Screenshot $screenshot) => $screenshot->setPublished(false)),
            CurationOperation::Delete => array_walk($screenshots, fn (Screenshot $screenshot) => $this->entityManager->remove($screenshot)),
        };
        $this->entityManager->flush();

        return ['tags' => $tags, 'album' => $album];
    }

    public function delete(Screenshot $screenshot): void
    {
        $this->entityManager->remove($screenshot);
        $this->entityManager->flush();
    }

    /**
     * @template T of object
     *
     * @param list<Screenshot>                  $screenshots
     * @param list<T>                           $items
     * @param callable(Screenshot, T): mixed    $change
     */
    private function each(array $screenshots, array $items, callable $change): void
    {
        if ([] === $items) {
            throw new \InvalidArgumentException('Rien à appliquer.');
        }
        foreach ($screenshots as $screenshot) {
            foreach ($items as $item) {
                $change($screenshot, $item);
            }
        }
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<Tag>
     */
    private function tagList(array $values, bool $create = true): array
    {
        $tags = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            $tag = match (true) {
                ctype_digit($value) => $this->tags->find((int) $value),
                $create && str_starts_with($value, self::NEW_PREFIX) && '' !== trim(substr($value, strlen(self::NEW_PREFIX))) => $this->tags->findOrCreatePath(substr($value, strlen(self::NEW_PREFIX))),
                default => null,
            };
            if (null !== $tag && !in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<Album>
     */
    private function albumList(array $values): array
    {
        $albums = [];
        foreach ($values as $value) {
            $album = $this->album($value);
            if (null !== $album && !in_array($album, $albums, true)) {
                $albums[] = $album;
            }
        }

        return $albums;
    }

    private function requiredAlbum(mixed $value, bool $create = true): Album
    {
        return $this->album($value, $create) ?? throw new \InvalidArgumentException('Album introuvable.');
    }

    private function album(mixed $value, bool $create = true): ?Album
    {
        $value = trim((string) $value);
        if (ctype_digit($value)) {
            return $this->albums->find((int) $value);
        }
        $title = trim(substr($value, strlen(self::NEW_PREFIX)));
        if (!$create || !str_starts_with($value, self::NEW_PREFIX) || '' === $title) {
            return null;
        }
        $album = (new Album())->setTitle($title);
        $this->entityManager->persist($album);

        return $album;
    }

    private function game(mixed $value): ?Game
    {
        $value = trim((string) $value);

        return ctype_digit($value) ? $this->games->find((int) $value) : null;
    }
}
