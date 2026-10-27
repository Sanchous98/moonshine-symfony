<?php

declare(strict_types=1);

namespace MoonShine\Symfony\EventSubscriber;

use MoonShine\Crud\Exceptions\NotFoundException;
use MoonShine\Crud\Exceptions\UnauthorizedException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Converts MoonShine exceptions into HTTP exceptions
 */
final class ExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onException', 10],
        ];
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof NotFoundException) {
            $event->setThrowable(new NotFoundHttpException($exception->getMessage() ?: 'Not found', $exception));
        }

        if ($exception instanceof UnauthorizedException) {
            $event->setThrowable(new AccessDeniedHttpException($exception->getMessage() ?: 'Access Denied.', $exception));
        }
    }
}
