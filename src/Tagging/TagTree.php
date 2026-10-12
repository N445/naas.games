<?php

namespace App\Tagging;

final readonly class TagTree
{
    /**
     * @param list<TagNode> $roots
     */
    private function __construct(private array $roots)
    {
    }

    /**
     * @param list<array{id: int, parent_id: ?int, name: string, screenshots: int}> $rows
     */
    public static function fromRows(array $rows): self
    {
        $nodes = [];
        foreach ($rows as $row) {
            $nodes[$row['id']] = new TagNode((int) $row['id'], $row['name'], (int) $row['screenshots']);
        }

        $roots = [];
        foreach ($rows as $row) {
            $parent = null === $row['parent_id'] ? null : ($nodes[$row['parent_id']] ?? null);
            null === $parent ? $roots[] = $nodes[$row['id']] : $parent->adopt($nodes[$row['id']]);
        }

        return new self($roots);
    }

    public function filteredBy(string $query): self
    {
        return new self(array_values(array_filter(array_map(static fn (TagNode $root): ?TagNode => $root->prunedTo($query), $this->roots))));
    }

    /**
     * @return list<TagNode>
     */
    public function roots(): array
    {
        return $this->roots;
    }

    public function isEmpty(): bool
    {
        return [] === $this->roots;
    }

    public function count(): int
    {
        return count($this->roots) + array_sum(array_map(static fn (TagNode $root): int => $root->descendantCount(), $this->roots));
    }

    /**
     * @return list<int>
     */
    public function branchIds(): array
    {
        return array_merge([], ...array_map(static fn (TagNode $root): array => $root->branchIds(), $this->roots));
    }
}
