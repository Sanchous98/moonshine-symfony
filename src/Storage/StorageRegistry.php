<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Storage;

use InvalidArgumentException;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use MoonShine\Contracts\Core\DependencyInjection\StorageContract;
use Psr\Container\ContainerInterface;

/**
 * MoonShine disks: local directories or Flysystem storages registered as services (league/flysystem-bundle)
 */
final class StorageRegistry
{
    /** @var array<string, StorageContract> */
    private array $storages = [];

    /**
     * @param array<string, array{root?: string|null, url?: string|null, service?: string|null}> $disks
     * @param ContainerInterface $filesystems locator of the Flysystem services used by disks
     */
    public function __construct(
        private readonly array $disks,
        private readonly string $default,
        private readonly ContainerInterface $filesystems,
    ) {}

    public function getDefault(): string
    {
        return $this->default;
    }

    public function get(?string $disk = null): StorageContract
    {
        $disk = $disk === null || $disk === '' ? $this->default : $disk;

        return $this->storages[$disk] ??= new FlysystemStorage(
            $this->createFilesystem($disk),
            $this->disks[$disk]['url'] ?? null,
        );
    }

    private function createFilesystem(string $disk): FilesystemOperator
    {
        $config = $this->disks[$disk] ?? throw new InvalidArgumentException(\sprintf('MoonShine disk "%s" is not configured.', $disk));

        if (isset($config['service'])) {
            $filesystem = $this->filesystems->get($config['service']);

            if (! $filesystem instanceof FilesystemOperator) {
                throw new InvalidArgumentException(\sprintf('Service "%s" of MoonShine disk "%s" is not a Flysystem filesystem.', $config['service'], $disk));
            }

            return $filesystem;
        }

        if (! isset($config['root'])) {
            throw new InvalidArgumentException(\sprintf('MoonShine disk "%s" needs a "root" directory or a Flysystem "service".', $disk));
        }

        return new Filesystem(new LocalFilesystemAdapter($config['root']));
    }
}
