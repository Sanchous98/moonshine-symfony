<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Notifications;

use MoonShine\Crud\Contracts\Notifications\MoonShineNotificationContract;
use MoonShine\Support\Enums\Color;
use Symfony\Component\Notifier\Channel\ChannelInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * "moonshine" channel of Symfony Notifier: shows notifications in the admin panel
 */
final readonly class MoonShineChannel implements ChannelInterface
{
    public const NAME = 'moonshine';

    public function __construct(private MoonShineNotificationContract $notifications) {}

    public function notify(Notification $notification, RecipientInterface $recipient, ?string $transportName = null): void
    {
        $message = $notification->getSubject();

        if ($notification->getContent() !== '') {
            $message = $message === '' ? $notification->getContent() : $message . ': ' . $notification->getContent();
        }

        $this->notifications->notify(
            message: $message,
            button: $notification instanceof MoonShineNotification ? $notification->getButton() : null,
            ids: $recipient instanceof AdminRecipient ? $recipient->getIdentifiers() : [],
            color: $notification instanceof MoonShineNotification ? $notification->getColor() : self::color($notification->getImportance()),
        );
    }

    public function supports(Notification $notification, RecipientInterface $recipient): bool
    {
        return true;
    }

    private static function color(string $importance): Color
    {
        return match ($importance) {
            Notification::IMPORTANCE_URGENT, Notification::IMPORTANCE_HIGH => Color::RED,
            Notification::IMPORTANCE_MEDIUM => Color::YELLOW,
            default => Color::GREEN,
        };
    }
}
