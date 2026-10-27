<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Fixtures\Pages;

use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Crud\Pages\FormPage;

final class PostFormPage extends FormPage
{
    protected ?string $alias = 'form-page';

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'title' => ['required', 'string', 'min:3'],
        ];
    }
}
