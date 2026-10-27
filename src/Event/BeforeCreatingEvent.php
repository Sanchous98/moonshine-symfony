<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Event;

/**
 * Dispatched before a new item is saved, listeners may change the item
 */
final class BeforeCreatingEvent extends ResourceEvent
{
}
