<?php

declare(strict_types=1);

namespace MoonShine\Symfony\DependencyInjection;

use MoonShine\Contracts\Core\CrudResourceContract;
use MoonShine\Contracts\Core\DependencyInjection\EndpointsContract;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\Core\ResourceContract;
use MoonShine\Core\AbstractRouter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class Router extends AbstractRouter
{
    /**
     * Route context parameters are path segments, they must not leak into the query string
     */
    private const CONTEXT_PARAMS = ['pageUri', 'resourceUri', 'resourceItem'];

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {}

    public function to(string $name = '', array $params = []): string
    {
        $name = $this->getName($name);

        $params = array_filter(
            $this->getParams($params),
            static fn (mixed $value): bool => \is_scalar($value) || $value instanceof \Stringable,
        );

        return $this->withoutContextQuery(
            $this->urlGenerator->generate($name, $params),
        );
    }

    public function getEndpoints(): EndpointsContract
    {
        return new Endpoints($this);
    }

    public function extractPageUri(?PageContract $page = null): ?string
    {
        return $page?->getUriKey() ?? $this->getRouteParam('pageUri');
    }

    public function extractResourceUri(?ResourceContract $resource = null): ?string
    {
        return $resource?->getUriKey() ?? $this->getRouteParam('resourceUri');
    }

    public function extractResourceItem(
        int|string|null $key = null,
        ?CrudResourceContract $resource = null,
    ): string|int|null {
        if ($key !== null) {
            return $key;
        }

        $castedKey = $resource?->getCastedData()?->getKey();

        if ($castedKey !== null) {
            return $castedKey;
        }

        $id = $this->getParam('resourceItem');

        if (\is_int($id) || \is_string($id)) {
            return $id;
        }

        return $this->getRouteParam('resourceItem');
    }

    private function withoutContextQuery(string $url): string
    {
        $query = parse_url($url, \PHP_URL_QUERY);

        if (! \is_string($query) || $query === '') {
            return $url;
        }

        parse_str($query, $params);

        $filtered = array_diff_key($params, array_flip(self::CONTEXT_PARAMS));

        if (\count($filtered) === \count($params)) {
            return $url;
        }

        $base = strstr($url, '?', true);

        return $filtered === [] ? $base : $base . '?' . http_build_query($filtered, '', '&', \PHP_QUERY_RFC3986);
    }

    private function getRouteParam(string $name): ?string
    {
        $value = $this->requestStack->getCurrentRequest()?->attributes->get($name);

        return \is_scalar($value) && $value !== '' ? (string) $value : null;
    }
}
