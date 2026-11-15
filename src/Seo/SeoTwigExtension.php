<?php

namespace App\Seo;

use App\Entity\Album;
use App\Entity\Link;
use App\Repository\AlbumRepository;
use App\Repository\LinkRepository;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class SeoTwigExtension
{
    /** @var list<string>|null */
    private ?array $sameAs = null;

    public function __construct(
        private readonly AlbumRepository $albums,
        private readonly LinkRepository $links,
    ) {
    }

    #[AsTwigFunction('seo_album')]
    public function album(string $slug): ?Album
    {
        return '' === $slug ? null : $this->albums->findOneBy(['slug' => $slug, 'published' => true]);
    }

    /**
     * Public profiles of the author, used as schema.org Person "sameAs".
     *
     * @return list<string>
     */
    #[AsTwigFunction('seo_same_as')]
    public function sameAs(): array
    {
        return $this->sameAs ??= array_values(array_filter(array_map(
            static fn (Link $link): string => (string) $link->getUrl(),
            $this->links->findVisible(),
        ), static fn (string $url): bool => str_starts_with($url, 'https://')));
    }

    /**
     * Turns already-escaped HTML (e.g. a rendered block) back into plain text for JSON-LD.
     */
    #[AsTwigFilter('seo_plain')]
    public function plain(?string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));
    }
}
