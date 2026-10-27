<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Fixtures\Doctrine;

use MoonShine\Symfony\Pages\SymfonyFormPage;

final class ArticleFormPage extends SymfonyFormPage
{
    protected ?string $alias = 'form-page';

    protected function formType(): string
    {
        return ArticleType::class;
    }
}
