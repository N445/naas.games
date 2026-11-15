<?php

namespace App\Screenshot;

/**
 * 64-bit difference hash (dHash): robust to re-encoding (JPG ↔ PNG), resizing and light retouching,
 * but not to cropping — a final and its 32:9 original are deliberately far apart.
 */
final class PerceptualHash
{
    private const int WIDTH = 9;
    private const int HEIGHT = 8;

    public static function compute(string $path): ?string
    {
        try {
            $image = new \Imagick();
            $image->setOption('jpeg:size', '256x256');
            $image->readImage($path.'[0]');
            $image->transformImageColorspace(\Imagick::COLORSPACE_GRAY);
            $image->resizeImage(self::WIDTH, self::HEIGHT, \Imagick::FILTER_BOX, 1, false);
            $pixels = $image->exportImagePixels(0, 0, self::WIDTH, self::HEIGHT, 'I', \Imagick::PIXEL_CHAR);
            $image->clear();
        } catch (\ImagickException) {
            return null;
        }

        $bits = '';
        for ($y = 0; $y < self::HEIGHT; ++$y) {
            for ($x = 0; $x < self::WIDTH - 1; ++$x) {
                $bits .= $pixels[$y * self::WIDTH + $x] < $pixels[$y * self::WIDTH + $x + 1] ? '1' : '0';
            }
        }

        return sprintf('%08x%08x', bindec(substr($bits, 0, 32)), bindec(substr($bits, 32)));
    }

    public static function distance(string $left, string $right): int
    {
        [$leftHigh, $leftLow] = self::halves($left);
        [$rightHigh, $rightLow] = self::halves($right);

        return self::popcount($leftHigh ^ $rightHigh) + self::popcount($leftLow ^ $rightLow);
    }

    /**
     * @return array{int, int} the hash as two 32-bit integers, for repeated comparisons
     */
    public static function halves(string $hash): array
    {
        return [(int) hexdec(substr($hash, 0, 8)), (int) hexdec(substr($hash, 8, 8))];
    }

    public static function popcount(int $value): int
    {
        $value -= ($value >> 1) & 0x55555555;
        $value = ($value & 0x33333333) + (($value >> 2) & 0x33333333);
        $value = ($value + ($value >> 4)) & 0x0F0F0F0F;

        return (($value * 0x01010101) & 0xFFFFFFFF) >> 24;
    }
}
