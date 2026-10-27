<?php

declare(strict_types=1);

namespace MoonShine\Symfony;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

final class Routing extends Loader
{
    public function __construct(private readonly string $prefix = '/admin')
    {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        /** @var RouteCollection $routes */
        $routes = $this->import(\dirname(__DIR__) . '/src/Controller/', 'attribute');

        $routes->addPrefix($this->prefix);

        foreach ($routes as $route) {
            if ($route->getPath() !== '/' && str_ends_with($route->getPath(), '/')) {
                $route->setPath(rtrim($route->getPath(), '/'));
            }
        }

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'moonshine.routes';
    }
}
