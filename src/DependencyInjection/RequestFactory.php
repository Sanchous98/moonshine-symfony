<?php

declare(strict_types=1);

namespace MoonShine\Symfony\DependencyInjection;

use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestFactory
{
    public static function create(RequestStack $requestStack): Request
    {
        $symfonyRequest = $requestStack->getCurrentRequest() ?? SymfonyRequest::createFromGlobals();

        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        return new Request(
            $psrHttpFactory->createRequest($symfonyRequest),
            $symfonyRequest,
        );
    }
}
