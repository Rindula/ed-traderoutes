<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthControllerTest extends WebTestCase
{
    public function testLiveEndpointIsAvailableWithoutDependencies(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health/live');

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok'], json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testStatusRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/status');

        self::assertResponseRedirects('/login');
    }
}
