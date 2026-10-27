<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Controller;

use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Crud\Contracts\HasHandlersContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Support\Enums\ToastType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class HandlerController extends MoonShineController
{
    #[Route('/resource/{resourceUri}/{handlerUri}/handler', name: 'moonshine.handler', methods: ['GET', 'POST'])]
    public function __invoke(CrudRequestContract $crudRequest, string $handlerUri): Response
    {
        $resource = $crudRequest->getResource();

        if (! $resource instanceof HasHandlersContract) {
            return JsonResponse::make()->toast('Resource not found', ToastType::ERROR)->setStatusCode(Response::HTTP_NOT_FOUND);
        }

        $handler = $resource->getHandlers()->findByUri($handlerUri);

        if ($handler === null) {
            return JsonResponse::make()->toast('Handler not found', ToastType::ERROR)->setStatusCode(Response::HTTP_NOT_FOUND);
        }

        return $handler->handle();
    }
}
