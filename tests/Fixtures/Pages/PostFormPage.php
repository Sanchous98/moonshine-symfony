<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Fixtures\Pages;

use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Crud\Pages\FormPage;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class PostFormPage extends FormPage
{
    protected ?string $alias = 'form-page';

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'title' => [new NotBlank(), new Length(min: 3)],
        ];
    }
}
