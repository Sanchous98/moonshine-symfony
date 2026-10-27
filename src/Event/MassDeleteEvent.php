<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Event;

use MoonShine\Contracts\Core\CrudResourceContract;
use Symfony\Contracts\EventDispatcher\Event;

abstract class MassDeleteEvent extends Event
{
    /**
     * @param list<int|string> $ids
     */
    public function __construct(
        private readonly CrudResourceContract $resource,
        private readonly array $ids,
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

    /**
     * @return list<int|string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}
