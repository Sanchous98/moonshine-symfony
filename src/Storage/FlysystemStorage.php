<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Storage;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use MoonShine\Contracts\Core\DependencyInjection\StorageContract;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

/**
 * MoonShine storage on top of a Flysystem filesystem, paths are relative to the filesystem root
 */
final readonly class FlysystemStorage implements StorageContract
{
    /**
     * @param string|null $url base public URL, the filesystem "public_url" option is used when null
     */
    public function __construct(
        private FilesystemOperator $filesystem,
        private ?string $url = null,
    ) {}

    public function getFilesystem(): FilesystemOperator
    {
        return $this->filesystem;
    }

    public function exists(string $path): bool
    {
        try {
            return $this->filesystem->fileExists($path) || $this->filesystem->directoryExists($path);
        } catch (FilesystemException) {
            return false;
        }
    }

    public function getPath(string $path): string
    {
        return ltrim($path, '/');
    }

    public function getUrl(string $path): string
    {
        if (preg_match('#^(https?:)?//#', $path) === 1) {
            return $path;
        }

        if ($this->url !== null) {
            return rtrim($this->url, '/') . '/' . ltrim($path, '/');
        }

        try {
            return $this->filesystem->publicUrl($path);
        } catch (Throwable) {
            return '/' . ltrim($path, '/');
        }
    }

    public function delete(array|string $paths): bool
    {
        $success = true;

        foreach ((array) $paths as $path) {
            try {
                if (! $this->filesystem->fileExists($path)) {
                    $success = false;

                    continue;
                }

                $this->filesystem->delete($path);
            } catch (FilesystemException) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @param array<string, mixed> $options Flysystem write config, e.g. ['visibility' => 'public']
     */
    public function store(string $path, mixed $file = null, array $options = []): false|string
    {
        return $this->storeAs($path, $file, null, $options);
    }

    /**
     * @param string|array<string, mixed>|null $name
     * @param array<string, mixed> $options Flysystem write config
     */
    public function storeAs(string $path, mixed $file, string|array|null $name = null, array $options = []): false|string
    {
        $name = \is_string($name) && $name !== '' ? basename($name) : $this->hashName($file);
        $stream = $this->openStream($file);

        if ($name === null || $stream === null) {
            return false;
        }

        $target = ltrim(trim($path, '/') . '/' . $name, '/');

        try {
            $this->filesystem->writeStream($target, $stream, $options);
        } catch (FilesystemException) {
            return false;
        } finally {
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }

        return $target;
    }

    public function getFiles(string $directory, bool $recursive = false): array
    {
        return $this->list($directory, $recursive, files: true);
    }

    public function getDirectories(string $directory, bool $recursive = false): array
    {
        return $this->list($directory, $recursive, files: false);
    }

    public function makeDirectory(string $path): bool
    {
        try {
            $this->filesystem->createDirectory($path);
        } catch (FilesystemException) {
            return false;
        }

        return true;
    }

    public function deleteDirectory(string $directory): bool
    {
        try {
            $this->filesystem->deleteDirectory($directory);
        } catch (FilesystemException) {
            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function list(string $directory, bool $recursive, bool $files): array
    {
        try {
            return $this->filesystem
                ->listContents($directory, $recursive)
                ->filter(static fn (StorageAttributes $item): bool => $files ? $item->isFile() : $item->isDir())
                ->map(static fn (StorageAttributes $item): string => $item->path())
                ->toArray();
        } catch (FilesystemException) {
            return [];
        }
    }

    /**
     * @return resource|null
     */
    private function openStream(mixed $file): mixed
    {
        $stream = match (true) {
            $file instanceof UploadedFileInterface => $file->getStream()->detach(),
            $file instanceof File => fopen($file->getPathname(), 'rb'),
            \is_string($file) && is_file($file) => fopen($file, 'rb'),
            default => null,
        };

        return \is_resource($stream) ? $stream : null;
    }

    private function hashName(mixed $file): ?string
    {
        $extension = match (true) {
            $file instanceof UploadedFileInterface => pathinfo((string) $file->getClientFilename(), \PATHINFO_EXTENSION),
            $file instanceof UploadedFile => $file->getClientOriginalExtension(),
            $file instanceof File => $file->getExtension(),
            \is_string($file) => pathinfo($file, \PATHINFO_EXTENSION),
            default => null,
        };

        if ($extension === null) {
            return null;
        }

        $extension = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $extension) ?? '');

        return bin2hex(random_bytes(20)) . ($extension !== '' ? ".$extension" : '');
    }
}
