<?php

declare(strict_types=1);

namespace MoonShine\Symfony\DependencyInjection;

use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\Core\ResourceContract;
use MoonShine\Core\AbstractEndpoints;
use MoonShine\Core\Core;
use Symfony\Component\HttpFoundation\RedirectResponse;

final readonly class Endpoints extends AbstractEndpoints
{
    public function toPage(
        string|PageContract|null $page = null,
        ResourceContract|string|null $resource = null,
        array $params = [],
        array $extra = [],
    ): string|RedirectResponse {
        $redirect = $extra['redirect'] ?? false;
        $fragment = $extra['fragment'] ?? null;

        if (\is_array($fragment)) {
            $fragment = implode(',', array_map(
                static fn (mixed $key, mixed $value): string => \sprintf('%s:%s', $key, \is_scalar($value) ? $value : ''),
                array_keys($fragment),
                $fragment,
            ));
        }

        if (\is_string($fragment) && $fragment !== '') {
            $params += ['_fragment-load' => $fragment];
        }

        $page = \is_string($page) ? $this->resolve($page) : $page;
        $resource = \is_string($resource) ? $this->resolve($resource) : $resource;

        unset($extra['redirect'], $extra['fragment']);

        $url = $this->router->to($resource === null ? 'page' : 'resource.page', [
            'pageUri' => $page instanceof PageContract
                ? $page->getUriKey()
                : $this->router->getParam('pageUri', $this->router->extractPageUri()),
            'resourceUri' => $resource instanceof ResourceContract
                ? $resource->getUriKey()
                : $this->router->getParam('resourceUri', $this->router->extractResourceUri()),
            ...$params,
            ...$extra,
        ]);

        return $redirect ? new RedirectResponse($url) : $url;
    }

    public function home(): string
    {
        return $this->router->to('index');
    }

    /**
     * @param class-string<PageContract|ResourceContract> $class
     */
    private function resolve(string $class): PageContract|ResourceContract|null
    {
        return Core::getInstance()->getInstances($class);
    }
}
