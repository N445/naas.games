<?php

namespace App\Screenshot;

final readonly class CaptureDate
{
    private const string FILENAME_PATTERN = '/(\d{4})[-_](\d{2})[-_](\d{2})[ _-](\d{2})[-_](\d{2})[-_](\d{2})/';

    public static function guess(string $absolutePath, string $originalName): ?\DateTimeImmutable
    {
        return self::fromFilename($originalName) ?? self::fromExif($absolutePath);
    }

    private static function fromExif(string $absolutePath): ?\DateTimeImmutable
    {
        $exif = @exif_read_data($absolutePath);
        $original = is_array($exif) ? ($exif['DateTimeOriginal'] ?? null) : null;

        return \DateTimeImmutable::createFromFormat('Y:m:d H:i:s', (string) $original) ?: null;
    }

    private static function fromFilename(string $originalName): ?\DateTimeImmutable
    {
        if (!preg_match(self::FILENAME_PATTERN, $originalName, $parts)) {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', vsprintf('%s-%s-%s %s:%s:%s', array_slice($parts, 1))) ?: null;
    }
}
