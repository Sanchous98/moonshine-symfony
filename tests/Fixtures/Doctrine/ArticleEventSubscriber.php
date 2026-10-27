<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Fixtures\Doctrine;

use MoonShine\Symfony\Event\AfterCreatedEvent;
use MoonShine\Symfony\Event\AfterDeletedEvent;
use MoonShine\Symfony\Event\AfterMassDeletedEvent;
use MoonShine\Symfony\Event\AfterUpdatedEvent;
use MoonShine\Symfony\Event\BeforeCreatingEvent;
use MoonShine\Symfony\Event\BeforeDeletingEvent;
use MoonShine\Symfony\Event\BeforeMassDeletingEvent;
use MoonShine\Symfony\Event\BeforeUpdatingEvent;
use MoonShine\Symfony\Event\MassDeleteEvent;
use MoonShine\Symfony\Event\ResourceEvent;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ArticleEventSubscriber implements EventSubscriberInterface
{
    /** @var list<string> */
    public static array $log = [];

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeCreatingEvent::class => 'beforeSave',
            BeforeUpdatingEvent::class => 'beforeSave',
            AfterCreatedEvent::class => 'log',
            AfterUpdatedEvent::class => 'log',
            BeforeDeletingEvent::class => 'beforeDeleting',
            AfterDeletedEvent::class => 'log',
            BeforeMassDeletingEvent::class => 'logMass',
            AfterMassDeletedEvent::class => 'logMass',
        ];
    }

    public function beforeSave(ResourceEvent $event): void
    {
        $this->log($event);

        $article = $event->getData();

        // listeners see the submitted values and can change them
        if ($event->isFor(ArticleResource::class) && $article instanceof Article) {
            $article->setTitle(rtrim($article->getTitle(), '!'));
        }
    }

    public function beforeDeleting(BeforeDeletingEvent $event): void
    {
        $this->log($event);

        $article = $event->getData();

        if ($article instanceof Article && $article->getTitle() === 'Protected') {
            throw new RuntimeException('This article cannot be deleted');
        }
    }

    public function log(ResourceEvent $event): void
    {
        $article = $event->getData();
        $title = $article instanceof Article ? $article->getTitle() : '?';

        self::$log[] = (new \ReflectionClass($event))->getShortName() . ':' . $title;
    }

    public function logMass(MassDeleteEvent $event): void
    {
        self::$log[] = (new \ReflectionClass($event))->getShortName() . ':' . implode(',', $event->getIds());
    }
}
