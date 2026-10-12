<?php

namespace App\Gallery;

enum ColorBucket: string
{
    case Red = 'red';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case Green = 'green';
    case Cyan = 'cyan';
    case Blue = 'blue';
    case Purple = 'purple';
    case Pink = 'pink';
    case Neutral = 'neutral';

    private const int MINIMUM_CHROMA = 14;
    private const int MINIMUM_SHARE = 8;

    public static function ofHex(string $hex): self
    {
        [$red, $green, $blue] = array_map(hexdec(...), str_split(substr(ltrim($hex, '#'), 0, 6), 2));
        $chroma = max($red, $green, $blue) - min($red, $green, $blue);
        if ($chroma < self::MINIMUM_CHROMA) {
            return self::Neutral;
        }

        return self::ofHue(self::hue($red, $green, $blue, $chroma));
    }

    /**
     * @param list<array{hex: string, share: int}> $palette
     *
     * @return list<self>
     */
    public static function forPalette(array $palette): array
    {
        $prominent = array_filter($palette, static fn (array $color): bool => $color['share'] >= self::MINIMUM_SHARE);

        return array_values(array_unique(array_map(static fn (array $color): self => self::ofHex($color['hex']), $prominent), SORT_REGULAR));
    }

    /**
     * @param list<array{hex: string, share: int}>|string|null $palette
     *
     * @return list<self>
     */
    public static function forStoredPalette(array|string|null $palette): array
    {
        $decoded = is_string($palette) ? json_decode($palette, true) : $palette;

        return self::forPalette(is_array($decoded) ? $decoded : []);
    }

    public function swatch(): string
    {
        return match ($this) {
            self::Red => '#e5383b',
            self::Orange => '#ff7a1a',
            self::Yellow => '#f5c518',
            self::Green => '#3fb950',
            self::Cyan => '#22c3c9',
            self::Blue => '#3b82f6',
            self::Purple => '#8b5cf6',
            self::Pink => '#ec4899',
            self::Neutral => '#8a8f98',
        };
    }

    private static function hue(int $red, int $green, int $blue, int $chroma): float
    {
        $hue = match (max($red, $green, $blue)) {
            $red => fmod(($green - $blue) / $chroma, 6),
            $green => ($blue - $red) / $chroma + 2,
            default => ($red - $green) / $chroma + 4,
        };

        return fmod($hue * 60 + 360, 360);
    }

    private static function ofHue(float $hue): self
    {
        return match (true) {
            $hue < 15, $hue >= 345 => self::Red,
            $hue < 45 => self::Orange,
            $hue < 70 => self::Yellow,
            $hue < 160 => self::Green,
            $hue < 200 => self::Cyan,
            $hue < 255 => self::Blue,
            $hue < 290 => self::Purple,
            default => self::Pink,
        };
    }
}
