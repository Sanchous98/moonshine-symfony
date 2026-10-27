<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Fixtures\Layouts;

use MoonShine\AssetManager\Css;
use MoonShine\MenuManager\MenuItem;
use MoonShine\Symfony\Layout\AppLayout;

/**
 * Custom menu items and assets are registered in the layout constructor
 */
final class TestLayout extends AppLayout
{
    protected function menu(): array
    {
        return [
            ...parent::menu(),
            MenuItem::make('/admin/page/reports', 'Reports'),
        ];
    }

    protected function assets(): array
    {
        return [
            ...parent::assets(),
            Css::make('/css/admin-custom.css'),
        ];
    }
}
