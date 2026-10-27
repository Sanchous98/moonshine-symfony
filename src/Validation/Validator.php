<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Validation;

use Illuminate\Container\Container;
use Illuminate\Validation\Factory;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Validates data with Laravel style rules returned by FormPage::rules()
 */
final class Validator
{
    private ?Factory $factory = null;

    public function __construct(private readonly TranslatorInterface $translator) {}

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, mixed> $messages
     * @param array<string, string> $attributes
     *
     * @return array<string, list<string>> errors keyed by field
     */
    public function validate(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        if ($rules === []) {
            return [];
        }

        $validator = $this->getFactory()->make($data, $rules, $messages, $attributes);

        /** @var array<string, list<string>> */
        return $validator->fails() ? $validator->errors()->toArray() : [];
    }

    private function getFactory(): Factory
    {
        return $this->factory ??= new Factory(
            new TranslatorBridge($this->translator),
            Container::getInstance(),
        );
    }
}
