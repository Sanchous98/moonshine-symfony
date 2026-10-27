<?php

declare(strict_types=1);

namespace MoonShine\Symfony\EventSubscriber;

use MoonShine\Contracts\Core\DependencyInjection\ConfiguratorContract;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies the locale chosen with the locale switcher of the admin panel
 */
final readonly class LocaleSubscriber implements EventSubscriberInterface
{
    private const SESSION_KEY = '_moonshine_locale';

    public function __construct(
        private ConfiguratorContract $config,
        private string $defaultLocale = 'en',
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // after routing (32), before the framework locale listener (16)
            KernelEvents::REQUEST => ['onRequest', 20],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (! \is_string($route) || ! str_starts_with($route, 'moonshine.')) {
            return;
        }

        $available = [];

        foreach ($this->config->getLocales() as $code => $label) {
            $available[] = \is_int($code) ? $label : $code;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        $requested = $request->query->get($this->config->getLocaleKey());

        if (\is_string($requested) && \in_array($requested, $available, true)) {
            $session?->set(self::SESSION_KEY, $requested);
            $request->setLocale($requested);

            return;
        }

        $stored = $session?->get(self::SESSION_KEY);

        $request->setLocale(\is_string($stored) && \in_array($stored, $available, true) ? $stored : $this->defaultLocale);
    }
}
