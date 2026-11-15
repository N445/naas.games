<?php

namespace App\Screenshot\Original;

/**
 * A darktable export keeps the capture's file name, optionally suffixed with a version (`_01`, `_02`…)
 * and possibly in another format: this key is what a final and its 32:9 original have in common.
 */
final class OriginalName
{
    private const float PANORAMIC_RATIO = 32 / 9;
    private const float RATIO_TOLERANCE = 0.12;

    public static function key(string $fileName): string
    {
        $base = pathinfo(str_replace('\\', '/', $fileName), PATHINFO_FILENAME);
        $base = (string) preg_replace('/_\d{1,3}$/', '', $base);

        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $base)));
    }

    /**
     * Darktable version of a capture (`name`, `name_01`, `name_02`…), whatever the format.
     */
    public static function version(string $fileName): string
    {
        return mb_strtolower(pathinfo(str_replace('\\', '/', $fileName), PATHINFO_FILENAME));
    }

    public static function isPanoramic(int $width, int $height): bool
    {
        return $height > 0 && abs($width / $height - self::PANORAMIC_RATIO) <= self::RATIO_TOLERANCE;
    }
}
