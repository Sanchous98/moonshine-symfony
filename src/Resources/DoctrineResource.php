<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Resources;

use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Doctrine\Persistence\ManagerRegistry;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormElementContract;
use MoonShine\Crud\QueryTags\QueryTag;
use MoonShine\Crud\Resources\CrudResource;
use MoonShine\Symfony\TypeCast\DoctrineCaster;
use MoonShine\UI\Contracts\RangeFieldContract;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * CRUD resource backed by a Doctrine ORM entity
 *
 * @template TEntity of object
 *
 * @extends CrudResource<TEntity>
 */
abstract class DoctrineResource extends CrudResource
{
    public const ALIAS = 'e';

    /** @var class-string<TEntity> */
    protected string $entity;

    protected ?ManagerRegistry $registry = null;

    /**
     * @var null|Closure(QueryBuilder): QueryBuilder
     */
    protected ?Closure $queryBuilderModifier = null;

    #[Required]
    public function setManagerRegistry(ManagerRegistry $registry): void
    {
        $this->registry = $registry;
    }

    /**
     * @return class-string<TEntity>
     */
    public function getEntityClass(): string
    {
        return $this->entity;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        $manager = $this->registry?->getManagerForClass($this->getEntityClass());

        if (! $manager instanceof EntityManagerInterface) {
            throw new RuntimeException(\sprintf('No Doctrine entity manager handles "%s".', $this->getEntityClass()));
        }

        return $manager;
    }

    /**
     * @return ClassMetadata<TEntity>
     */
    public function getMetadata(): ClassMetadata
    {
        return $this->getEntityManager()->getClassMetadata($this->getEntityClass());
    }

    public function getColumn(): string
    {
        return $this->getMetadata()->getSingleIdentifierFieldName();
    }

    /**
     * @return DoctrineCaster<TEntity>
     */
    public function getCaster(): DoctrineCaster
    {
        return new DoctrineCaster($this->getEntityClass(), $this->getColumn(), $this->getEntityManager());
    }

    /**
     * @return TEntity
     */
    public function getDataInstance(): object
    {
        return new ($this->getEntityClass())();
    }

    /**
     * Base query of the resource, override it to add joins or conditions
     */
    protected function query(): QueryBuilder
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select(self::ALIAS)
            ->from($this->getEntityClass(), self::ALIAS);
    }

    /**
     * @param Closure(QueryBuilder): QueryBuilder $modifier
     */
    public function modifyQueryBuilder(Closure $modifier): static
    {
        $this->queryBuilderModifier = $modifier;

        return $this;
    }

    public function getQueryBuilder(): QueryBuilder
    {
        $builder = $this->query();

        if ($this->queryBuilderModifier !== null) {
            $builder = ($this->queryBuilderModifier)($builder);
        }

        return $builder;
    }

    public function getQueryParams(): Collection
    {
        if ($this->queryParams !== []) {
            return new Collection($this->queryParams);
        }

        return new Collection($this->getCore()->getRequest()->getOnly($this->getQueryParamsKeys()));
    }

    /**
     * @return Collection<array-key, TEntity>|LengthAwarePaginator<array-key, TEntity>
     */
    public function getItems(): Collection|LengthAwarePaginator
    {
        $this->withCachedQueryParams();

        $builder = $this->getQueryBuilder();

        $this->applySearch($builder);
        $this->applyFilters($builder);
        $this->applyQueryTags($builder);
        $this->applyOrder($builder);

        if (! $this->isPaginationUsed()) {
            /** @var list<TEntity> $items */
            $items = $builder->getQuery()->getResult();

            return new Collection($items);
        }

        $perPage = $this->getItemsPerPage();
        $page = max(1, $this->getPaginatorPage());

        $builder->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);

        $paginator = new DoctrinePaginator($builder, fetchJoinCollection: \count($builder->getDQLPart('join')) > 0);

        $request = $this->getCore()->getRequest();
        $pageName = $this->getQueryParamName('page');

        return (new LengthAwarePaginator(
            iterator_to_array($paginator->getIterator(), false),
            \count($paginator),
            $perPage,
            $page,
            [
                'path' => $request->getRequest()->getUri()->getPath(),
                'pageName' => $pageName,
            ],
        ))->appends($request->getExcept([$pageName, '_token']));
    }

    public function findItem(bool $orFail = false): ?DataWrapperContract
    {
        $id = $this->getItemID();
        $entity = $id === null ? null : $this->getEntityManager()->find($this->getEntityClass(), $id);

        if ($entity === null) {
            return $orFail ? throw new NotFoundHttpException('Item not found') : null;
        }

        return $this->getCaster()->cast($entity);
    }

    /**
     * @param array<int|string> $ids
     */
    public function massDelete(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $this->beforeMassDeleting($ids);

        $manager = $this->getEntityManager();

        foreach ($manager->getRepository($this->getEntityClass())->findBy([$this->getColumn() => $ids]) as $entity) {
            $manager->remove($entity);
        }

        $manager->flush();

        $this->afterMassDeleted($ids);
    }

    public function delete(DataWrapperContract $item, ?FieldsContract $fields = null): bool
    {
        $item = $this->beforeDeleting($item);
        $entity = $item->getOriginal();

        $fields ??= $this->getFormFields()->onlyFields();
        $fields->fill($item->toArray(), $item);
        $fields->each(static fn (FieldContract $field): mixed => $field->afterDestroy($entity));

        $manager = $this->getEntityManager();
        $manager->remove($entity);
        $manager->flush();

        $this->afterDeleted($item);

        return true;
    }

    public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract
    {
        $manager = $this->getEntityManager();
        $isNew = ! $manager->contains($item->getOriginal());

        $fields ??= $this->getFormFields()->onlyFields();
        $fields->fill($item->toArray(), $item);

        $item = $isNew ? $this->beforeCreating($item) : $this->beforeUpdating($item);
        $entity = $item->getOriginal();

        // fields write with data_set(), the proxy maps it to setters or mapped properties
        $target = $this->proxy($entity);

        $fields->each(static function (FieldContract $field) use (&$target): void {
            $target = $field->beforeApply($target);
        });

        $fields->each(function (FieldContract $field) use (&$target): void {
            $target = $field->apply($this->fieldApply(...), $target);
        });

        $entity = $this->unwrap($target, $entity);

        $manager->persist($entity);
        $manager->flush();

        $target = $this->proxy($entity);

        $fields->each(static function (FieldContract $field) use (&$target): void {
            $target = $field->afterApply($target);
        });

        $entity = $this->unwrap($target, $entity);

        $manager->flush();

        $this->isRecentlyCreated = $isNew;

        $item = $this->getCaster()->cast($entity);
        $item = $isNew ? $this->afterCreated($item) : $this->afterUpdated($item);

        $this->setItem($item->getOriginal());

        return $item;
    }

    /**
     * @param TEntity $entity
     *
     * @return EntityProxy<TEntity>
     */
    protected function proxy(object $entity): EntityProxy
    {
        return new EntityProxy($entity, $this->getMetadata(), $this->castValue(...));
    }

    /**
     * @param TEntity $fallback
     *
     * @return TEntity
     */
    private function unwrap(mixed $target, object $fallback): object
    {
        if ($target instanceof EntityProxy) {
            return $target->getEntity();
        }

        return $target instanceof $fallback ? $target : $fallback;
    }

    /**
     * Default way a form field writes its value into the entity
     *
     * @param EntityProxy<TEntity> $target
     *
     * @return EntityProxy<TEntity>
     */
    protected function fieldApply(EntityProxy $target, mixed $value, FieldContract $field): EntityProxy
    {
        // false means the value is missing in the request
        if ($value === false && (! $field instanceof FormElementContract || ! $field->hasRequestValue())) {
            return $target;
        }

        // identifiers are generated by the database
        if ($this->getMetadata()->isIdentifier($field->getColumn())) {
            return $target;
        }

        data_set($target, $field->getColumn(), $value === false ? null : $value);

        return $target;
    }

    protected function castValue(string $property, mixed $value): mixed
    {
        $metadata = $this->getMetadata();

        if ($metadata->hasAssociation($property)) {
            return $this->castAssociation($property, $value);
        }

        if ($value === null || $value === '' || ! $metadata->hasField($property)) {
            return $value === '' && $metadata->hasField($property) && $metadata->isNullable($property) ? null : $value;
        }

        return match ($metadata->getTypeOfField($property)) {
            'integer', 'smallint', 'bigint' => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'boolean' => filter_var($value, \FILTER_VALIDATE_BOOL),
            'date', 'datetime', 'datetimetz', 'time' => \is_string($value) ? new \DateTime($value) : $value,
            'date_immutable', 'datetime_immutable', 'datetimetz_immutable', 'time_immutable' => \is_string($value) ? new \DateTimeImmutable($value) : $value,
            default => $value,
        };
    }

    /**
     * Turns submitted identifiers into entity references
     */
    protected function castAssociation(string $property, mixed $value): mixed
    {
        $metadata = $this->getMetadata();
        /** @var class-string $target */
        $target = $metadata->getAssociationTargetClass($property);
        $manager = $this->getEntityManager();

        $reference = static fn (mixed $id): ?object => \is_object($id) ? $id : (
            $id === null || $id === '' ? null : $manager->getReference($target, $id)
        );

        if ($metadata->isCollectionValuedAssociation($property)) {
            $ids = \is_iterable($value) ? $value : ($value === null || $value === '' ? [] : [$value]);
            $items = [];

            foreach ($ids as $id) {
                if (($entity = $reference($id)) !== null) {
                    $items[] = $entity;
                }
            }

            return new ArrayCollection($items);
        }

        return $reference(\is_array($value) ? reset($value) : $value);
    }

    protected function applySearch(QueryBuilder $builder): void
    {
        $term = data_get($this->getQueryParams(), $this->getSearchQueryKey());

        if (! $this->hasSearch() || ! \is_string($term) || trim($term) === '') {
            return;
        }

        $term = trim($term);
        $conditions = [];
        $parameters = [];

        foreach ($this->getSearchColumns() as $column) {
            if (! \is_string($column)) {
                continue;
            }

            if ($this->isTextColumn($column)) {
                $conditions[] = 'LOWER(' . $this->qualify($column) . ') LIKE :moonshine_search';
                $parameters['moonshine_search'] = '%' . mb_strtolower($term) . '%';
            } elseif (is_numeric($term)) {
                $conditions[] = $this->qualify($column) . ' = :moonshine_search_exact';
                $parameters['moonshine_search_exact'] = $term;
            }
        }

        if ($conditions === []) {
            return;
        }

        $builder->andWhere($builder->expr()->orX(...$conditions));

        foreach ($parameters as $name => $value) {
            $builder->setParameter($name, $value);
        }
    }

    protected function applyFilters(QueryBuilder $builder): void
    {
        if (! $this->hasFilters()) {
            return;
        }

        $params = $this->getFilterParams();

        if (! \is_array($params) || $params === []) {
            return;
        }

        $this->getFilters()->onlyFields()->each(function (FieldContract $filter) use ($builder, $params): void {
            $value = data_get($params, $filter->getColumn());

            if ($value === null || $value === '' || $value === []) {
                return;
            }

            // built-in field appliers write into arrays/objects, filters need a query condition
            if ($filter instanceof FormElementContract && $filter->hasOnApply()) {
                $filter->apply(static fn (QueryBuilder $builder): QueryBuilder => $builder, $builder);

                return;
            }

            $this->filterApply($builder, $value, $filter);
        });
    }

    /**
     * Default filter condition
     */
    protected function filterApply(QueryBuilder $builder, mixed $value, FieldContract $field): QueryBuilder
    {
        $column = $this->qualify($field->getColumn());
        $param = 'moonshine_filter_' . preg_replace('/\W/', '_', $field->getColumn());

        if ($field instanceof RangeFieldContract && \is_array($value)) {
            $from = $value[$field->getFromField()] ?? null;
            $to = $value[$field->getToField()] ?? null;

            if ($from !== null && $from !== '') {
                $builder->andWhere("$column >= :{$param}_from")->setParameter("{$param}_from", $from);
            }

            if ($to !== null && $to !== '') {
                $builder->andWhere("$column <= :{$param}_to")->setParameter("{$param}_to", $to);
            }

            return $builder;
        }

        if (\is_array($value)) {
            return $builder->andWhere("$column IN (:$param)")->setParameter($param, array_values($value));
        }

        if ($this->isTextColumn($field->getColumn())) {
            return $builder
                ->andWhere("LOWER($column) LIKE :$param")
                ->setParameter($param, '%' . mb_strtolower((string) $value) . '%');
        }

        return $builder->andWhere("$column = :$param")->setParameter($param, $this->castValue($field->getColumn(), $value));
    }

    protected function applyQueryTags(QueryBuilder $builder): void
    {
        foreach ($this->getQueryTags() as $tag) {
            if ($tag instanceof QueryTag && $tag->isActive()) {
                $tag->apply($builder);
            }
        }
    }

    protected function applyOrder(QueryBuilder $builder): void
    {
        [$column, $direction, $callback] = $this->prepareOrder();

        if ($callback instanceof Closure) {
            $callback($builder, $column, $direction);

            return;
        }

        $column = \is_string($column) && $column !== '' ? $column : $this->getColumn();

        if (! $this->isSortableColumn($column)) {
            return;
        }

        $builder->addOrderBy($this->qualify($column), strtolower((string) $direction) === 'asc' ? 'ASC' : 'DESC');
    }

    /**
     * Only mapped fields can be used in ORDER BY, the sort parameter comes from the request
     */
    protected function isSortableColumn(string $column): bool
    {
        if (str_contains($column, '.')) {
            return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $column);
        }

        return $this->getMetadata()->hasField($column);
    }

    protected function isTextColumn(string $column): bool
    {
        $metadata = $this->getMetadata();

        return $metadata->hasField($column)
            && \in_array($metadata->getTypeOfField($column), ['string', 'text', 'ascii_string', 'guid'], true);
    }

    protected function qualify(string $column): string
    {
        return str_contains($column, '.') ? $column : self::ALIAS . '.' . $column;
    }
}
