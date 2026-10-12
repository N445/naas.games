<?php

namespace App\Controller;

use App\Repository\LinkRepository;
use App\Repository\ScreenshotRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LinksController extends AbstractController
{
    #[Route('/links', name: 'APP_LINKS', methods: ['GET'])]
    public function index(LinkRepository $links, ScreenshotRepository $screenshots): Response
    {
        return $this->render('links/index.html.twig', [
            'links' => $links->findVisible(),
            'background' => $screenshots->findRandomPublished(),
        ]);
    }
}
