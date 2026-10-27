<?php

declare(strict_types=1);

namespace MoonShine\Symfony\DependencyInjection;

use MoonShine\Contracts\Core\DependencyInjection\StorageContract;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\Core\ResourceContract;
use MoonShine\Core\Core;
use MoonShine\Core\Storage\FileStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\Service\ResetInterface;

final class MoonShine extends Core implements ResetInterface
{
    public function runningUnitTests(): bool
    {
        return $this->getEnvironment() === 'test';
    }

    public function runningInConsole(): bool
    {
        return \PHP_SAPI === 'cli' || \PHP_SAPI === 'phpdbg';
    }

    public function isLocal(): bool
    {
        return \in_array($this->getEnvironment(), ['dev', 'local'], true);
    }

    public function isProduction(): bool
    {
        return $this->getEnvironment() === 'prod';
    }

    public function getContainer(?string $id = null, mixed $default = null, ...$parameters): mixed
    {
        if ($id === null) {
            return $this->container;
        }

        if (! $this->container->has($id)) {
            return $default;
        }

        return $this->container->get($id);
    }

    public function getStorage(...$parameters): StorageContract
    {
        return new FileStorage();
    }

    public function autoload(?string $namespace = null): static
    {
        $namespace ??= $this->getConfig()->getNamespace();

        return $this
            ->pages($this->getOptimizer()->getClassType(PageContract::class, $namespace))
            ->resources($this->getOptimizer()->getClassType(ResourceContract::class, $namespace));
    }

    public function reset(): void
    {
        $this->flushState();
    }

    private function getEnvironment(): ?string
    {
        if (! $this->container instanceof ContainerInterface || ! $this->container->hasParameter('kernel.environment')) {
            return null;
        }

        $environment = $this->container->getParameter('kernel.environment');

        return \is_string($environment) ? $environment : null;
    }
}
