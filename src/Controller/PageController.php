<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Controller;

use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class PageController extends MoonShineController
{
    #[Route('/page/{pageUri}', name: 'moonshine.page', methods: ['GET'])]
    public function default(CrudRequestContract $crudRequest): Response
    {
        return $this->response($crudRequest->getPage()->render());
    }

    #[Route('/resource/{resourceUri}/{pageUri}/{resourceItem?}', name: 'moonshine.resource.page', methods: ['GET'])]
    public function resource(CrudRequestContract $crudRequest): Response
    {
        if (! $crudRequest->hasResource()) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('MoonShine resource not found');
        }

        return $this->response($crudRequest->getPage()->render());
    }
}
