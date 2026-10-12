<?php

namespace App\Gallery;

use App\Tagging\TagName;

final readonly class TagFacet
{
    /**
     * @param list<TagFacet> $children
     * @param list<int>      $screenshotIds
     */
    private function __construct(
        private int $id,
        private string $name,
        private string $slug,
        private array $children,
        private array $screenshotIds,
    ) {
    }

    /**
     * @param array{id: int, name: string, slug: string} $row
     * @param array<int, list<array{id: int, parent_id: ?int, name: string, slug: string}>> $rowsByParent
     * @param array<int, list<int>> $screenshotIdsByTag
     */
    public static function grow(array $row, array $rowsByParent, array $screenshotIdsByTag): ?self
    {
        $children = array_values(array_filter(array_map(
            static fn (array $child): ?self => self::grow($child, $rowsByParent, $screenshotIdsByTag),
            $rowsByParent[$row['id']] ?? [],
        )));
        $screenshotIds = array_values(array_unique(array_merge($screenshotIdsByTag[$row['id']] ?? [], ...array_map(static fn (self $child): array => $child->screenshotIds, $children))));

        return [] === $screenshotIds ? null : new self($row['id'], $row['name'], $row['slug'], $children, $screenshotIds);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function displayName(): string
    {
        return TagName::display($this->name);
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function count(): int
    {
        return count($this->screenshotIds);
    }

    /**
     * @return list<TagFacet>
     */
    public function children(): array
    {
        return $this->children;
    }

    public function hasChildren(): bool
    {
        return [] !== $this->children;
    }

    public function leadsTo(string $slug): bool
    {
        return array_any($this->children, static fn (self $child): bool => $child->slug === $slug || $child->leadsTo($slug));
    }
}
