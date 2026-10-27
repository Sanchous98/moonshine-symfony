<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Storage;

use InvalidArgumentException;
use MoonShine\Contracts\Core\DependencyInjection\StorageContract;

final class StorageRegistry
{
    /** @var array<string, StorageContract> */
    private array $storages = [];

    /**
     * @param array<string, array{root: string, url: string}> $disks
     */
    public function __construct(
        private readonly array $disks,
        private readonly string $default,
    ) {}

    public function getDefault(): string
    {
        return $this->default;
    }

    public function get(?string $disk = null): StorageContract
    {
        $disk = $disk === null || $disk === '' ? $this->default : $disk;

        if (! isset($this->disks[$disk])) {
            throw new InvalidArgumentException(\sprintf('MoonShine disk "%s" is not configured.', $disk));
        }

        return $this->storages[$disk] ??= new LocalStorage($this->disks[$disk]['root'], $this->disks[$disk]['url']);
    }
}
