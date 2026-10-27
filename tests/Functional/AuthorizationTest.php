<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Functional;

use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\Action;
use MoonShine\Symfony\Tests\Fixtures\Resources\PostResource;
use MoonShine\Symfony\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Write endpoints must respect can() and hasAction() of the resource, not only the hidden buttons
 */
final class AuthorizationTest extends WebTestCase
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

    private function send(string $method, string $uri, array $data = []): void
    {
        if ($this->client->getCookieJar()->get('XSRF-TOKEN') === null) {
            $this->client->request('GET', '/admin');
        }

        $token = (string) $this->client->getCookieJar()->get('XSRF-TOKEN')?->getValue();

        $this->client->xmlHttpRequest($method, $uri, $data, server: ['HTTP_X_XSRF_TOKEN' => $token]);
    }

    public function testCreateRequiresAbility(): void
    {
        PostResource::$denied = [Ability::CREATE];

        $this->send('POST', '/admin/resource/post-resource/crud', ['title' => 'Sneaky']);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(2, PostResource::$items);
    }

    public function testCreateRequiresAction(): void
    {
        PostResource::$actions = [Action::VIEW, Action::UPDATE];

        $this->send('POST', '/admin/resource/post-resource/crud', ['title' => 'Sneaky']);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(2, PostResource::$items);
    }

    public function testUpdateRequiresAbility(): void
    {
        PostResource::$denied = [Ability::UPDATE];

        $this->send('POST', '/admin/resource/post-resource/crud/1/update', ['title' => 'Changed']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('First post', PostResource::$items[1]['title']);
    }

    public function testDeleteRequiresAbilityAndAction(): void
    {
        PostResource::$denied = [Ability::DELETE];
        $this->send('POST', '/admin/resource/post-resource/crud/1/delete');
        self::assertResponseStatusCodeSame(403);

        PostResource::$denied = [];
        PostResource::$actions = [Action::VIEW, Action::UPDATE, Action::CREATE];
        $this->send('POST', '/admin/resource/post-resource/crud/1/delete');
        self::assertResponseStatusCodeSame(403);

        self::assertArrayHasKey(1, PostResource::$items);
    }

    public function testMassDeleteRequiresAbility(): void
    {
        PostResource::$denied = [Ability::MASS_DELETE];

        $this->send('POST', '/admin/resource/post-resource/crud/mass-delete', ['ids' => [1, 2]]);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(2, PostResource::$items);
    }

    public function testDeniedResponseIsAToast(): void
    {
        PostResource::$denied = [Ability::UPDATE];

        $this->send('POST', '/admin/resource/post-resource/crud/1/update', ['title' => 'Changed']);

        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('Access Denied.', $json['message'] ?? null);
    }

    public function testRefusedPagesAnswer403(): void
    {
        PostResource::$denied = [Ability::VIEW_ANY, Ability::UPDATE];

        $this->client->request('GET', '/admin/resource/post-resource/index-page');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/resource/post-resource/form-page/1');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAsyncComponentRequiresPageAccess(): void
    {
        PostResource::$denied = [Ability::VIEW_ANY];

        $this->client->request('GET', '/admin/component/index-page/post-resource', [
            '_component_name' => 'index-table-post-resource',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertStringNotContainsString('First post', (string) $this->client->getResponse()->getContent());
    }

    public function testReactiveRequiresPageAccess(): void
    {
        PostResource::$denied = [Ability::UPDATE];

        $this->send('POST', '/admin/reactive/form-page/post-resource/1', [
            '_component_name' => 'post-resource',
            'values' => ['title' => 'Hello World'],
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
