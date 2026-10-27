<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Resources;

use Closure;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Lets MoonShine fields read and write entity properties with data_get()/data_set()
 * through the PropertyAccess component (getters, setters, adders/removers) or Doctrine metadata,
 * method calls are forwarded to the entity.
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
        if (self::accessor()->isReadable($this->entity, $name)) {
            return self::accessor()->getValue($this->entity, $name);
        }

        if ($this->metadata->hasField($name) || $this->metadata->hasAssociation($name)) {
            return $this->metadata->getFieldValue($this->entity, $name);
        }

        return null;
    }

    /**
     * Writes through setters or adders/removers of collections, mapped properties without accessors are set directly
     */
    public function __set(string $name, mixed $value): void
    {
        $value = ($this->cast)($name, $value);

        if (self::accessor()->isWritable($this->entity, $name)) {
            self::accessor()->setValue($this->entity, $name, $value);

            return;
        }

        if ($this->metadata->hasField($name) || $this->metadata->hasAssociation($name)) {
            $this->metadata->setFieldValue($this->entity, $name, $value);
        }
    }

    private static function accessor(): PropertyAccessorInterface
    {
        static $accessor = null;

        return $accessor ??= PropertyAccess::createPropertyAccessor();
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
