<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Fixtures;

use MoonShine\Symfony\MoonShineBundle;
use MoonShine\Symfony\Tests\Fixtures\Pages\Dashboard;
use MoonShine\Symfony\Tests\Fixtures\Pages\LoginPage;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use MoonShine\Symfony\Tests\Fixtures\Doctrine\ArticleEventSubscriber;
use MoonShine\Symfony\Tests\Fixtures\Doctrine\ArticleFormPage;
use MoonShine\Symfony\Tests\Fixtures\Doctrine\ArticleResource;
use MoonShine\Symfony\Tests\Fixtures\Doctrine\ArticleType;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();

        if ($this->environment === 'auth') {
            yield new SecurityBundle();
        }

        if ($this->environment === 'doctrine') {
            yield new DoctrineBundle();
        }

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
            'property_access' => true,
            'form' => true,
            'notifier' => true,
            'validation' => true,
            'translator' => ['default_path' => '%kernel.project_dir%/translations'],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        $container->extension('moonshine', [
            'namespace' => 'MoonShine\Symfony\Tests\Fixtures',
            'prefix' => $this->environment === 'prefixed' ? '/backoffice/panel' : '/admin',
            'auth' => ['enabled' => $this->environment === 'auth'],
            'locales' => ['en' => 'English', 'de' => 'Deutsch'],
            'storage' => [
                'disks' => [
                    'public' => ['root' => '%kernel.cache_dir%/storage', 'url' => '/storage'],
                    'memory' => ['service' => 'test.memory_filesystem', 'url' => 'https://cdn.example.com'],
                ],
            ],
            'pages' => [
                'dashboard' => Dashboard::class,
                'login' => LoginPage::class,
            ],
        ]);

        if ($this->environment === 'auth') {
            $container->extension('security', [
                'firewalls' => [
                    'moonshine' => [
                        'pattern' => '^/admin',
                        'provider' => 'users',
                        'form_login' => [
                            'login_path' => 'moonshine.login',
                            'check_path' => 'moonshine.authenticate',
                            'default_target_path' => 'moonshine.index',
                            'username_parameter' => 'username',
                            'password_parameter' => 'password',
                            'enable_csrf' => true,
                            'csrf_parameter' => '_token',
                            'csrf_token_id' => 'moonshine',
                        ],
                        'logout' => ['path' => 'moonshine.logout', 'target' => 'moonshine.login'],
                        'remember_me' => ['secret' => '%kernel.secret%', 'remember_me_parameter' => 'remember'],
                    ],
                    'main' => ['lazy' => true, 'provider' => 'users'],
                ],
                'password_hashers' => [InMemoryUser::class => 'plaintext'],
                'providers' => [
                    'users' => [
                        'memory' => [
                            'users' => [
                                'admin' => ['password' => 'secret', 'roles' => ['ROLE_ADMIN']],
                                'editor' => ['password' => 'secret', 'roles' => ['ROLE_USER']],
                            ],
                        ],
                    ],
                ],
            ]);
        }

        if ($this->environment === 'doctrine') {
            $container->extension('doctrine', [
                'dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/test.db'],
                'orm' => [
                    'mappings' => [
                        'Tests' => [
                            'type' => 'attribute',
                            'dir' => __DIR__ . '/Doctrine',
                            'prefix' => 'MoonShine\Symfony\Tests\Fixtures\Doctrine',
                            'is_bundle' => false,
                        ],
                    ],
                ],
            ]);

            $container->services()
                ->defaults()->autowire()->autoconfigure()
                ->set(ArticleResource::class)
                ->set(ArticleFormPage::class)
                ->set(ArticleEventSubscriber::class)
                ->set(ArticleType::class)->tag('form.type');
        }

        $container->services()
            ->set('logger', NullLogger::class)
            ->set('test.memory_filesystem', Filesystem::class)
                ->args([inline_service(InMemoryFilesystemAdapter::class)])
                ->public()
            ->alias('test.notifier', 'notifier')->public();

        $container->services()
            ->defaults()->autowire()->autoconfigure()
            ->load('MoonShine\\Symfony\\Tests\\Fixtures\\Pages\\', __DIR__ . '/Pages/')
            ->load('MoonShine\\Symfony\\Tests\\Fixtures\\Resources\\', __DIR__ . '/Resources/')
            ->load('MoonShine\\Symfony\\Tests\\Fixtures\\Layouts\\', __DIR__ . '/Layouts/');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'moonshine.routes');

        // like the Symfony skeleton, must not register MoonShine controllers twice
        if (class_exists(AttributeServicesLoader::class)) {
            $routes->import('routing.controllers');
        }
    }
}
