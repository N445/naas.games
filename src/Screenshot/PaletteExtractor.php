<?php

namespace App\Screenshot;

final readonly class PaletteExtractor
{
    private const int SAMPLE_SIZE = 200;
    private const int CLUSTERS = 10;
    private const int KEPT_COLORS = 3;
    private const float MIN_DISTANCE = 45.0;

    public function extract(string $absolutePath): Palette
    {
        $image = new \Imagick($absolutePath);
        $image->thumbnailImage(self::SAMPLE_SIZE, self::SAMPLE_SIZE, true);
        $image->quantizeImage(self::CLUSTERS, \Imagick::COLORSPACE_SRGB, 0, false, false);
        $histogram = $image->getImageHistogram();
        $image->clear();

        usort($histogram, static fn (\ImagickPixel $left, \ImagickPixel $right): int => $right->getColorCount() <=> $left->getColorCount());

        return $this->toPalette(array_slice($this->mergeSimilar($histogram), 0, self::KEPT_COLORS));
    }

    /**
     * @param list<\ImagickPixel> $histogram
     *
     * @return list<array{rgb: array{r: int, g: int, b: int}, count: int}>
     */
    private function mergeSimilar(array $histogram): array
    {
        $groups = [];
        foreach ($histogram as $pixel) {
            $groups = $this->absorb($groups, $pixel->getColor(), $pixel->getColorCount());
        }
        usort($groups, static fn (array $left, array $right): int => $right['count'] <=> $left['count']);

        return $groups;
    }

    /**
     * @param list<array{rgb: array{r: int, g: int, b: int}, count: int}> $groups
     * @param array{r: int, g: int, b: int}                               $rgb
     *
     * @return list<array{rgb: array{r: int, g: int, b: int}, count: int}>
     */
    private function absorb(array $groups, array $rgb, int $count): array
    {
        foreach ($groups as $index => $group) {
            if ($this->distance($group['rgb'], $rgb) < self::MIN_DISTANCE) {
                $groups[$index]['count'] += $count;

                return $groups;
            }
        }
        $groups[] = ['rgb' => $rgb, 'count' => $count];

        return $groups;
    }

    /**
     * @param array{r: int, g: int, b: int} $left
     * @param array{r: int, g: int, b: int} $right
     */
    private function distance(array $left, array $right): float
    {
        return sqrt(($left['r'] - $right['r']) ** 2 + ($left['g'] - $right['g']) ** 2 + ($left['b'] - $right['b']) ** 2);
    }

    /**
     * @param list<array{rgb: array{r: int, g: int, b: int}, count: int}> $groups
     */
    private function toPalette(array $groups): Palette
    {
        $total = array_sum(array_column($groups, 'count')) ?: 1;

        return new Palette(array_map(
            static fn (array $group): PaletteColor => new PaletteColor(vsprintf('#%02x%02x%02x', $group['rgb']), (int) round(100 * $group['count'] / $total)),
            $groups,
        ));
    }
}
