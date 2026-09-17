<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardControllerTest extends WebTestCase
{
    public function testDashboardPageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/dashboard');

        self::assertResponseRedirects('/login');
    }

    public function testDashboardJsonRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/dashboard');

        self::assertResponseRedirects('/login');
    }
}
