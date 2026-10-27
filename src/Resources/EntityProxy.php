<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Resources;

use Closure;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * Lets MoonShine fields read and write entity properties with data_get()/data_set()
 * through getters, setters or Doctrine metadata, method calls are forwarded to the entity.
 *
 * @template TEntity of object
 */
final class EntityProxy
{
    /**
     * @param TEntity $entity
     * @param ClassMetadata<TEntity> $metadata
     * @param Closure(string, mixed): mixed $cast
     */
    public function __construct(
        private readonly object $entity,
        private readonly ClassMetadata $metadata,
        private readonly Closure $cast,
    ) {}

    /**
     * @return TEntity
     */
    public function getEntity(): object
    {
        return $this->entity;
    }

    public function __get(string $name): mixed
    {
        foreach (['get', 'is', 'has'] as $prefix) {
            if (method_exists($this->entity, $method = $prefix . ucfirst($name))) {
                return $this->entity->{$method}();
            }
        }

        if ($this->metadata->hasField($name) || $this->metadata->hasAssociation($name)) {
            return $this->metadata->getFieldValue($this->entity, $name);
        }

        return property_exists($this->entity, $name) && isset($this->entity->{$name}) ? $this->entity->{$name} : null;
    }

    public function __set(string $name, mixed $value): void
    {
        $value = ($this->cast)($name, $value);

        if (method_exists($this->entity, $method = 'set' . ucfirst($name))) {
            $this->entity->{$method}($value);

            return;
        }

        if ($this->metadata->hasField($name) || $this->metadata->hasAssociation($name)) {
            $this->metadata->setFieldValue($this->entity, $name, $value);

            return;
        }

        $this->entity->{$name} = $value;
    }

    public function __isset(string $name): bool
    {
        return $this->__get($name) !== null;
    }

    public function __unset(string $name): void
    {
        $this->__set($name, null);
    }

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->entity->{$method}(...$arguments);
    }
}
