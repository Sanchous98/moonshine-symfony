<?php

declare(strict_types=1);

namespace MoonShine\Symfony\DependencyInjection;

use Illuminate\Support\Arr;
use MoonShine\ColorManager\Palettes\NeutralPalette;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\Contracts\Core\DependencyInjection\ConfiguratorContract;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Contracts\UI\FormContract;
use MoonShine\Contracts\UI\LayoutContract;
use MoonShine\Symfony\Layout\AppLayout;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;

final class Configurator implements ConfiguratorContract
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private array $items = [],
        private readonly string $cacheDir = '',
    ) {}

    public function getNamespace(string $path = '', ?string $base = null): string
    {
        $base ??= $this->getString('namespace', 'App\MoonShine');

        $path = trim(str_replace('/', '\\', $path), '\\');

        return $path === '' ? $base : $base . '\\' . $path;
    }

    public function getTitle(): string
    {
        return $this->getString('title', 'MoonShine');
    }

    public function getLogo(bool $small = false): ?string
    {
        $logo = $this->get($small ? 'logo_small' : 'logo');

        return \is_string($logo) && $logo !== '' ? $logo : null;
    }

    public function isUseProfile(): bool
    {
        return $this->isAuthEnabled();
    }

    public function isAuthEnabled(): bool
    {
        return (bool) $this->get('auth.enabled', false);
    }

    public function isUseNotifications(): bool
    {
        return (bool) $this->get('notifications.enabled', true);
    }

    public function getLayout(): string
    {
        $layout = $this->get('layout');

        return \is_string($layout) && is_subclass_of($layout, LayoutContract::class)
            ? $layout
            : AppLayout::class;
    }

    public function getForm(string $name, string $default, mixed ...$parameters): FormBuilderContract
    {
        $class = $this->get("forms.$name", $default);

        if (! \is_string($class) || ! is_subclass_of($class, FormContract::class)) {
            $class = $default;
        }

        return (new $class(...$parameters))();
    }

    public function getPage(string $name, string $default, mixed ...$parameters): PageContract
    {
        $class = $this->get("pages.$name", $default);

        if (! \is_string($class) || ! is_subclass_of($class, PageContract::class)) {
            $class = $default;
        }

        if (! $this->container->has($class)) {
            throw new RuntimeException(\sprintf(
                'MoonShine page "%s" is not registered as a service. Make sure it is autoconfigured or tagged with "moonshine.page".',
                $class,
            ));
        }

        $page = $this->container->get($class);

        if (! $page instanceof PageContract) {
            throw new RuntimeException(\sprintf('Service "%s" is not a MoonShine page.', $class));
        }

        return $page;
    }

    /**
     * @return list<class-string<PageContract>>
     */
    public function getPages(): array
    {
        $pages = $this->get('pages', []);

        return \is_array($pages) ? array_values(array_filter(
            $pages,
            static fn (mixed $page): bool => \is_string($page) && is_subclass_of($page, PageContract::class),
        )) : [];
    }

    /**
     * @return array<array-key, string>
     */
    public function getLocales(): array
    {
        $locales = $this->get('locales', []);

        return \is_array($locales) ? $locales : [];
    }

    /**
     * Current locale of the admin panel
     */
    public function getLocale(): string
    {
        $requestStack = $this->container->has('request_stack') ? $this->container->get('request_stack') : null;
        $locale = $requestStack instanceof RequestStack ? $requestStack->getCurrentRequest()?->getLocale() : null;

        return \is_string($locale) && $locale !== '' ? $locale : $this->getString('locale', 'en');
    }

    public function getLocaleKey(): string
    {
        return '_locale';
    }

    public function getDisk(): string
    {
        return $this->getString('storage.default', 'public');
    }

    public function getDiskOptions(): array
    {
        return [];
    }

    /**
     * @return class-string<PaletteContract>
     */
    public function getPalette(): string
    {
        $palette = $this->get('palette');

        return \is_string($palette) && is_subclass_of($palette, PaletteContract::class)
            ? $palette
            : NeutralPalette::class;
    }

    public function has(string $key): bool
    {
        return Arr::has($this->items, $key);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === 'view.compiled') {
            return $this->cacheDir . '/views';
        }

        return value(Arr::get($this->items, $key, $default));
    }

    public function set(string $key, mixed $value): self
    {
        Arr::set($this->items, $key, $value);

        return $this;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->set((string) $offset, $value);
    }

    public function offsetUnset(mixed $offset): void
    {
        Arr::forget($this->items, (string) $offset);
    }

    private function getString(string $key, string $default): string
    {
        $value = $this->get($key, $default);

        return \is_string($value) ? $value : $default;
    }
}
