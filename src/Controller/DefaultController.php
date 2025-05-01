<?php

namespace App\Controller;

use App\Service\Flickr\PhotosProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DefaultController extends AbstractController
{
    #[Route('/', name: 'APP_HOMEPAGE')]
    public function index(PhotosProvider $photosProvider): Response
    {
        return $this->render('default/index.html.twig', [
            'photos' => $photosProvider->getPhotos()
        ]);
    }
}
