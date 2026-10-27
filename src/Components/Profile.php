<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Components;

use MoonShine\UI\Components\MoonShineComponent;

/**
 * @method static static make(string $logOutRoute, string $nameOfUser, string $username = '', ?string $avatar = null)
 */
final class Profile extends MoonShineComponent
{
    protected string $view = 'moonshine::components.layout.profile';

    public function __construct(
        private readonly string $logOutRoute,
        private readonly string $nameOfUser,
        private readonly string $username = '',
        private readonly ?string $avatar = null,
    ) {
        parent::__construct();
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [
            'route' => '',
            'logOutRoute' => $this->logOutRoute,
            'avatar' => $this->avatar ?? $this->getAssetManager()->getAsset('vendor/moonshine/avatar.jpg'),
            'nameOfUser' => $this->nameOfUser,
            'username' => $this->username,
            'menu' => null,
            'translates' => [
                'logout' => $this->getCore()->getTranslator()->get('moonshine::ui.login.logout'),
            ],
        ];
    }
}
