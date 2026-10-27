<?php

declare(strict_types=1);

namespace MoonShine\Symfony\DependencyInjection;

use Composer\InstalledVersions;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\Factory as FactoryContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use MoonShine\Contracts\Core\DependencyInjection\ViewRendererContract;
use MoonShine\Core\Core;
use MoonShine\Support\AlpineJs;
use MoonShine\Symfony\Security\Csrf;
use RuntimeException;

final class ViewRenderer implements ViewRendererContract
{
    private ?Factory $factory = null;

    /**
     * @param list<string> $paths additional view paths
     */
    public function __construct(
        private readonly string $cacheDir,
        private readonly array $paths = [],
        private readonly ?string $uiPath = null,
    ) {}

    public function render(string $view, array $data = []): Renderable
    {
        return $this->getFactory()->make($view, $data);
    }

    public function reset(): void
    {
        $this->factory?->flushState();
    }

    private function getFactory(): Factory
    {
        if ($this->factory !== null) {
            return $this->factory;
        }

        $views = $this->getUiPath() . '/resources/views';
        $components = $views . '/components';

        $files = new Filesystem();
        $files->ensureDirectoryExists($this->cacheDir);

        $compiler = new BladeCompiler(files: $files, cachePath: $this->cacheDir);

        $compiler->componentNamespace('MoonShine\UI\Components', 'moonshine');
        $compiler->anonymousComponentNamespace($components, 'moonshine');

        $compiler->directive(
            'csrf',
            static fn (): string => '<?php echo ' . Core::class . '::getInstance()->getContainer(' . Csrf::class . '::class)?->field(); ?>',
        );
        $compiler->directive(
            'defineEvent',
            static fn (string $e): string => '<?php echo ' . AlpineJs::class . "::eventBlade($e); ?>",
        );
        $compiler->directive(
            'defineEventWhen',
            static fn (string $e): string => '<?php echo ' . AlpineJs::class . "::eventBladeWhen($e); ?>",
        );

        $engines = new EngineResolver();
        $engines->register('blade', static fn (): CompilerEngine => new CompilerEngine($compiler, $files));

        $factory = new Factory(
            engines: $engines,
            finder: new FileViewFinder($files, [...$this->paths, $views, $components], ['blade.php']),
            events: new Dispatcher(),
        );

        $factory->addNamespace('moonshine', [$views, $components]);
        $factory->addNamespace('__components', $this->cacheDir);

        // Blade components resolve the factory and the application through the global Laravel container
        $container = Container::getInstance();
        $factory->setContainer($container);
        $container->instance(FactoryContract::class, $factory);
        $container->instance('view', $factory);
        $container->instance('blade.compiler', $compiler);

        if (! $container->bound(Application::class)) {
            $container->instance(Application::class, new class () {
                public function getNamespace(): string
                {
                    return 'App\\';
                }
            });
        }

        return $this->factory = $factory;
    }

    private function getUiPath(): string
    {
        $path = $this->uiPath ?? (InstalledVersions::isInstalled('moonshine/ui')
            ? InstalledVersions::getInstallPath('moonshine/ui')
            : null);

        if ($path === null || ! is_dir($path)) {
            throw new RuntimeException('Package "moonshine/ui" is not installed.');
        }

        return realpath($path) ?: $path;
    }
}
