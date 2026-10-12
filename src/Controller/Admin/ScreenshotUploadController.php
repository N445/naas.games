<?php

namespace App\Controller\Admin;

use App\Entity\Album;
use App\Entity\Game;
use App\Repository\AlbumRepository;
use App\Repository\GameRepository;
use App\Screenshot\ScreenshotImporter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AdminRoute('/screenshots/upload', name: 'screenshot_upload')]
final class ScreenshotUploadController extends AbstractController
{
    private const string CSRF_TOKEN_ID = 'screenshot_upload';

    public function __construct(
        private readonly AlbumRepository $albums,
        private readonly GameRepository $games,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[AdminRoute('/', name: 'index', options: ['methods' => ['GET']])]
    public function index(Request $request): Response
    {
        return $this->render('admin/screenshot/upload.html.twig', [
            'albums' => $this->albums->findAllByTitle(),
            'games' => $this->games->findAllByName(),
            'selectedAlbum' => $request->query->getInt('album'),
            'csrfTokenId' => self::CSRF_TOKEN_ID,
        ]);
    }

    #[AdminRoute('/album', name: 'album', options: ['methods' => ['POST']])]
    public function createAlbum(Request $request): JsonResponse
    {
        $this->assertCsrfToken($request);
        $title = trim($request->getPayload()->getString('title'));
        if ('' === $title) {
            return $this->json(['error' => 'Le titre de l\'album est obligatoire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $album = (new Album())->setTitle($title);
        $this->entityManager->persist($album);
        $this->entityManager->flush();

        return $this->json(['id' => $album->getId(), 'title' => $album->getTitle()]);
    }

    #[AdminRoute('/file', name: 'file', options: ['methods' => ['POST']])]
    public function uploadFile(Request $request, ValidatorInterface $validator, ScreenshotImporter $importer): JsonResponse
    {
        $this->assertCsrfToken($request);
        $upload = $request->files->get('file');
        $violations = $validator->validate($upload, new Image(maxSize: '100M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif']));
        if (!$upload instanceof UploadedFile || count($violations) > 0) {
            return $this->json(['error' => count($violations) > 0 ? $violations->get(0)->getMessage() : 'Aucun fichier reçu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $importer->import($upload, $this->findAlbum($request->request->getString('album')), $this->findGame($request->request->getString('game')));
        $template = $request->request->getBoolean('compact') ? 'admin/screenshot/_uploaded_compact.html.twig' : 'admin/screenshot/_uploaded.html.twig';

        return $this->json([
            'duplicate' => $result->duplicate,
            'html' => $this->renderView($result->duplicate ? 'admin/screenshot/_uploaded_compact.html.twig' : $template, ['screenshot' => $result->screenshot, 'duplicate' => $result->duplicate]),
        ]);
    }

    private function findAlbum(string $albumId): ?Album
    {
        return ctype_digit($albumId) ? $this->albums->find((int) $albumId) : null;
    }

    private function findGame(string $gameId): ?Game
    {
        return ctype_digit($gameId) ? $this->games->find((int) $gameId) : null;
    }

    private function assertCsrfToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
