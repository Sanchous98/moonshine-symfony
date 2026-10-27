<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Event;

/**
 * Dispatched before an item is deleted, throw to prevent the deletion
 */
final class BeforeDeletingEvent extends ResourceEvent
{
}
