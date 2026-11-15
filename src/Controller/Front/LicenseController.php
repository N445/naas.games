<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LicenseController extends AbstractController
{
    #[Route('/license', name: 'APP_LICENSE', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('front/license/index.html.twig');
    }
}
