<?php

namespace App\Controller;

use App\Security\OidcClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET'])]
    public function login(Request $request): Response
    {
        $error = $request->query->get('error') === 'oidc'
            ? 'Die Anmeldung über Authentik ist fehlgeschlagen. Bitte versuche es erneut.'
            : null;

        return $this->render('security/login.html.twig', ['error' => $error]);
    }

    #[Route('/login/start', name: 'login_start', methods: ['GET'])]
    public function loginStart(Request $request, OidcClient $oidcClient): Response
    {
        return $this->redirect($oidcClient->authorizationUrl($request->getSession()));
    }
    #[Route('/auth/callback', name: 'oidc_callback', methods: ['GET'])]
    public function callback(): never { throw new \LogicException('The OIDC authenticator handles this route.'); }
    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): never { throw new \LogicException('Logout is handled by the firewall.'); }
}
