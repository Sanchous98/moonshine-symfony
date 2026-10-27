<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Access to the admin panel user, works without the security bundle when auth is disabled
 */
final readonly class Auth
{
    public function __construct(
        private bool $enabled = false,
        private ?string $role = null,
        private ?Security $security = null,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getUser(): ?UserInterface
    {
        return $this->security?->getUser();
    }

    public function isGranted(): bool
    {
        if (! $this->enabled) {
            return true;
        }

        if ($this->security === null || $this->getUser() === null) {
            return false;
        }

        return $this->role === null || $this->security->isGranted($this->role);
    }

    public function getName(): string
    {
        $user = $this->getUser();

        if ($user === null) {
            return '';
        }

        foreach (['getName', 'getFullName', 'getDisplayName'] as $method) {
            if (method_exists($user, $method)) {
                $name = $user->{$method}();

                if (\is_string($name) && $name !== '') {
                    return $name;
                }
            }
        }

        return $user->getUserIdentifier();
    }

    public function getAvatar(): ?string
    {
        $user = $this->getUser();

        if ($user !== null && method_exists($user, 'getAvatar')) {
            $avatar = $user->getAvatar();

            return \is_string($avatar) && $avatar !== '' ? $avatar : null;
        }

        return null;
    }
}
