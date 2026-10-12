<?php

namespace App\Ai;

use App\Entity\Tag;
use App\Repository\TagRepository;

final readonly class TagSuggestion
{
    private const int MAX_TAGS = 4;

    /**
     * @param list<string> $tags
     */
    private function __construct(
        private ?string $gameSlug,
        private array $tags,
    ) {
    }

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $tags = array_map(static fn (string $tag): string => implode(Tag::PATH_SEPARATOR, TagRepository::segments($tag)), $data['tags'] ?? []);

        return new self(
            TagPrompt::UNKNOWN_GAME === $data['game'] ? null : $data['game'],
            array_slice(array_values(array_unique(array_filter($tags))), 0, self::MAX_TAGS),
        );
    }

    /**
     * @param list<string> $tags
     */
    public static function of(?string $gameSlug, array $tags): self
    {
        return new self($gameSlug, array_slice($tags, 0, self::MAX_TAGS));
    }

    public function isEmpty(): bool
    {
        return [] === $this->tags;
    }

    public function gameSlug(): ?string
    {
        return $this->gameSlug;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return $this->tags;
    }
}
