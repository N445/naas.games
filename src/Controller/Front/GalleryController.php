<?php

namespace App\Controller\Front;

use App\Entity\Screenshot;
use App\Gallery\GalleryFacetQuery;
use App\Gallery\GalleryFilters;
use App\Gallery\GalleryQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gallery')]
final class GalleryController extends AbstractController
{
    private const int SIMILAR_LIMIT = 6;

    public function __construct(
        private readonly GalleryQuery $gallery,
        private readonly GalleryFacetQuery $facets,
    ) {
    }

    #[Route('', name: 'APP_GALLERY', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = GalleryFilters::fromQuery($request->query);
        $selected = $this->selected($request->query->getString('screenshot'));

        return $this->render('front/gallery/index.html.twig', [
            'filters' => $filters,
            'page' => $this->gallery->page($filters, $request->query->getInt('page', 1)),
            'facets' => $this->facets->facets($filters),
            'selected' => $selected,
            'similar' => null === $selected ? [] : $this->gallery->similarTo($selected, self::SIMILAR_LIMIT),
        ]);
    }

    #[Route('/results', name: 'APP_GALLERY_RESULTS', methods: ['GET'])]
    public function results(Request $request): Response
    {
        $filters = GalleryFilters::fromQuery($request->query);

        return $this->render('front/gallery/_regions.html.twig', [
            'filters' => $filters,
            'page' => $this->gallery->page($filters, 1),
            'facets' => $this->facets->facets($filters),
        ]);
    }

    #[Route('/more', name: 'APP_GALLERY_MORE', methods: ['GET'])]
    public function more(Request $request): Response
    {
        $filters = GalleryFilters::fromQuery($request->query);

        return $this->render('front/gallery/_page.html.twig', [
            'filters' => $filters,
            'page' => $this->gallery->page($filters, $request->query->getInt('page', 1)),
        ]);
    }

    #[Route('/screenshots/{slug}', name: 'APP_GALLERY_SCREENSHOT', requirements: ['slug' => '[a-z0-9-]+'], methods: ['GET'])]
    public function screenshot(Request $request, string $slug): Response
    {
        $screenshot = $this->selected($slug) ?? throw $this->createNotFoundException();

        return $this->render('front/gallery/_detail.html.twig', [
            'screenshot' => $screenshot,
            'filters' => GalleryFilters::fromQuery($request->query),
            'similar' => $this->gallery->similarTo($screenshot, self::SIMILAR_LIMIT),
        ]);
    }

    private function selected(string $slug): ?Screenshot
    {
        return '' === $slug ? null : $this->gallery->findPublished($slug);
    }
}
