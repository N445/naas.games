<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Screenshot;
use App\Entity\Tag;
use App\Gallery\GalleryFilters;
use App\Repository\ScreenshotRepository;
use App\Screenshot\Curation\CurationOperation;
use App\Screenshot\Curation\ScreenshotCurator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Editing endpoints behind the admin path, called by the gallery when an admin is signed in.
 * Every call needs the session CSRF token in the X-CSRF-Token header.
 */
#[Route('%app.admin_path%/curation', name: 'APP_CURATION_')]
#[IsGranted('ROLE_ADMIN')]
final class CurationController extends AbstractController
{
    public const string CSRF_TOKEN_ID = 'gallery_curation';
    private const int MAX_BATCH = 500;

    public function __construct(
        private readonly ScreenshotRepository $screenshots,
        private readonly ScreenshotCurator $curator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/screenshots/{id}', name: 'UPDATE', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(Request $request, Screenshot $screenshot): JsonResponse
    {
        $this->assertCsrfToken($request);
        try {
            $this->curator->update($screenshot, $request->getPayload()->all());
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'published' => $screenshot->isPublished(),
            'albums' => array_map(self::albumChoice(...), $screenshot->getAlbums()->getValues()),
            'tags' => array_map(self::tagChoice(...), $screenshot->getTags()->getValues()),
            'cards' => $this->cards([$screenshot], $request),
        ]);
    }

    #[Route('/screenshots/{id}/delete', name: 'DELETE', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Screenshot $screenshot): JsonResponse
    {
        $this->assertCsrfToken($request);
        $slug = (string) $screenshot->getSlug();
        $this->curator->delete($screenshot);

        return $this->json(['removed' => [$slug]]);
    }

    #[Route('/batch', name: 'BATCH', methods: ['POST'])]
    public function batch(Request $request): JsonResponse
    {
        $this->assertCsrfToken($request);
        $payload = $request->getPayload();
        $operation = CurationOperation::tryFrom($payload->getString('operation'));
        $ids = array_slice(array_filter(array_map(intval(...), $payload->all('ids'))), 0, self::MAX_BATCH);
        $screenshots = $this->screenshots->findByIds($ids);
        if (null === $operation || [] === $screenshots) {
            return $this->json(['error' => 'Sélection ou action invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $slugs = array_map(static fn (Screenshot $screenshot): string => (string) $screenshot->getSlug(), $screenshots);
        try {
            $resolved = $this->curator->apply($operation, $screenshots, $payload->all());
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (CurationOperation::Delete === $operation) {
            return $this->json(['count' => count($screenshots), 'removed' => $slugs]);
        }

        return $this->json([
            'count' => count($screenshots),
            'tags' => [] === $resolved['tags'] ? null : array_map(self::tagChoice(...), $resolved['tags']),
            'album' => null === $resolved['album'] ? null : self::albumChoice($resolved['album']),
            'cards' => $this->cards($screenshots, $request),
        ]);
    }

    /**
     * @return array{id: int, label: string}
     */
    private static function albumChoice(Album $album): array
    {
        return ['id' => (int) $album->getId(), 'label' => (string) $album->getTitle()];
    }

    /**
     * @return array{id: int, label: string}
     */
    private static function tagChoice(Tag $tag): array
    {
        return ['id' => (int) $tag->getId(), 'label' => $tag->getPath()];
    }

    /**
     * Re-rendered gallery cards, so the grid reflects the change without reloading.
     *
     * @param list<Screenshot> $screenshots
     *
     * @return array<string, string>
     */
    private function cards(array $screenshots, Request $request): array
    {
        $locale = $request->headers->get('X-Gallery-Locale');
        if (in_array($locale, ['en', 'fr'], true)) {
            $request->setLocale($locale);
            $this->urlGenerator->getContext()->setParameter('_locale', $locale);
        }
        parse_str(ltrim((string) $request->headers->get('X-Gallery-Query'), '?'), $query);
        $filters = GalleryFilters::fromQuery(new InputBag(array_filter($query, is_string(...))));
        $cards = [];
        foreach ($screenshots as $screenshot) {
            $cards[(string) $screenshot->getSlug()] = $this->renderView('front/gallery/_card.html.twig', ['screenshot' => $screenshot, 'filters' => $filters, 'eager' => false]);
        }

        return $cards;
    }

    private function assertCsrfToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
