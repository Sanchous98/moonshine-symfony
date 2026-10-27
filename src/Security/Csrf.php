<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Security;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * CSRF tokens of the admin panel, the same token id is used by the login form
 */
final readonly class Csrf
{
    public const TOKEN_ID = 'moonshine';

    public const FIELD = '_token';

    public const HEADERS = ['X-CSRF-TOKEN', 'X-XSRF-TOKEN'];

    public const COOKIE = 'XSRF-TOKEN';

    public function __construct(private ?CsrfTokenManagerInterface $tokenManager = null) {}

    public function isEnabled(): bool
    {
        return $this->tokenManager !== null;
    }

    public function getToken(): string
    {
        return $this->tokenManager?->getToken(self::TOKEN_ID)->getValue() ?? '';
    }

    public function isValid(?string $value): bool
    {
        if ($this->tokenManager === null) {
            return true;
        }

        return \is_string($value) && $value !== ''
            && $this->tokenManager->isTokenValid(new CsrfToken(self::TOKEN_ID, $value));
    }

    public function field(): string
    {
        if (! $this->isEnabled()) {
            return '';
        }

        return \sprintf(
            '<input type="hidden" name="%s" value="%s" autocomplete="off">',
            self::FIELD,
            htmlspecialchars($this->getToken(), \ENT_QUOTES),
        );
    }
}
