<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Pages;

use MoonShine\Crud\Pages\FormPage;
use MoonShine\Symfony\Form\SymfonyFormPageContract;
use Symfony\Component\Form\FormTypeInterface;

/**
 * Form page backed by a Symfony form type: the form maps the submitted data
 * into the item and validates it, MoonShine fields are used for rendering
 */
abstract class SymfonyFormPage extends FormPage implements SymfonyFormPageContract
{
    /**
     * @return class-string<FormTypeInterface<mixed>>
     */
    abstract protected function formType(): string;

    /**
     * Options passed to the form type
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [];
    }

    public function getFormType(): string
    {
        return $this->formType();
    }

    public function getFormOptions(): array
    {
        return $this->formOptions();
    }
}
