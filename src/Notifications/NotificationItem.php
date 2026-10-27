<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Notifications;

use DateTimeImmutable;
use DateTimeInterface;
use MoonShine\Crud\Contracts\Notifications\NotificationButtonContract;
use MoonShine\Crud\Contracts\Notifications\NotificationItemContract;

final readonly class NotificationItem implements NotificationItemContract
{
    public function __construct(
        private string $id,
        private string $message,
        private ?string $color = null,
        private DateTimeInterface $date = new DateTimeImmutable(),
        private ?NotificationButtonContract $button = null,
        private ?string $icon = null,
        private string $readRoute = '',
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function withReadRoute(string $readRoute): self
    {
        return new self($this->id, $this->message, $this->color, $this->date, $this->button, $this->icon, $readRoute);
    }

    public function getReadRoute(): string
    {
        return $this->readRoute;
    }

    public function getColor(): string
    {
        return $this->color ?? 'green';
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getIcon(): string
    {
        return $this->icon ?? 'information-circle';
    }

    public function getDate(): DateTimeInterface
    {
        return $this->date;
    }

    public function getButton(): ?NotificationButtonContract
    {
        return $this->button;
    }
}
