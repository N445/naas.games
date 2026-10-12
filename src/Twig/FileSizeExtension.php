<?php

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

final class FileSizeExtension
{
    private const array UNITS = ['o', 'Ko', 'Mo', 'Go', 'To'];

    #[AsTwigFilter('file_size')]
    public function format(int $bytes): string
    {
        $exponent = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count(self::UNITS) - 1) : 0;

        return sprintf('%s %s', number_format($bytes / 1024 ** $exponent, $exponent > 0 ? 1 : 0, ',', ' '), self::UNITS[$exponent]);
    }
}
