<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Functional;

use MoonShine\Symfony\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LocaleTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testLocaleSwitcher(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin/login');
        self::assertSelectorTextContains('body', 'Welcome to MoonShine!');
        self::assertSelectorExists('html[lang="en"]');

        $client->request('GET', '/admin/login', ['_locale' => 'de']);
        self::assertSelectorTextContains('body', 'Willkommen bei MoonShine!');
        self::assertSelectorExists('html[lang="de"]');

        // the choice is remembered
        $client->request('GET', '/admin/login');
        self::assertSelectorExists('html[lang="de"]');

        // unknown locales are ignored
        $client->request('GET', '/admin/login', ['_locale' => 'xx']);
        self::assertSelectorExists('html[lang="de"]');
    }

    public function testSwitcherIsRendered(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        self::assertSelectorExists('a[href*="_locale=de"]');
    }
}
