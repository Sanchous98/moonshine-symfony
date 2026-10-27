<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use MoonShine\Core\Attributes\Layout;
use MoonShine\Crud\Pages\Page;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\Symfony\Layout\AppLayout;

#[SkipMenu]
#[Layout(AppLayout::class)]
final class Dashboard extends Page
{
    protected string $title = 'Dashboard';

    protected function components(): iterable
    {
        return [];
    }
}
