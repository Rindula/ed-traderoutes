<?php

namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class OidcAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly OidcClient $oidcClient, private readonly UserRepository $users) {}
    public function supports(Request $request): ?bool { return $request->attributes->get('_route') === 'oidc_callback'; }

    public function authenticate(Request $request): Passport
    {
        try {
            $claims = $this->oidcClient->fetchUser($request, $request->getSession());
        } catch (\Throwable $exception) {
            throw new AuthenticationException('OIDC authentication failed.', 0, $exception);
        }
        $subject = $claims['sub'] ?? null;
        if (!is_string($subject) || $subject === '') {
            throw new AuthenticationException('OIDC response did not contain a subject.');
        }
        $email = is_string($claims['email'] ?? null) ? $claims['email'] : null;
        $name = is_string($claims['name'] ?? null) ? $claims['name'] : ($email ?? $subject);
        return new SelfValidatingPassport(new UserBadge($subject, fn (): object => $this->users->findOrCreateFromOidc($subject, $email, $name)));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response { return new RedirectResponse('/status'); }
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response { return new RedirectResponse('/login?error=oidc'); }
    public function start(Request $request, ?AuthenticationException $authException = null): Response { return new RedirectResponse('/login'); }
}
