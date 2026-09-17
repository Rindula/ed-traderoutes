<?php

namespace App\Controller;

use App\Security\OidcClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET'])]
    public function login(Request $request, OidcClient $oidcClient): RedirectResponse { return new RedirectResponse($oidcClient->authorizationUrl($request->getSession())); }
    #[Route('/auth/callback', name: 'oidc_callback', methods: ['GET'])]
    public function callback(): never { throw new \LogicException('The OIDC authenticator handles this route.'); }
    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): never { throw new \LogicException('Logout is handled by the firewall.'); }
}
