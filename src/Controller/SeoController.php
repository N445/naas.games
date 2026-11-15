<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Screenshot;
use App\Repository\AlbumRepository;
use App\Repository\ScreenshotRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SeoController extends AbstractController
{
    private const array LOCALES = ['en', 'fr'];
    private const array PAGES = ['APP_HOMEPAGE', 'APP_GALLERY', 'APP_LINKS', 'APP_LICENSE'];
    private const array FRONT_PREFIXES = ['', '/fr'];

    #[Route('/sitemap.xml', name: 'APP_SITEMAP', methods: ['GET'], format: 'xml')]
    public function sitemap(ScreenshotRepository $screenshots, AlbumRepository $albums): Response
    {
        $published = $screenshots->findBy(['published' => true], ['createdAt' => 'DESC']);
        $gamesWithScreenshots = [];
        foreach ($published as $screenshot) {
            if (null !== $game = $screenshot->getGame()) {
                $gamesWithScreenshots[$game->getSlug()] = $game;
            }
        }

        $response = $this->render('seo/sitemap.xml.twig', [
            'pages' => self::PAGES,
            'locales' => self::LOCALES,
            'screenshots' => $published,
            'albums' => array_filter(
                $albums->findBy(['published' => true], ['title' => 'ASC']),
                static fn (Album $album): bool => $album->getScreenshots()->exists(static fn (int $key, Screenshot $screenshot): bool => $screenshot->isPublished()),
            ),
            'games' => array_values($gamesWithScreenshots),
            'lastmod' => array_reduce($published, static fn (?\DateTimeInterface $latest, Screenshot $screenshot) => max($latest, $screenshot->getUpdatedAt()), null),
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }

    #[Route('/robots.txt', name: 'APP_ROBOTS', methods: ['GET'], format: 'txt')]
    public function robots(): Response
    {
        // The admin path is deliberately not listed (it is secret); login, admin and fragments send X-Robots-Tag: noindex instead.
        $disallowed = [];
        foreach (self::FRONT_PREFIXES as $prefix) {
            foreach (['/gallery/results', '/gallery/more', '/gallery/screenshots/', '/screenshots/'] as $path) {
                $disallowed[] = 'Disallow: '.$prefix.$path;
            }
        }

        $content = implode("\n", [
            'User-agent: *',
            ...$disallowed,
            '',
            'Sitemap: '.$this->generateUrl('APP_SITEMAP', [], 0),
            '',
        ]);

        return new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
