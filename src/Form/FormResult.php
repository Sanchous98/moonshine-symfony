<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Form;

use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;

final readonly class FormResult
{
    /**
     * @param array<string, list<string>> $errors errors keyed by field name
     * @param mixed $item the item with mapped values
     * @param FieldsContract $fields MoonShine fields that still have to be applied to the item
     */
    public function __construct(
        public array $errors,
        public mixed $item,
        public FieldsContract $fields,
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function getFirstError(): string
    {
        return (string) (array_values($this->errors)[0][0] ?? 'The given data was invalid.');
    }
}
