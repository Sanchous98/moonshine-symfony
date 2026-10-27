<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Storage;

use MoonShine\Contracts\Core\DependencyInjection\StorageContract;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

/**
 * Local disk: paths are relative to the root directory and served from the base URL
 */
final readonly class LocalStorage implements StorageContract
{
    private Filesystem $fs;

    public function __construct(
        private string $root,
        private string $url,
    ) {
        $this->fs = new Filesystem();
    }

    public function exists(string $path): bool
    {
        return $this->fs->exists($this->getPath($path));
    }

    public function getPath(string $path): string
    {
        $relative = ltrim($path, '/');

        if (str_contains(Path::canonicalize($relative), '..')) {
            throw new \InvalidArgumentException(\sprintf('Path "%s" is outside of the storage.', $path));
        }

        return rtrim($this->root, '/') . '/' . $relative;
    }

    public function getUrl(string $path): string
    {
        if (preg_match('#^(https?:)?//#', $path) === 1) {
            return $path;
        }

        return rtrim($this->url, '/') . '/' . ltrim($path, '/');
    }

    public function delete(array|string $paths): bool
    {
        $success = true;

        foreach ((array) $paths as $path) {
            try {
                $absolute = $this->getPath($path);

                if (! is_file($absolute)) {
                    $success = false;

                    continue;
                }

                $this->fs->remove($absolute);
            } catch (Throwable) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function store(string $path, mixed $file = null, array $options = []): false|string
    {
        return $this->storeAs($path, $file, null, $options);
    }

    /**
     * @param string|array<string, mixed>|null $name
     * @param array<string, mixed> $options
     */
    public function storeAs(string $path, mixed $file, string|array|null $name = null, array $options = []): false|string
    {
        $name = \is_string($name) && $name !== '' ? basename($name) : $this->hashName($file);

        if ($name === null) {
            return false;
        }

        $relative = trim(trim($path, '/') . '/' . $name, '/');
        $target = $this->getPath($relative);

        $this->fs->mkdir(\dirname($target));

        try {
            match (true) {
                $file instanceof UploadedFileInterface => $file->moveTo($target),
                $file instanceof File => $file->move(\dirname($target), basename($target)),
                \is_string($file) && is_file($file) => $this->fs->copy($file, $target, true),
                default => throw new \InvalidArgumentException('Unsupported file.'),
            };
        } catch (Throwable) {
            return false;
        }

        return $relative;
    }

    public function getFiles(string $directory, bool $recursive = false): array
    {
        return $this->scan($directory, $recursive, files: true);
    }

    public function getDirectories(string $directory, bool $recursive = false): array
    {
        return $this->scan($directory, $recursive, files: false);
    }

    public function makeDirectory(string $path): bool
    {
        try {
            $this->fs->mkdir($this->getPath($path));
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    public function deleteDirectory(string $directory): bool
    {
        $absolute = $this->getPath($directory);

        if (! is_dir($absolute)) {
            return false;
        }

        $this->fs->remove($absolute);

        return true;
    }

    /**
     * @return list<string> paths relative to the root
     */
    private function scan(string $directory, bool $recursive, bool $files): array
    {
        $absolute = $this->getPath($directory);

        if (! is_dir($absolute)) {
            return [];
        }

        $result = [];

        foreach (scandir($absolute) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $relative = trim(trim($directory, '/') . '/' . $item, '/');
            $isDir = is_dir($absolute . '/' . $item);

            if ($isDir !== $files) {
                $result[] = $relative;
            }

            if ($isDir && $recursive) {
                array_push($result, ...$this->scan($relative, true, $files));
            }
        }

        return $result;
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
