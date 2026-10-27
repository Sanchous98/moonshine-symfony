<?php

declare(strict_types=1);

namespace MoonShine\Symfony;

use MoonShine\AssetManager\AssetManager;
use MoonShine\ColorManager\ColorManager;
use MoonShine\ColorManager\Palettes\NeutralPalette;
use MoonShine\Contracts\AssetManager\AssetManagerContract;
use MoonShine\Contracts\AssetManager\AssetResolverContract;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\Core\DependencyInjection\AppliesRegisterContract;
use MoonShine\Contracts\Core\DependencyInjection\CacheAttributesContract;
use MoonShine\Contracts\Core\DependencyInjection\ConfiguratorContract;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\DependencyInjection\OptimizerCollectionContract;
use MoonShine\Contracts\Core\DependencyInjection\RequestContract;
use MoonShine\Contracts\Core\DependencyInjection\RouterContract;
use MoonShine\Contracts\Core\DependencyInjection\TranslatorContract;
use MoonShine\Contracts\Core\DependencyInjection\ViewRendererContract;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\Core\ResourceContract;
use MoonShine\Contracts\MenuManager\MenuAutoloaderContract;
use MoonShine\Contracts\MenuManager\MenuManagerContract;
use MoonShine\Core\Support\CacheAttributes;
use MoonShine\Crud\Collections\Fields;
use MoonShine\Crud\Contracts\Notifications\MoonShineNotificationContract;
use MoonShine\Crud\Contracts\Page\DetailPageContract;
use MoonShine\Crud\Contracts\Page\FormPageContract;
use MoonShine\Crud\Contracts\Page\IndexPageContract;
use MoonShine\Crud\Pages\DetailPage;
use MoonShine\Crud\Pages\FormPage;
use MoonShine\Crud\Pages\IndexPage;
use MoonShine\Crud\Pages\PageComponents\DefaultDetailComponent;
use MoonShine\Crud\Pages\PageComponents\DefaultForm;
use MoonShine\Crud\Pages\PageComponents\DefaultListComponent;
use MoonShine\MenuManager\MenuAutoloader;
use MoonShine\MenuManager\MenuManager;
use MoonShine\Symfony\Applies\FileApply;
use MoonShine\Symfony\Command\InstallCommand;
use MoonShine\Symfony\DependencyInjection\AssetResolver;
use MoonShine\Symfony\EventSubscriber\ExceptionSubscriber;
use MoonShine\Symfony\EventSubscriber\LocaleSubscriber;
use MoonShine\Symfony\DependencyInjection\Configurator;
use MoonShine\Symfony\DependencyInjection\CrudRequest;
use MoonShine\Symfony\DependencyInjection\MoonShine;
use MoonShine\Symfony\DependencyInjection\Request;
use MoonShine\Symfony\DependencyInjection\RequestFactory;
use MoonShine\Symfony\DependencyInjection\Router;
use MoonShine\Symfony\DependencyInjection\Translator;
use MoonShine\Symfony\DependencyInjection\ViewRenderer;
use MoonShine\Symfony\Layout\AppLayout;
use MoonShine\Symfony\Notifications\MoonShineChannel;
use MoonShine\Symfony\Notifications\NotificationService;
use MoonShine\Symfony\Notifications\NotificationStorage;
use MoonShine\Symfony\Optimizer\OptimizerCacheWarmer;
use MoonShine\Symfony\Optimizer\OptimizerCollection;
use MoonShine\Symfony\Security\AccessSubscriber;
use MoonShine\Symfony\Security\Auth;
use MoonShine\Symfony\Security\Csrf;
use MoonShine\Symfony\Security\CsrfSubscriber;
use MoonShine\Symfony\Storage\StorageRegistry;
use MoonShine\Symfony\Form\FormProcessor;
use MoonShine\Symfony\Validation\LaravelValidator;
use MoonShine\UI\Fields\File;
use MoonShine\UI\Applies\AppliesRegister;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Notifier\Channel\ChannelInterface;

final class MoonShineBundle extends AbstractBundle
{
    protected string $name = 'moonshine';

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition
            ->rootNode()
            ->children()
                ->scalarNode('namespace')->defaultValue('App\MoonShine')->end()
                ->scalarNode('prefix')->defaultValue('/admin')->end()
                ->scalarNode('title')->defaultValue('MoonShine')->end()
                ->scalarNode('logo')->defaultNull()->end()
                ->scalarNode('logo_small')->defaultNull()->end()
                ->scalarNode('layout')->defaultValue(AppLayout::class)->end()
                ->scalarNode('palette')->defaultValue(NeutralPalette::class)->end()
                ->scalarNode('locale')->defaultValue('en')->end()
                ->arrayNode('locales')
                    ->info('Locales of the switcher, a list of codes or code => label')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('pages')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('dashboard')->defaultValue('App\MoonShine\Pages\Dashboard')->end()
                        ->scalarNode('login')->defaultValue('App\MoonShine\Pages\LoginPage')->end()
                    ->end()
                ->end()
                ->arrayNode('forms')
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('auth')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('role')
                            ->info('Role required to access the panel, null allows any authenticated user')
                            ->defaultValue('ROLE_ADMIN')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('storage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('default')->defaultValue('public')->end()
                        ->arrayNode('disks')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('root')
                                        ->info('Local directory of the disk')
                                        ->defaultNull()
                                    ->end()
                                    ->scalarNode('service')
                                        ->info('Flysystem FilesystemOperator service, e.g. a league/flysystem-bundle storage')
                                        ->defaultNull()
                                    ->end()
                                    ->scalarNode('url')
                                        ->info('Public URL of the disk, the Flysystem "public_url" is used when null')
                                        ->defaultNull()
                                    ->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn (array $disk): bool => ($disk['root'] === null) === ($disk['service'] === null))
                                    ->thenInvalid('A disk needs either "root" or "service".')
                                ->end()
                            ->end()
                            ->defaultValue([
                                'public' => [
                                    'root' => '%kernel.project_dir%/public/storage',
                                    'url' => '/storage',
                                ],
                            ])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('notifications')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('pool')
                            ->info('PSR-6 cache pool service keeping the notifications, use a persistent one in production')
                            ->defaultValue('cache.app')
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('csrf')
                    ->info('Protect admin requests with CSRF tokens, requires symfony/security-csrf')
                    ->defaultTrue()
                ->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('framework')) {
            $builder->prependExtensionConfig('framework', ['csrf_protection' => true, 'validation' => ['enabled' => true]]);
        }
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CompilerPass());
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('moonshine.prefix', '/' . trim((string) $config['prefix'], '/'));
        $builder->setParameter('moonshine.cache_dir', '%kernel.cache_dir%/moonshine');

        $this->commands($container);
        $this->routing($container);
        $this->core($config, $builder);
        $this->request($builder);
        $this->layouts($container, $builder);
        $this->view($builder);
        $this->fields($builder);
        $this->assets($config, $builder);
        $this->menu($builder);
        $this->cache($builder);
        $this->notifications($config, $builder);
        $this->security($config, $builder);
        $this->storage($config, $builder);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function security(array $config, ContainerBuilder $builder): void
    {
        /** @var array{enabled: bool, role: ?string} $auth */
        $auth = $config['auth'];

        $builder
            ->register(Auth::class)
            ->setArguments([
                $auth['enabled'],
                $auth['role'],
                new Reference('security.helper', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);

        $builder
            ->register(Csrf::class)
            ->setArguments([
                $config['csrf']
                    ? new Reference('security.csrf.token_manager', ContainerBuilder::NULL_ON_INVALID_REFERENCE)
                    : null,
            ])
            ->setPublic(true);

        $builder
            ->register(AccessSubscriber::class)
            ->setAutowired(true)
            ->addTag('kernel.event_subscriber');

        $builder
            ->register(CsrfSubscriber::class)
            ->setAutowired(true)
            ->addTag('kernel.event_subscriber');

        $builder
            ->register(ExceptionSubscriber::class)
            ->addTag('kernel.event_subscriber');

        $builder
            ->register(LocaleSubscriber::class)
            ->setArguments([new Reference(ConfiguratorContract::class), $config['locale']])
            ->addTag('kernel.event_subscriber');
    }

    private function commands(ContainerConfigurator $container): void
    {
        $container
            ->services()
            ->set(InstallCommand::class)
            ->args(['%kernel.project_dir%'])
            ->tag('console.command');
    }

    private function routing(ContainerConfigurator $container): void
    {
        $services = $container->services();

        $services
            ->set(Routing::class)
            ->args(['%moonshine.prefix%'])
            ->tag('routing.loader');

        // not autoconfigured: #[Route] would register them in "routing.controllers" without the prefix
        $services
            ->load('MoonShine\\Symfony\\Controller\\', $this->getPath() . '/src/Controller/*')
            ->autowire()
            ->tag('controller.service_arguments');
    }

    private function request(ContainerBuilder $builder): void
    {
        $builder
            ->register(RequestContract::class, Request::class)
            ->setFactory([RequestFactory::class, 'create'])
            ->addArgument(new Reference('request_stack'))
            ->setShared(false)
            ->setPublic(true);

        $builder
            ->register(CrudRequestContract::class, CrudRequest::class)
            ->setPublic(true)
            ->setAutowired(true);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function core(array $config, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(ResourceContract::class)
            ->addTag('moonshine.resource');

        $builder->registerForAutoconfiguration(PageContract::class)
            ->addTag('moonshine.page');

        $builder->register(Instances::class)
            ->addArgument(new TaggedIteratorArgument('moonshine.resource'))
            ->addArgument(new TaggedIteratorArgument('moonshine.page'));

        $builder
            ->register(CoreContract::class, MoonShine::class)
            ->setArgument('$container', new Reference('service_container'))
            ->setArgument('$cache', new Reference('moonshine.cache'))
            ->setConfigurator([new Reference(Instances::class), 'register'])
            ->addTag('kernel.reset', ['method' => 'reset'])
            ->setPublic(true)
            ->setAutowired(true);

        $builder
            ->register(RouterContract::class, Router::class)
            ->setPublic(true)
            ->setAutowired(true);

        $builder
            ->register(ConfiguratorContract::class, Configurator::class)
            ->setArgument('$container', new Reference('service_container'))
            ->setArgument('$items', $config)
            ->setArgument('$cacheDir', '%moonshine.cache_dir%')
            ->setPublic(true)
            ->setAutowired(true);

        $builder
            ->register(TranslatorContract::class, Translator::class)
            ->setPublic(true)
            ->setAutowired(true);
    }

    private function layouts(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            ->load('MoonShine\\Symfony\\Layout\\', $this->getPath() . '/src/Layout/*')
            ->autowire()
            ->autoconfigure()
            ->public();

        $pages = [
            IndexPageContract::class => IndexPage::class,
            FormPageContract::class => FormPage::class,
            DetailPageContract::class => DetailPage::class,
        ];

        foreach ($pages as $contract => $class) {
            $builder
                ->register($class)
                ->setShared(false)
                ->setPublic(true)
                ->setAutowired(true);

            $builder->setAlias($contract, $class)->setPublic(true);
        }

        foreach ([DefaultListComponent::class, DefaultDetailComponent::class, DefaultForm::class] as $component) {
            $builder
                ->register($component)
                ->setPublic(true)
                ->setAutowired(true);
        }
    }

    private function view(ContainerBuilder $builder): void
    {
        $builder
            ->register(ViewRendererContract::class, ViewRenderer::class)
            ->setArgument('$cacheDir', '%moonshine.cache_dir%/views')
            ->addTag('kernel.reset', ['method' => 'reset'])
            ->setPublic(true);
    }

    private function fields(ContainerBuilder $builder): void
    {
        $builder
            ->register(FieldsContract::class, Fields::class)
            ->setPublic(true)
            ->setShared(false)
            ->setAutowired(true);

        $builder
            ->register(AppliesRegisterContract::class, AppliesRegister::class)
            ->addMethodCall('type', ['fields'])
            ->addMethodCall('for', [ResourceContract::class])
            ->addMethodCall('push', [[File::class => FileApply::class]])
            ->setPublic(true)
            ->setAutowired(true);

        $builder
            ->register(FileApply::class)
            ->setPublic(true);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function assets(array $config, ContainerBuilder $builder): void
    {
        $builder
            ->register(AssetManagerContract::class, AssetManager::class)
            ->addTag('kernel.reset', ['method' => 'flushState'])
            ->setPublic(true)
            ->setAutowired(true);

        $builder
            ->register(AssetResolverContract::class, AssetResolver::class)
            ->setArgument('$requestStack', new Reference('request_stack'))
            ->setPublic(true);

        $builder->register('moonshine.palette', (string) $config['palette']);

        $builder
            ->register(ColorManagerContract::class, ColorManager::class)
            ->setArgument('$palette', new Reference('moonshine.palette'))
            ->setPublic(true);
    }

    private function menu(ContainerBuilder $builder): void
    {
        $builder
            ->register(MenuManagerContract::class, MenuManager::class)
            ->setPublic(true)
            ->setAutowired(true);

        $builder
            ->register(MenuAutoloaderContract::class, MenuAutoloader::class)
            ->setPublic(true)
            ->setAutowired(true);
    }

    private function cache(ContainerBuilder $builder): void
    {
        $builder
            ->register('moonshine.cache', Psr16Cache::class)
            ->setArguments([new Reference('cache.app')]);

        $builder
            ->register(OptimizerCollection::class)
            ->setArgument('$cachePath', '%moonshine.cache_dir%/optimizer.php')
            ->setArgument('$config', new Reference(ConfiguratorContract::class))
            ->setPublic(true);

        $builder->setAlias(OptimizerCollectionContract::class, OptimizerCollection::class)->setPublic(true);

        $builder
            ->register(OptimizerCacheWarmer::class)
            ->setArgument('$optimizer', new Reference(OptimizerCollection::class))
            ->addTag('kernel.cache_warmer');

        $builder
            ->register(CacheAttributesContract::class, CacheAttributes::class)
            ->setPublic(true)
            ->setAutowired(true);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function storage(array $config, ContainerBuilder $builder): void
    {
        /** @var array{default: string, disks: array<string, array{root: ?string, service: ?string, url: ?string}>} $storage */
        $storage = $config['storage'];

        $services = [];

        foreach ($storage['disks'] as $disk) {
            if (($disk['service'] ?? null) !== null) {
                $services[$disk['service']] = new Reference($disk['service']);
            }
        }

        $builder
            ->register(StorageRegistry::class)
            ->setArguments([
                $storage['disks'],
                $storage['default'],
                ServiceLocatorTagPass::register($builder, $services),
            ])
            ->setPublic(true);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function notifications(array $config, ContainerBuilder $builder): void
    {
        // Laravel style string rules are an optional fallback
        if (class_exists(\Illuminate\Validation\Factory::class)) {
            $builder
                ->register(LaravelValidator::class)
                ->setAutowired(true);
        }

        $builder
            ->register(FormProcessor::class)
            ->setArguments([
                new Reference('validator'),
                new Reference('form.factory', ContainerBuilder::NULL_ON_INVALID_REFERENCE),
                new Reference(LaravelValidator::class, ContainerBuilder::NULL_ON_INVALID_REFERENCE),
            ]);

        /** @var array{pool: string} $notifications */
        $notifications = $config['notifications'];

        $builder
            ->register(NotificationStorage::class)
            ->setArguments([new Reference($notifications['pool'])]);

        $builder
            ->register(NotificationService::class)
            ->setAutowired(true)
            ->setPublic(true);

        $builder->setAlias(MoonShineNotificationContract::class, NotificationService::class)->setPublic(true);

        if (interface_exists(ChannelInterface::class)) {
            $builder
                ->register(MoonShineChannel::class)
                ->setArguments([new Reference(MoonShineNotificationContract::class)])
                ->addTag('notifier.channel', ['channel' => MoonShineChannel::NAME]);
        }
    }
}
