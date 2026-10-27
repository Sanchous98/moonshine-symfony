<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Notifications;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Keeps notifications in a PSR-6 cache pool. Use a persistent pool
 * (Redis, Doctrine DBAL, ...) to keep them between deployments.
 */
final readonly class NotificationStorage
{
    public const BROADCAST = '*';

    private const MAX_ITEMS = 100;

    public function __construct(private CacheItemPoolInterface $pool) {}

    public function add(string $recipient, NotificationItem $item): void
    {
        $items = [$item->getId() => $item, ...$this->get($recipient)];

        $this->save($this->key($recipient), \array_slice($items, 0, self::MAX_ITEMS, true));
    }

    /**
     * @return array<string, NotificationItem> newest first
     */
    public function get(string $recipient): array
    {
        $items = $this->pool->getItem($this->key($recipient))->get();

        return \is_array($items) ? array_filter($items, static fn (mixed $item): bool => $item instanceof NotificationItem) : [];
    }

    public function remove(string $recipient, string $id): void
    {
        $items = $this->get($recipient);
        unset($items[$id]);

        $this->save($this->key($recipient), $items);
    }

    public function clear(string $recipient): void
    {
        $this->pool->deleteItem($this->key($recipient));
    }

    /**
     * Broadcast notifications read by the recipient
     *
     * @return array<string, true>
     */
    public function getRead(string $recipient): array
    {
        $read = $this->pool->getItem($this->key($recipient, 'read'))->get();

        return \is_array($read) ? $read : [];
    }

    /**
     * @param list<string> $ids
     */
    public function markRead(string $recipient, array $ids): void
    {
        $read = $this->getRead($recipient) + array_fill_keys($ids, true);

        // forget marks of broadcasts that no longer exist
        $read = array_intersect_key($read, $this->get(self::BROADCAST));

        $this->save($this->key($recipient, 'read'), $read);
    }

    private function save(string $key, mixed $value): void
    {
        $this->pool->save($this->pool->getItem($key)->set($value));
    }

    private function key(string $recipient, string $suffix = 'items'): string
    {
        return 'moonshine_notifications.' . $suffix . '.' . hash('xxh128', $recipient);
    }
}
