<?php

declare(strict_types=1);

namespace MoonShine\Symfony\TypeCast;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\AbstractPaginator;
use MoonShine\Contracts\Core\Paginator\PaginatorContract;
use MoonShine\Contracts\Core\TypeCasts\DataCasterContract;
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
     * @return DoctrineDataWrapper<T>
     */
    public function cast(mixed $data): DoctrineDataWrapper
    {
        if (! $data instanceof $this->class) {
            $data = new ($this->class)(...(\is_array($data) ? $data : []));
        }

        /** @var T $data */
        return new DoctrineDataWrapper($data, $this->keyName, $this->entityManager);
    }

    public function paginatorCast(mixed $data): ?PaginatorContract
    {
        if (! $data instanceof AbstractPaginator || ! $data instanceof Arrayable) {
            return null;
        }

        return (new PaginatorCaster($data->toArray(), $data->items(), $data->getPageName()))->cast();
    }
}
