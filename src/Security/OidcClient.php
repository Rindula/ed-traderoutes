<?php

namespace App\Security;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class OidcClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $authorizationEndpoint,
        private readonly string $tokenEndpoint,
        private readonly string $userinfoEndpoint,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
    ) {}

    public function authorizationUrl(SessionInterface $session): string
    {
        $state = bin2hex(random_bytes(32));
        $nonce = bin2hex(random_bytes(32));
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $session->set('oidc_state', $state);
        $session->set('oidc_nonce', $nonce);
        $session->set('oidc_code_verifier', $verifier);

        return $this->authorizationEndpoint.'?'.http_build_query([
            'response_type' => 'code', 'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri, 'scope' => 'openid profile email',
            'state' => $state, 'nonce' => $nonce, 'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    /** @return array<string, mixed> */
    public function fetchUser(Request $request, SessionInterface $session): array
    {
        $expectedState = (string) $session->get('oidc_state');
        if ($expectedState === '' || !hash_equals($expectedState, (string) $request->query->get('state'))) {
            throw new \RuntimeException('Invalid OIDC state.');
        }
        $code = $request->query->getString('code');
        if ($code === '') throw new \RuntimeException('Missing OIDC authorization code.');
        $token = $this->httpClient->request('POST', $this->tokenEndpoint, ['body' => [
            'grant_type' => 'authorization_code', 'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret, 'redirect_uri' => $this->redirectUri,
            'code' => $code, 'code_verifier' => $session->get('oidc_code_verifier'),
        ]])->toArray();
        $accessToken = $token['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') throw new \RuntimeException('OIDC token response did not contain an access token.');

        return $this->httpClient->request('GET', $this->userinfoEndpoint, ['auth_bearer' => $accessToken])->toArray();
    }
}
