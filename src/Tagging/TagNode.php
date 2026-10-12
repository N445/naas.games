<?php

namespace App\Tagging;

final class TagNode
{
    /**
     * @var list<TagNode>
     */
    private array $children = [];

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        private readonly int $screenshotCount,
    ) {
    }

    public function adopt(self $child): void
    {
        $this->children[] = $child;
    }

    /**
     * @return list<TagNode>
     */
    public function children(): array
    {
        return $this->children;
    }

    public function hasChildren(): bool
    {
        return [] !== $this->children;
    }

    public function screenshotCount(): int
    {
        return $this->screenshotCount;
    }

    public function totalScreenshotCount(): int
    {
        return $this->screenshotCount + array_sum(array_map(static fn (self $child): int => $child->totalScreenshotCount(), $this->children));
    }

    public function descendantCount(): int
    {
        return count($this->children) + array_sum(array_map(static fn (self $child): int => $child->descendantCount(), $this->children));
    }

    public function matches(string $query): bool
    {
        return '' === $query || str_contains(mb_strtolower($this->name), mb_strtolower($query));
    }

    public function prunedTo(string $query): ?self
    {
        if ($this->matches($query)) {
            return $this;
        }

        $kept = new self($this->id, $this->name, $this->screenshotCount);
        foreach ($this->children as $child) {
            $prunedChild = $child->prunedTo($query);
            if (null !== $prunedChild) {
                $kept->adopt($prunedChild);
            }
        }

        return $kept->hasChildren() ? $kept : null;
    }

    /**
     * @return list<int>
     */
    public function branchIds(): array
    {
        if (!$this->hasChildren()) {
            return [];
        }

        return [$this->id, ...array_merge(...array_map(static fn (self $child): array => $child->branchIds(), $this->children))];
    }
}
