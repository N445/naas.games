<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'APP_LOGIN')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('APP_ADMIN');
        }

        return $this->render('@EasyAdmin/page/login.html.twig', [
            'page_title' => 'Naas Games',
            'favicon_path' => '/favicon/favicon-96x96.png',
            'action' => $this->generateUrl('APP_LOGIN'),
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'csrf_token_intention' => 'authenticate',
            'target_path' => $this->generateUrl('APP_ADMIN'),
            'username_label' => 'Identifiant',
            'password_label' => 'Mot de passe',
            'sign_in_label' => 'Se connecter',
        ]);
    }

    #[Route('/logout', name: 'APP_LOGOUT')]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the logout key of the firewall.');
    }
}
