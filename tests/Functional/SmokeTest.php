<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Functional;

use MoonShine\Symfony\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SmokeTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testDashboardRenders(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Hello from dashboard');
    }

    public function testLoginPageRenders(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }
}
