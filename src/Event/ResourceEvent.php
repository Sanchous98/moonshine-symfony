<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Event;

use MoonShine\Contracts\Core\CrudResourceContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event of a single item of a resource
 */
abstract class ResourceEvent extends Event
{
    public function __construct(
        private readonly CrudResourceContract $resource,
        private DataWrapperContract $item,
    ) {}

    public function getResource(): CrudResourceContract
    {
        return $this->resource;
    }

    /**
     * @param class-string<CrudResourceContract> $resource
     */
    public function isFor(string $resource): bool
    {
        return $this->resource instanceof $resource;
    }

    public function getItem(): DataWrapperContract
    {
        return $this->item;
    }

    /**
     * The original data: an entity for Doctrine resources
     */
    public function getData(): mixed
    {
        return $this->item->getOriginal();
    }

    public function setItem(DataWrapperContract $item): void
    {
        $this->item = $item;
    }
}
