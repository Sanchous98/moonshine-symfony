<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Notifications;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use MoonShine\Crud\Contracts\Notifications\MoonShineNotificationContract;
use MoonShine\Crud\Contracts\Notifications\NotificationButtonContract;
use MoonShine\Support\Enums\Color;
use MoonShine\Symfony\Security\Auth;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Admin panel notifications of the current user.
 * Send them with MoonShine::notify() or the "moonshine" channel of Symfony Notifier.
 *
 * @implements MoonShineNotificationContract<NotificationItem>
 */
final readonly class NotificationService implements MoonShineNotificationContract
{
    public function __construct(
        private NotificationStorage $storage,
        private Auth $auth,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @param array<int|string> $ids user identifiers, all users when empty
     */
    public function notify(
        string $message,
        ?NotificationButtonContract $button = null,
        array $ids = [],
        string|Color|null $color = null,
        ?string $icon = null,
    ): void {
        $item = new NotificationItem(
            id: self::uuid(),
            message: $message,
            color: $color instanceof Color ? $color->value : $color,
            date: new DateTimeImmutable(),
            button: $button,
            icon: $icon,
        );

        if ($ids === []) {
            $this->storage->add(NotificationStorage::BROADCAST, $item);

            return;
        }

        foreach (array_unique(array_map('strval', $ids)) as $recipient) {
            $this->storage->add($recipient, $item);
        }
    }

    /**
     * @return Collection<string, NotificationItem>
     */
    public function getAll(): Collection
    {
        $recipient = $this->recipient();

        if ($recipient === null) {
            return new Collection();
        }

        $broadcasts = array_diff_key(
            $this->storage->get(NotificationStorage::BROADCAST),
            $this->storage->getRead($recipient),
        );

        return (new Collection([...$this->storage->get($recipient), ...$broadcasts]))
            ->sortByDesc(static fn (NotificationItem $item): int => $item->getDate()->getTimestamp())
            ->map(fn (NotificationItem $item): NotificationItem => $item->withReadRoute(
                $this->urlGenerator->generate('moonshine.notifications.read', ['id' => $item->getId()]),
            ));
    }

    public function readAll(): void
    {
        $recipient = $this->recipient();

        if ($recipient === null) {
            return;
        }

        $this->storage->clear($recipient);
        $this->storage->markRead($recipient, array_keys($this->storage->get(NotificationStorage::BROADCAST)));
    }

    public function markAsRead(int|string $id): void
    {
        $recipient = $this->recipient();

        if ($recipient === null) {
            return;
        }

        $id = (string) $id;

        if (isset($this->storage->get($recipient)[$id])) {
            $this->storage->remove($recipient, $id);

            return;
        }

        $this->storage->markRead($recipient, [$id]);
    }

    public function getReadAllRoute(): string
    {
        return $this->urlGenerator->generate('moonshine.notifications.readAll');
    }

    private function recipient(): ?string
    {
        return $this->auth->getUser()?->getUserIdentifier();
    }

    private static function uuid(): string
    {
        return class_exists(Uuid::class) ? Uuid::v7()->toRfc4122() : bin2hex(random_bytes(16));
    }
}
