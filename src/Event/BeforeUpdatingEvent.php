<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Event;

/**
 * Dispatched before an existing item is saved, listeners may change the item
 */
final class BeforeUpdatingEvent extends ResourceEvent
{
}
