<?php

declare(strict_types=1);

namespace MoonShine\Symfony\TypeCast;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\AbstractPaginator;
use MoonShine\Contracts\Core\Paginator\PaginatorContract;
use MoonShine\Contracts\Core\TypeCasts\DataCasterContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Core\TypeCasts\MixedDataWrapper;
use MoonShine\Crud\TypeCasts\PaginatorCaster;

/**
 * @template T of object
 *
 * @implements DataCasterContract<T>
 */
final readonly class DoctrineCaster implements DataCasterContract
{
    /**
     * @param class-string<T> $class
     */
    public function __construct(
        private string $class,
        private string $keyName = 'id',
        private ?EntityManagerInterface $entityManager = null,
    ) {}

    /**
     * @return class-string<T>
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Entities are wrapped as they are, other data (filter values, raw arrays) is not turned into an entity
     *
     * @return DoctrineDataWrapper<T>|MixedDataWrapper
     */
    public function cast(mixed $data): DataWrapperContract
    {
        if ($data instanceof $this->class) {
            return new DoctrineDataWrapper($data, $this->keyName, $this->entityManager);
        }

        $data = \is_array($data) ? $data : [];
        $key = $data[$this->keyName] ?? null;

        return new MixedDataWrapper($data, \is_int($key) || \is_string($key) ? $key : null);
    }

    public function paginatorCast(mixed $data): ?PaginatorContract
    {
        if (! $data instanceof AbstractPaginator || ! $data instanceof Arrayable) {
            return null;
        }

        return (new PaginatorCaster($data->toArray(), $data->items(), $data->getPageName()))->cast();
    }
}
