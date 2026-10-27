<?php

declare(strict_types=1);

namespace MoonShine\Symfony\TypeCast;

use Doctrine\ORM\EntityManagerInterface;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use ReflectionObject;
use ReflectionProperty;

/**
 * @template T of object
 *
 * @implements DataWrapperContract<T>
 */
final readonly class DoctrineDataWrapper implements DataWrapperContract
{
    /**
     * @param T $entity
     */
    public function __construct(
        private object $entity,
        private string $keyName = 'id',
        private ?EntityManagerInterface $entityManager = null,
    ) {}

    /**
     * @return T
     */
    public function getOriginal(): object
    {
        return $this->entity;
    }

    public function getKey(): int|string|null
    {
        $key = $this->read($this->keyName);

        return \is_int($key) || \is_string($key) ? $key : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        foreach ($this->getProperties() as $property) {
            if ($property->isInitialized($this->entity)) {
                $data[$property->getName()] = $property->getValue($this->entity);
            }
        }

        return $this->withAssociationKeys($data);
    }

    /**
     * Associations are represented by identifiers, the way select fields expect them
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function withAssociationKeys(array $data): array
    {
        if ($this->entityManager === null || ! $this->entityManager->getMetadataFactory()->hasMetadataFor($this->entity::class)) {
            return $data;
        }

        $metadata = $this->entityManager->getClassMetadata($this->entity::class);

        foreach ($metadata->getAssociationNames() as $association) {
            $value = $data[$association] ?? null;

            if ($value === null) {
                continue;
            }

            $data[$association] = is_iterable($value)
                ? array_values(array_map($this->identify(...), iterator_to_array($value)))
                : $this->identify($value);
        }

        return $data;
    }

    private function identify(object $entity): mixed
    {
        $identifier = $this->entityManager?->getUnitOfWork()->getSingleIdentifierValue($entity);

        return $identifier ?? $this->entityManager?->getClassMetadata($entity::class)->getIdentifierValues($entity);
    }

    private function read(string $name): mixed
    {
        $getter = 'get' . ucfirst($name);

        if (method_exists($this->entity, $getter)) {
            return $this->entity->{$getter}();
        }

        foreach ($this->getProperties() as $property) {
            if ($property->getName() === $name) {
                return $property->isInitialized($this->entity) ? $property->getValue($this->entity) : null;
            }
        }

        return null;
    }

    /**
     * @return list<ReflectionProperty>
     */
    private function getProperties(): array
    {
        $properties = [];
        $reflection = new ReflectionObject($this->entity);

        do {
            foreach ($reflection->getProperties() as $property) {
                if (! $property->isStatic()) {
                    $properties[$property->getName()] ??= $property;
                }
            }
        } while ($reflection = $reflection->getParentClass());

        return array_values($properties);
    }
}
