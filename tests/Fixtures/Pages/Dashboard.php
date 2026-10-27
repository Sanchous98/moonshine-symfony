<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Fixtures\Pages;

use MoonShine\Core\Attributes\Layout;
use MoonShine\Crud\Pages\Page;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\Symfony\Tests\Fixtures\Layouts\TestLayout;
use MoonShine\UI\Components\Heading;

#[SkipMenu]
#[Layout(TestLayout::class)]
final class Dashboard extends Page
{
    protected string $title = 'Dashboard';

    protected function components(): iterable
    {
        return [
            Heading::make('Hello from dashboard'),
        ];
    }
}
