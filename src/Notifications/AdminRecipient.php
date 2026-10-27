<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Notifications;

use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Admin panel users addressed by their user identifiers, all users when empty
 */
final readonly class AdminRecipient implements RecipientInterface
{
    /** @var list<string> */
    private array $identifiers;

    public function __construct(string ...$identifiers)
    {
        $this->identifiers = array_values($identifiers);
    }

    /**
     * @return list<string>
     */
    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }
}
