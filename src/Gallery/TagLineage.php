<?php

namespace App\Gallery;

use App\Tagging\TagName;

use Doctrine\DBAL\Connection;

final class TagLineage
{
    /**
     * @var list<array{id: int, parent_id: ?int, name: string, slug: string}>|null
     */
    private ?array $rows = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<array{id: int, parent_id: ?int, name: string, slug: string}>
     */
    public function rows(): array
    {
        return $this->rows ??= array_map(self::normalize(...), $this->connection->fetchAllAssociative('SELECT id, parent_id, name, slug FROM tag ORDER BY name'));
    }

    /**
     * @return list<int>
     */
    public function branchIds(string $reference): array
    {
        $root = $this->find($reference);

        return null === $root ? [] : $this->descendantsOf([$root['id']]);
    }

    public function label(string $reference): ?string
    {
        $tag = $this->find($reference);
        if (null === $tag) {
            return null;
        }
        $parentLabel = null === $tag['parent_id'] ? null : $this->label((string) $tag['parent_id']);

        $name = TagName::display($tag['name']);

        return null === $parentLabel ? $name : $parentLabel.' › '.$name;
    }

    /**
     * @return array{id: int, parent_id: ?int, name: string, slug: string}|null
     */
    public function find(string $reference): ?array
    {
        return array_find($this->rows(), static fn (array $row): bool => $row['slug'] === $reference)
            ?? array_find($this->rows(), static fn (array $row): bool => (string) $row['id'] === $reference);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private function descendantsOf(array $ids): array
    {
        $children = array_column(array_filter($this->rows(), static fn (array $row): bool => in_array($row['parent_id'], $ids, true)), 'id');
        $unseen = array_values(array_diff($children, $ids));

        return [] === $unseen ? $ids : $this->descendantsOf([...$ids, ...$unseen]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: int, parent_id: ?int, name: string, slug: string}
     */
    private static function normalize(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'parent_id' => null === $row['parent_id'] ? null : (int) $row['parent_id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
        ];
    }
}
