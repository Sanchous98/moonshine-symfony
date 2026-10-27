<?php

declare(strict_types=1);

namespace MoonShine\Symfony;

use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\Core\ResourceContract;

final class Instances
{
    /** @var list<ResourceContract> */
    public readonly array $resources;

    /** @var list<PageContract> */
    public readonly array $pages;

    /**
     * @param iterable<array-key, ResourceContract> $resources
     * @param iterable<array-key, PageContract> $pages
     */
    public function __construct(iterable $resources, iterable $pages)
    {
        $this->resources = array_values($resources instanceof \Traversable ? iterator_to_array($resources) : $resources);
        $this->pages = array_values($pages instanceof \Traversable ? iterator_to_array($pages) : $pages);
    }

    public function register(CoreContract $core): void
    {
        $core
            ->resources($this->resources, newCollection: true)
            ->pages($this->pages, newCollection: true);
    }
}
