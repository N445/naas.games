<?php

namespace App\Gallery;

final readonly class GalleryFacets
{
    /**
     * @param list<FacetOption>     $games
     * @param list<FacetOption>     $albums
     * @param list<YearFacet>       $years
     * @param array<string, int>    $colorCounts
     * @param list<TagFacet>        $tags
     * @param array<string, string> $labels
     */
    public function __construct(
        private array $games,
        private array $albums,
        private array $years,
        private array $colorCounts,
        private array $tags,
        private array $labels,
    ) {
    }

    /**
     * @return list<FacetOption>
     */
    public function games(): array
    {
        return $this->games;
    }

    /**
     * @return list<FacetOption>
     */
    public function albums(): array
    {
        return $this->albums;
    }

    /**
     * @return list<YearFacet>
     */
    public function years(): array
    {
        return $this->years;
    }

    public function year(string $year): ?YearFacet
    {
        return array_find($this->years, static fn (YearFacet $facet): bool => $facet->year() === $year);
    }

    /**
     * @return list<ColorBucket>
     */
    public function colors(): array
    {
        return ColorBucket::cases();
    }

    public function colorCount(ColorBucket $color): int
    {
        return $this->colorCounts[$color->value] ?? 0;
    }

    /**
     * @return list<TagFacet>
     */
    public function tags(): array
    {
        return $this->tags;
    }

    public function label(string $key, string $value): string
    {
        return $this->labels[$key] ?? $value;
    }
}
