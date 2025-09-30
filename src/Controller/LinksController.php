<?php

namespace App\Controller;

use App\Repository\Link\LinkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LinksController extends AbstractController
{
    #[Route('/links', name: 'app_links')]
    public function index(): Response
    {
        return $this->render('links/index.html.twig', [
        ]);
    }
}
