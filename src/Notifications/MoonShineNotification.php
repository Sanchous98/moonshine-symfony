<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Notifications;

use MoonShine\Crud\Contracts\Notifications\NotificationButtonContract;
use MoonShine\Support\Enums\Color;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * Notifier notification with MoonShine specific presentation
 */
class MoonShineNotification extends Notification
{
    private ?NotificationButtonContract $button = null;

    private string|Color|null $color = null;

    /**
     * @param list<string> $channels
     */
    public function __construct(string $subject = '', array $channels = [MoonShineChannel::NAME])
    {
        parent::__construct($subject, $channels);
    }

    public function button(NotificationButtonContract $button): static
    {
        $this->button = $button;

        return $this;
    }

    public function getButton(): ?NotificationButtonContract
    {
        return $this->button;
    }

    public function color(string|Color $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getColor(): string|Color|null
    {
        return $this->color;
    }
}
