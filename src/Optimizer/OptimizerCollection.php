<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Optimizer;

use MoonShine\Contracts\Core\DependencyInjection\ConfiguratorContract;
use MoonShine\Contracts\Core\DependencyInjection\OptimizerCollectionContract;
use MoonShine\Core\Collections\OptimizerCollection as BaseOptimizerCollection;

/**
 * Scans only the configured namespace instead of the whole Composer class map:
 * Symfony applications usually dump an optimized class map that contains every vendor class
 */
final class OptimizerCollection implements OptimizerCollectionContract
{
    private readonly BaseOptimizerCollection $collection;

    public function __construct(
        string $cachePath,
        private readonly ConfiguratorContract $config,
    ) {
        $this->collection = new BaseOptimizerCollection($cachePath, $config);
    }

    public function getCachePath(): string
    {
        return $this->collection->getCachePath();
    }

    public function getType(string $contract, ?string $namespace = null, bool $withCache = true): array
    {
        return $this->getTypes($namespace, $withCache)[$contract] ?? [];
    }

    public function getClassType(string $contract, ?string $namespace = null, bool $withCache = true): array
    {
        return array_values(array_filter(
            $this->getType($contract, $namespace, $withCache),
            static fn (mixed $class): bool => \is_string($class) && is_a($class, $contract, true),
        ));
    }

    public function getTypes(?string $namespace = null, bool $withCache = true): array
    {
        return $this->collection->getTypes($namespace ?? $this->config->getNamespace(), $withCache);
    }

    public function hasType(string $contract): bool
    {
        return $this->getType($contract) !== [];
    }

    public function hasCache(): bool
    {
        return $this->collection->hasCache();
    }

    /**
     * Fresh scan, ignoring the cache file
     *
     * @return array<class-string, array<mixed>>
     */
    public function detect(): array
    {
        return (new BaseOptimizerCollection($this->getCachePath(), $this->config))
            ->getTypes($this->config->getNamespace(), withCache: false);
    }
}
