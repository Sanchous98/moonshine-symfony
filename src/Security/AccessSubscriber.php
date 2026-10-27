<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Protects every admin route when authentication is enabled,
 * independently of the application's access_control rules
 */
final readonly class AccessSubscriber implements EventSubscriberInterface
{
    public const PUBLIC_ROUTES = ['moonshine.login', 'moonshine.authenticate', 'moonshine.logout'];

    public function __construct(
        private Auth $auth,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // after the firewall (8) authenticated the user, before CSRF check (6)
            KernelEvents::REQUEST => ['onRequest', 7],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if (! $event->isMainRequest()
            || ! $this->auth->isEnabled()
            || ! \is_string($route)
            || ! str_starts_with($route, 'moonshine.')
            || \in_array($route, self::PUBLIC_ROUTES, true)
            || $this->auth->isGranted()
        ) {
            return;
        }

        if ($this->auth->getUser() !== null) {
            throw new AccessDeniedHttpException('Access to the admin panel is denied.');
        }

        $loginUrl = $this->urlGenerator->generate('moonshine.login');

        $event->setResponse($this->isAsync($request)
            ? new JsonResponse(['message' => 'Unauthenticated.', 'redirect' => $loginUrl], 401)
            : new RedirectResponse($loginUrl));
    }

    private function isAsync(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || str_contains((string) $request->headers->get('Accept'), 'json');
    }
}
