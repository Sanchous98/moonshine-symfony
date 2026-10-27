<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Fixtures;

use MoonShine\Symfony\MoonShineBundle;
use MoonShine\Symfony\Tests\Fixtures\Pages\Dashboard;
use MoonShine\Symfony\Tests\Fixtures\Pages\LoginPage;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new MoonShineBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/moonshine-symfony-tests/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/moonshine-symfony-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'translator' => ['default_path' => '%kernel.project_dir%/translations'],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        $container->extension('moonshine', [
            'namespace' => 'MoonShine\Symfony\Tests\Fixtures',
            'prefix' => $this->environment === 'prefixed' ? '/backoffice/panel' : '/admin',
            'pages' => [
                'dashboard' => Dashboard::class,
                'login' => LoginPage::class,
            ],
        ]);

        $container->services()
            ->set('logger', NullLogger::class);

        $container->services()
            ->defaults()->autowire()->autoconfigure()
            ->load('MoonShine\\Symfony\\Tests\\Fixtures\\Pages\\', __DIR__ . '/Pages/')
            ->load('MoonShine\\Symfony\\Tests\\Fixtures\\Resources\\', __DIR__ . '/Resources/');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'moonshine.routes');
    }
}
