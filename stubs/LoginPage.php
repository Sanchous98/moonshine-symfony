<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use MoonShine\Core\Attributes\Layout;
use MoonShine\Crud\Forms\LoginForm;
use MoonShine\Crud\Pages\Page;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\Symfony\Layout\LoginLayout;

#[SkipMenu]
#[Layout(LoginLayout::class)]
final class LoginPage extends Page
{
    protected function components(): iterable
    {
        return [
            $this->getCore()->getConfig()->getForm(
                'login',
                LoginForm::class,
                action: $this->getRouter()->to('authenticate'),
                core: $this->getCore(),
            ),
        ];
    }
}
