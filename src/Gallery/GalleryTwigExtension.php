<?php

namespace App\Gallery;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFilter;

final readonly class GalleryTwigExtension
{
    private const array DATE_STYLES = ['short' => \IntlDateFormatter::SHORT, 'medium' => \IntlDateFormatter::MEDIUM, 'long' => \IntlDateFormatter::LONG];

    public function __construct(private RequestStack $requestStack)
    {
    }

    #[AsTwigFilter('gallery_date')]
    public function date(\DateTimeInterface $date, string $style = 'medium'): string
    {
        $formatter = new \IntlDateFormatter($this->locale(), self::DATE_STYLES[$style] ?? \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE, $date->getTimezone());

        return (string) $formatter->format($date);
    }

    #[AsTwigFilter('gallery_month')]
    public function month(int $month, string $pattern = 'LLLL'): string
    {
        $formatter = new \IntlDateFormatter($this->locale(), \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'UTC', null, $pattern);

        return mb_convert_case((string) $formatter->format(new \DateTimeImmutable(sprintf('2000-%02d-01', $month), new \DateTimeZone('UTC'))), MB_CASE_TITLE);
    }

    #[AsTwigFilter('gallery_color')]
    public function color(string $hex): ColorBucket
    {
        return ColorBucket::ofHex($hex);
    }

    private function locale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en';
    }
}
