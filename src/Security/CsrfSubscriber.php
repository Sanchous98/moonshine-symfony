<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Verifies the CSRF token of state changing admin requests.
 * The token comes from the "_token" form field or from the X-XSRF-TOKEN header
 * that the MoonShine frontend (axios) sends from the XSRF-TOKEN cookie.
 */
final readonly class CsrfSubscriber implements EventSubscriberInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

    /**
     * Login is verified by the security firewall itself
     */
    private const EXCLUDED_ROUTES = ['moonshine.authenticate'];

    public function __construct(private Csrf $csrf) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // after routing (32), after the firewall (8)
            KernelEvents::REQUEST => ['onRequest', 6],
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (! $event->isMainRequest()
            || ! $this->csrf->isEnabled()
            || ! $this->isMoonShineRoute($request)
            || \in_array($request->getRealMethod(), self::SAFE_METHODS, true)
            || \in_array($request->attributes->get('_route'), self::EXCLUDED_ROUTES, true)
        ) {
            return;
        }

        if (! $this->csrf->isValid($this->extractToken($request))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        if (! $event->isMainRequest() || ! $this->csrf->isEnabled() || ! $this->isMoonShineRoute($request)) {
            return;
        }

        $event->getResponse()->headers->setCookie(
            Cookie::create(Csrf::COOKIE)
                ->withValue($this->csrf->getToken())
                ->withSecure($request->isSecure())
                ->withHttpOnly(false)
                ->withSameSite(Cookie::SAMESITE_LAX),
        );
    }

    private function extractToken(Request $request): ?string
    {
        $token = $request->request->get(Csrf::FIELD);

        if (\is_string($token) && $token !== '') {
            return $token;
        }

        $payload = $request->getContentTypeFormat() === 'json' ? $request->getPayload()->get(Csrf::FIELD) : null;

        if (\is_string($payload) && $payload !== '') {
            return $payload;
        }

        foreach (Csrf::HEADERS as $header) {
            $value = $request->headers->get($header);

            if (\is_string($value) && $value !== '') {
                return urldecode($value);
            }
        }

        return null;
    }

    private function isMoonShineRoute(Request $request): bool
    {
        $route = $request->attributes->get('_route');

        return \is_string($route) && str_starts_with($route, 'moonshine.');
    }
}
