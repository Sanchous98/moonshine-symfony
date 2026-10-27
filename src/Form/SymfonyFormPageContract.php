<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Form;

use Symfony\Component\Form\FormTypeInterface;

/**
 * A form page whose data is mapped and validated by a Symfony form type
 */
interface SymfonyFormPageContract
{
    /**
     * @return class-string<FormTypeInterface<mixed>>
     */
    public function getFormType(): string;

    /**
     * @return array<string, mixed>
     */
    public function getFormOptions(): array;
}
