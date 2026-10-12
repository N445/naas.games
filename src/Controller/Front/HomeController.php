<?php

namespace App\Controller\Front;

use App\Entity\Link;
use App\Homepage\HomepageCatalog;
use App\Repository\LinkRepository;
use App\Repository\HomepageSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly HomepageSettingsRepository $settings,
        private readonly HomepageCatalog $catalog,
        private readonly LinkRepository $links,
    ) {
    }

    #[Route('/', name: 'APP_HOMEPAGE', methods: ['GET'])]
    public function index(): Response
    {
        $settings = $this->settings->current();

        return $this->render('front/home/index.html.twig', [
            'settings' => $settings,
            'hero' => $this->catalog->heroScreenshots($settings->getHeroScreenshotIds()),
            'statistics' => $settings->isShowStatistics() ? $this->catalog->statistics() : [],
            'latest' => $settings->isShowLatest() ? $this->catalog->latestScreenshots($settings->getLatestLimit()) : [],
            'albums' => $settings->isShowAlbums() ? $this->catalog->featuredAlbums($settings->getFeaturedAlbumIds()) : [],
            'social_urls' => array_map(static fn (Link $link): string => (string) $link->getUrl(), $this->links->findVisible()),
        ]);
    }
}
