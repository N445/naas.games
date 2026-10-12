<?php

namespace App\Screenshot;

final readonly class Palette
{
    private const array IDEAL_SHARES = [60, 30, 10];
    private const int WORST_DEVIATION = 80;

    /**
     * @param list<PaletteColor> $colors
     */
    public function __construct(private array $colors)
    {
    }

    /**
     * @param list<array{hex: string, share: int}> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(array_map(PaletteColor::fromArray(...), $data));
    }

    /**
     * @return list<array{hex: string, share: int}>
     */
    public function toArray(): array
    {
        return array_map(static fn (PaletteColor $color): array => $color->toArray(), $this->colors);
    }

    /**
     * @return list<PaletteColor>
     */
    public function colors(): array
    {
        return $this->colors;
    }

    public function balanceScore(): int
    {
        $deviation = 0;
        foreach ($this->colors as $rank => $color) {
            $deviation += $color->deviationFrom(self::IDEAL_SHARES[$rank] ?? 0);
        }

        return (int) round(100 * max(0, 1 - $deviation / self::WORST_DEVIATION));
    }
}
