<?php

namespace App\Controller;

use App\Repository\Link\LinkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LinksController extends AbstractController
{
    #[Route('/links', name: 'app_links')]
    public function index(LinkRepository $linkRepository): Response
    {
        $links = $linkRepository->createQueryBuilder('l')
            ->orderBy('l.display_order', 'ASC')
            ->getQuery()
            ->getResult();
        return $this->render('links/index.html.twig', [
            'links' => $links,
        ]);
    }
}
