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
use MoonShine\Core\Collections\OptimizerCollection;
use MoonShine\Core\Support\CacheAttributes;
use MoonShine\Crud\Collections\Fields;
use MoonShine\Crud\Contracts\Notifications\MoonShineNotificationContract;
use MoonShine\Crud\Contracts\Page\DetailPageContract;
use MoonShine\Crud\Contracts\Page\FormPageContract;
use MoonShine\Crud\Contracts\Page\IndexPageContract;
use MoonShine\Crud\Notifications\MemoryNotification;
use MoonShine\Crud\Pages\DetailPage;
use MoonShine\Crud\Pages\FormPage;
use MoonShine\Crud\Pages\IndexPage;
use MoonShine\Crud\Pages\PageComponents\DefaultDetailComponent;
use MoonShine\Crud\Pages\PageComponents\DefaultForm;
use MoonShine\Crud\Pages\PageComponents\DefaultListComponent;
use MoonShine\MenuManager\MenuAutoloader;
use MoonShine\MenuManager\MenuManager;
use MoonShine\Symfony\Command\InstallCommand;
use MoonShine\Symfony\DependencyInjection\AssetResolver;
use MoonShine\Symfony\DependencyInjection\Configurator;
use MoonShine\Symfony\DependencyInjection\CrudRequest;
use MoonShine\Symfony\DependencyInjection\MoonShine;
use MoonShine\Symfony\DependencyInjection\Request;
use MoonShine\Symfony\DependencyInjection\RequestFactory;
use MoonShine\Symfony\DependencyInjection\Router;
use MoonShine\Symfony\DependencyInjection\Translator;
use MoonShine\Symfony\DependencyInjection\ViewRenderer;
use MoonShine\Symfony\Layout\AppLayout;
use MoonShine\UI\Applies\AppliesRegister;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

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
            ->end();
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
        $this->notifications($builder);
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

        $services
            ->load('MoonShine\\Symfony\\Controller\\', $this->getPath() . '/src/Controller/*')
            ->autowire()
            ->autoconfigure();
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

        $builder
            ->register(IndexPageContract::class, IndexPage::class)
            ->setShared(false)
            ->setPublic(true)
            ->setAutowired(true);

        $builder
            ->register(DefaultListComponent::class)
            ->setPublic(true)
            ->setAutowired(true);

        $builder
            ->register(DetailPageContract::class, DetailPage::class)
            ->setShared(false)
            ->setPublic(true)
            ->setAutowired(true);

        $builder
            ->register(DefaultDetailComponent::class)
            ->setPublic(true)
            ->setAutowired(true);

        $builder
            ->register(FormPageContract::class, FormPage::class)
            ->setShared(false)
            ->setPublic(true)
            ->setAutowired(true);

        $builder
            ->register(DefaultForm::class)
            ->setPublic(true)
            ->setAutowired(true);
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
            ->setPublic(true)
            ->setAutowired(true);
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
            ->register(OptimizerCollectionContract::class, OptimizerCollection::class)
            ->setArgument('$cachePath', '%moonshine.cache_dir%/optimizer.php')
            ->setPublic(true)
            ->setAutowired(true);

        $builder
            ->register(CacheAttributesContract::class, CacheAttributes::class)
            ->setPublic(true)
            ->setAutowired(true);
    }

    private function notifications(ContainerBuilder $builder): void
    {
        $builder
            ->register(MoonShineNotificationContract::class, MemoryNotification::class)
            ->setPublic(true)
            ->setAutowired(true);
    }
}
