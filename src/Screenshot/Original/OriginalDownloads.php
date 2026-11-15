<?php

namespace App\Screenshot\Original;

use App\Entity\Screenshot;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Attribute\AsTwigFunction;

/**
 * Whether visitors may download the untouched 32:9 capture behind a published final.
 * Can be switched off site-wide (ORIGINAL_DOWNLOADS=0) or per original from the admin.
 */
final readonly class OriginalDownloads
{
    public function __construct(
        #[Autowire('%app.original_downloads%')]
        private bool $enabled,
    ) {
    }

    #[AsTwigFunction('original_downloadable')]
    public function allows(Screenshot $screenshot): bool
    {
        return $this->enabled
            && $screenshot->isPublished()
            && true === $screenshot->getOriginal()?->isDownloadable();
    }
}
