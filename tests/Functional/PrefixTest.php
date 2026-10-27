<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Functional;

use MoonShine\Symfony\Tests\Fixtures\Resources\PostResource;
use MoonShine\Symfony\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PrefixTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testRoutesUseConfiguredPrefix(): void
    {
        PostResource::seed();
        $client = self::createClient(['environment' => 'prefixed']);

        $client->request('GET', '/backoffice/panel');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/backoffice/panel/resource/post-resource/index-page"]');

        $client->request('GET', '/backoffice/panel/resource/post-resource/form-page/1');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/backoffice/panel/resource/post-resource/crud/1/update"]');

        $client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(404);
    }
}
