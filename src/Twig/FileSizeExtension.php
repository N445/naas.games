<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFilter;

final readonly class FileSizeExtension
{
    private const array UNITS = [
        'fr' => ['o', 'Ko', 'Mo', 'Go', 'To'],
        'en' => ['B', 'KB', 'MB', 'GB', 'TB'],
    ];

    private const array DECIMAL_SEPARATORS = ['fr' => ',', 'en' => '.'];

    public function __construct(private RequestStack $requestStack)
    {
    }

    #[AsTwigFilter('file_size')]
    public function format(int $bytes): string
    {
        $locale = 'en' === $this->requestStack->getCurrentRequest()?->getLocale() ? 'en' : 'fr';
        $units = self::UNITS[$locale];
        $exponent = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;
        $value = number_format($bytes / 1024 ** $exponent, $exponent > 0 ? 1 : 0, self::DECIMAL_SEPARATORS[$locale], ' ');

        return sprintf('%s %s', $value, $units[$exponent]);
    }
}
