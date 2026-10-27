<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Applies;

use Closure;
use MoonShine\Contracts\UI\ApplyContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\UI\Fields\File;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * Stores uploaded files of File/Image fields and keeps the remaining ones
 *
 * @implements ApplyContract<File>
 */
final class FileApply implements ApplyContract
{
    public function apply(FieldContract $field): Closure
    {
        return static function (mixed $item) use ($field): mixed {
            if (! $field instanceof File) {
                return $item;
            }

            $requestValue = $field->getRequestValue();
            $remaining = $field->getRemainingValues()->filter()->values();

            $value = $field->isMultiple() ? $remaining->all() : $remaining->first();

            if ($requestValue !== false && $requestValue !== null) {
                $stored = array_map(
                    static fn (UploadedFileInterface $file): string => self::store($field, $file),
                    array_values(array_filter(
                        \is_array($requestValue) ? $requestValue : [$requestValue],
                        static fn (mixed $file): bool => $file instanceof UploadedFileInterface && $file->getError() === \UPLOAD_ERR_OK,
                    )),
                );

                if ($stored !== []) {
                    $previous = $field->toValue(withDefault: false);

                    $value = $field->isMultiple() ? [...$remaining->all(), ...$stored] : $stored[0];

                    // a single file is replaced by the new upload
                    if (! $field->isMultiple() && \is_string($previous) && $previous !== '' && $previous !== $value) {
                        $field->deleteFile($previous);
                    }
                }
            }

            $field->removeExcludedFiles($value);

            data_set($item, $field->getColumn(), $value);

            return $item;
        };
    }

    private static function store(File $field, UploadedFileInterface $file): string
    {
        $extension = strtolower(pathinfo((string) $file->getClientFilename(), \PATHINFO_EXTENSION));

        if (! $field->isAllowedExtension($extension)) {
            throw new RuntimeException(\sprintf('File extension "%s" is not allowed.', $extension));
        }

        $storage = $field->getCore()->getStorage(disk: $field->getDisk());

        $name = match (true) {
            $field->getCustomName() !== null => \call_user_func($field->getCustomName(), $file, $field),
            $field->isKeepOriginalFileName() => $file->getClientFilename(),
            default => null,
        };

        $path = $storage->storeAs($field->getDir(), $file, \is_string($name) ? $name : null, $field->getOptions());

        if ($path === false) {
            throw new RuntimeException('Failed to store the uploaded file.');
        }

        return $path;
    }
}
