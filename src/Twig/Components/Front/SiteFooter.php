<?php

namespace App\Twig\Components\Front;

use App\Entity\Link;
use App\Repository\LinkRepository;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final readonly class SiteFooter
{
    public function __construct(private LinkRepository $links)
    {
    }

    /**
     * @return list<Link>
     */
    public function getLinks(): array
    {
        return $this->links->findVisible();
    }
}
