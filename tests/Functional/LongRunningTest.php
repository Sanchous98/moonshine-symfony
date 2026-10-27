<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Functional;

use MoonShine\Contracts\Core\DependencyInjection\RouterContract;
use MoonShine\Symfony\Tests\Fixtures\Resources\PostResource;
use MoonShine\Symfony\Tests\Fixtures\TestKernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class LongRunningTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testMenuAndAssetsSurviveKernelReset(): void
    {
        PostResource::seed();

        // a worker (RoadRunner, FrankenPHP) handles requests with the same kernel,
        // the kernel resets the services before every following request
        $kernel = self::bootKernel();

        foreach ([1, 2, 3] as $number) {
            $request = Request::create('/admin');
            $response = $kernel->handle($request);
            $crawler = new Crawler((string) $response->getContent(), 'http://localhost/admin');
            $kernel->terminate($request, $response);

            self::assertSame(200, $response->getStatusCode(), "request $number");
            self::assertCount(1, $crawler->filter('a.menu-link[href="/admin/resource/post-resource/index-page"]'), "menu, request $number");
            self::assertCount(1, $crawler->filter('a.menu-link[href="/admin/page/reports"]'), "custom menu item, request $number");
            self::assertCount(1, $crawler->filter('link[href*="vendor/moonshine/assets/main.css"]'), "css, request $number");
            self::assertCount(1, $crawler->filter('link[href*="/css/admin-custom.css"]'), "custom css, request $number");
        }
    }

    public function testRouterKeepsArrayQueryParameters(): void
    {
        self::bootKernel();

        /** @var RouterContract $router */
        $router = self::getContainer()->get(RouterContract::class);

        $url = $router->to('resource.page', [
            'resourceUri' => 'post-resource',
            'pageUri' => 'index-page',
            'filter' => ['title' => 'First', 'tags' => [1, 2]],
            'sort' => '-id',
        ]);

        self::assertSame(
            '/admin/resource/post-resource/index-page?filter%5Btitle%5D=First&filter%5Btags%5D%5B0%5D=1&filter%5Btags%5D%5B1%5D=2&sort=-id',
            $url,
        );
    }
}
