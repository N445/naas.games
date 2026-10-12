<?php

namespace App\Controller\Front;

use App\Gallery\GalleryQuery;
use App\Statistics\StatisticRecorder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ScreenshotViewController extends AbstractController
{
    private const string SESSION_KEY = 'gallery_viewed_screenshots';

    public function __construct(
        private readonly GalleryQuery $gallery,
        private readonly StatisticRecorder $statistics,
    ) {
    }

    #[Route('/screenshots/{slug}/view', name: 'APP_SCREENSHOT_VIEW', requirements: ['slug' => '[a-z0-9-]+'], methods: ['POST'])]
    public function __invoke(Request $request, string $slug): Response
    {
        if (!$this->isSameOrigin($request)) {
            return new Response(null, Response::HTTP_FORBIDDEN);
        }
        $screenshot = $this->gallery->findPublished($slug) ?? throw $this->createNotFoundException();
        $session = $request->getSession();
        $viewed = $session->get(self::SESSION_KEY, []);
        if (in_array($screenshot->getId(), $viewed, true)) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        $session->set(self::SESSION_KEY, [...$viewed, $screenshot->getId()]);
        $this->statistics->recordView($screenshot);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    private function isSameOrigin(Request $request): bool
    {
        $origin = $request->headers->get('Origin');

        return 'XMLHttpRequest' === $request->headers->get('X-Requested-With')
            && (null === $origin || $origin === $request->getSchemeAndHttpHost());
    }
}
