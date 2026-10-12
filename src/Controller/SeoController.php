<?php

namespace App\Controller;

use App\Repository\ScreenshotRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SeoController extends AbstractController
{
    private const array LOCALES = ['en', 'fr'];
    private const array PAGES = ['APP_HOMEPAGE', 'APP_GALLERY', 'APP_LINKS'];

    #[Route('/sitemap.xml', name: 'APP_SITEMAP', methods: ['GET'], format: 'xml')]
    public function sitemap(ScreenshotRepository $screenshots): Response
    {
        $response = $this->render('seo/sitemap.xml.twig', [
            'pages' => self::PAGES,
            'locales' => self::LOCALES,
            'screenshots' => $screenshots->findBy(['published' => true], ['createdAt' => 'DESC']),
        ]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }

    #[Route('/robots.txt', name: 'APP_ROBOTS', methods: ['GET'], format: 'txt')]
    public function robots(#[Autowire('%app.admin_path%')] string $adminPath): Response
    {
        $content = implode("\n", [
            'User-agent: *',
            'Disallow: '.$adminPath,
            'Disallow: /login',
            'Disallow: /_components',
            'Disallow: /gallery/results',
            'Disallow: /gallery/more',
            'Disallow: /fr/gallery/results',
            'Disallow: /fr/gallery/more',
            '',
            'Sitemap: '.$this->generateUrl('APP_SITEMAP', [], 0),
            '',
        ]);

        return new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
