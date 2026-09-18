<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageIsRenderedWithoutStartingOidcFlow(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'ED Trade Routes');
        self::assertSelectorTextContains('a', 'Mit Authentik anmelden');
    }

    public function testLoginPageDisplaysOidcError(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login?error=oidc');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('p[role="alert"]', 'Anmeldung über Authentik ist fehlgeschlagen');
    }
}
