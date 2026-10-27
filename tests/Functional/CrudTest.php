<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Functional;

use MoonShine\Symfony\Tests\Fixtures\Resources\PostResource;
use MoonShine\Symfony\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CrudTest extends WebTestCase
{
    private KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        PostResource::seed();
        $this->client = self::createClient();
    }

    public function testIndexPageListsItems(): void
    {
        $this->client->request('GET', '/admin/resource/post-resource/index-page');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'First post');
        self::assertSelectorTextContains('body', 'Second post');
    }

    public function testMenuContainsResource(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/admin/resource/post-resource/index-page"]');
    }

    public function testDetailPage(): void
    {
        $this->client->request('GET', '/admin/resource/post-resource/detail-page/2');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Second post');
    }

    public function testFormPageForExistingItem(): void
    {
        $this->client->request('GET', '/admin/resource/post-resource/form-page/1');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/admin/resource/post-resource/crud/1/update"]');
        self::assertSelectorExists('input[name="title"][value="First post"]');
    }

    public function testFormPageForNewItem(): void
    {
        $this->client->request('GET', '/admin/resource/post-resource/form-page');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/admin/resource/post-resource/crud"]');
    }

    public function testUnknownResourceIs404(): void
    {
        $this->client->request('GET', '/admin/resource/unknown/index-page');

        self::assertResponseStatusCodeSame(404);
    }

    public function testStoreViaAjax(): void
    {
        $this->client->xmlHttpRequest('POST', '/admin/resource/post-resource/crud', ['title' => 'Created']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Created', PostResource::$items[3]['title'] ?? null);

        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Saved', $json['message'] ?? null);
    }

    public function testUpdateWithRedirect(): void
    {
        $this->client->request('POST', '/admin/resource/post-resource/crud/1/update', ['title' => 'Updated']);

        self::assertResponseRedirects('/admin/resource/post-resource/form-page/1');
        self::assertSame('Updated', PostResource::$items[1]['title']);
    }

    public function testDestroy(): void
    {
        $this->client->xmlHttpRequest('POST', '/admin/resource/post-resource/crud/1/delete');

        self::assertResponseIsSuccessful();
        self::assertArrayNotHasKey(1, PostResource::$items);
    }

    public function testMassDelete(): void
    {
        $this->client->xmlHttpRequest('POST', '/admin/resource/post-resource/crud/mass-delete', ['ids' => [1, 2]]);

        self::assertResponseIsSuccessful();
        self::assertSame([], PostResource::$items);
    }

    public function testAsyncMethod(): void
    {
        $this->client->request('GET', '/admin/method/index-page/post-resource', ['method' => 'ping']);

        self::assertResponseIsSuccessful();
        self::assertSame('pong', $this->client->getResponse()->getContent());
    }

    public function testAsyncMethodRejectsMethodsWithoutAttribute(): void
    {
        $this->client->request('GET', '/admin/method/index-page/post-resource', ['method' => 'massDelete']);

        self::assertResponseStatusCodeSame(500);
    }

    public function testAsyncTableComponent(): void
    {
        $this->client->request('GET', '/admin/component/index-page/post-resource', [
            '_component_name' => 'index-table-post-resource',
        ]);

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<html', $content);
        self::assertStringContainsString('First post', $content);
        self::assertStringContainsString('Second post', $content);
    }

    public function testAsyncTableRow(): void
    {
        $this->client->request('GET', '/admin/component/index-page/post-resource', [
            '_component_name' => 'index-table-post-resource',
            '_key' => 2,
        ]);

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Second post', $content);
        self::assertStringNotContainsString('First post', $content);
    }

    public function testFragmentLoad(): void
    {
        $this->client->request('GET', '/admin/resource/post-resource/index-page', ['_fragment-load' => 'crud-list']);

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<html', $content);
        self::assertStringContainsString('First post', $content);
    }

    public function testReactiveFields(): void
    {
        $this->client->jsonRequest('POST', '/admin/reactive/form-page/post-resource/1', [
            '_component_name' => 'post-resource',
            'values' => ['title' => 'Hello World', 'slug' => ''],
        ]);

        self::assertResponseIsSuccessful();
        $json = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertSame('hello-world', $json['values']['slug'] ?? null);
        self::assertArrayHasKey('slug', $json['fields'] ?? []);
    }
}
