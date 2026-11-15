<?php

namespace App\Controller\Admin;

use App\Entity\Screenshot;
use App\Repository\ScreenshotRepository;
use App\Screenshot\Duplicate\DuplicateFinder;
use App\Screenshot\Duplicate\ScreenshotMerger;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[AdminRoute('/duplicates', name: 'duplicates')]
final class DuplicateController extends AbstractController
{
    private const string CSRF_TOKEN_ID = 'screenshot_duplicates';

    public function __construct(
        private readonly DuplicateFinder $finder,
        private readonly ScreenshotMerger $merger,
        private readonly ScreenshotRepository $screenshots,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[AdminRoute('/', name: 'index', options: ['methods' => ['GET']])]
    public function index(): Response
    {
        return $this->render('admin/duplicate/index.html.twig', [
            'groups' => $this->finder->groups(),
            'maxDistance' => DuplicateFinder::MAX_DISTANCE,
            'missingHashes' => $this->screenshots->count(['perceptualHash' => null]),
            'csrfTokenId' => self::CSRF_TOKEN_ID,
        ]);
    }

    #[AdminRoute('/resolve', name: 'resolve', options: ['methods' => ['POST']])]
    public function resolve(Request $request): Response
    {
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $group = $this->screenshots->findByIds(array_map(intval(...), $request->request->all('screenshots')));
        if (count($group) < 2) {
            throw $this->createNotFoundException('Groupe de doublons introuvable.');
        }

        match ($request->request->has('delete') ? 'delete' : $request->request->getString('operation')) {
            'merge' => $this->merge($group, $request->request->getInt('keep')),
            'dismiss' => $this->dismiss($group),
            'delete' => $this->delete($group, $request->request->getInt('delete')),
            default => throw $this->createNotFoundException('Opération inconnue.'),
        };

        return $this->redirectToRoute('APP_ADMIN_duplicates_index');
    }

    /**
     * @param list<Screenshot> $group
     */
    private function merge(array $group, int $keeperId): void
    {
        $keeper = $this->pick($group, $keeperId);
        $title = $keeper->getTitle();
        $merged = $this->merger->merge($keeper, $group);
        $this->addFlash('success', sprintf('%d doublon(s) fusionné(s) dans « %s » (tags, albums et statistiques conservés).', $merged, $title));
    }

    /**
     * @param list<Screenshot> $group
     */
    private function dismiss(array $group): void
    {
        $this->merger->dismiss($group);
        $this->addFlash('success', 'Groupe écarté : ces screenshots ne seront plus proposés comme doublons.');
    }

    /**
     * @param list<Screenshot> $group
     */
    private function delete(array $group, int $screenshotId): void
    {
        $screenshot = $this->pick($group, $screenshotId);
        $title = $screenshot->getTitle();
        $this->entityManager->remove($screenshot);
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('« %s » supprimé.', $title));
    }

    /**
     * @param list<Screenshot> $group
     */
    private function pick(array $group, int $id): Screenshot
    {
        foreach ($group as $screenshot) {
            if ($screenshot->getId() === $id) {
                return $screenshot;
            }
        }

        throw $this->createNotFoundException('Ce screenshot ne fait pas partie du groupe.');
    }
}
