<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Fixtures\Doctrine;

enum ArticleStatus: string
{
    case Draft = 'draft';

    case Published = 'published';
}
