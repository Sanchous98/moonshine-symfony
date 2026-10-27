<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Resources;

use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Symfony\Event\AfterCreatedEvent;
use MoonShine\Symfony\Event\AfterDeletedEvent;
use MoonShine\Symfony\Event\AfterMassDeletedEvent;
use MoonShine\Symfony\Event\AfterUpdatedEvent;
use MoonShine\Symfony\Event\BeforeCreatingEvent;
use MoonShine\Symfony\Event\BeforeDeletingEvent;
use MoonShine\Symfony\Event\BeforeMassDeletingEvent;
use MoonShine\Symfony\Event\BeforeUpdatingEvent;
use MoonShine\Symfony\Event\ResourceEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Dispatches the MoonShine resource hooks as Symfony events.
 * Resources that override a hook must call the parent implementation to keep the event.
 *
 * @mixin \MoonShine\Crud\Resources\CrudResource
 */
trait DispatchesResourceEvents
{
    private ?EventDispatcherInterface $eventDispatcher = null;

    #[Required]
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    protected function beforeCreating(DataWrapperContract $item): DataWrapperContract
    {
        return $this->dispatchItemEvent(new BeforeCreatingEvent($this, $item));
    }

    protected function afterCreated(DataWrapperContract $item): DataWrapperContract
    {
        return $this->dispatchItemEvent(new AfterCreatedEvent($this, $item));
    }

    protected function beforeUpdating(DataWrapperContract $item): DataWrapperContract
    {
        return $this->dispatchItemEvent(new BeforeUpdatingEvent($this, $item));
    }

    protected function afterUpdated(DataWrapperContract $item): DataWrapperContract
    {
        return $this->dispatchItemEvent(new AfterUpdatedEvent($this, $item));
    }

    protected function beforeDeleting(DataWrapperContract $item): DataWrapperContract
    {
        return $this->dispatchItemEvent(new BeforeDeletingEvent($this, $item));
    }

    protected function afterDeleted(DataWrapperContract $item): DataWrapperContract
    {
        return $this->dispatchItemEvent(new AfterDeletedEvent($this, $item));
    }

    /**
     * @param list<int|string> $ids
     */
    protected function beforeMassDeleting(array $ids): void
    {
        $this->eventDispatcher?->dispatch(new BeforeMassDeletingEvent($this, $ids));
    }

    /**
     * @param list<int|string> $ids
     */
    protected function afterMassDeleted(array $ids): void
    {
        $this->eventDispatcher?->dispatch(new AfterMassDeletedEvent($this, $ids));
    }

    private function dispatchItemEvent(ResourceEvent $event): DataWrapperContract
    {
        return ($this->eventDispatcher?->dispatch($event) ?? $event)->getItem();
    }
}
