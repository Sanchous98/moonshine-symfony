<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Controller;

use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\DependencyInjection\RequestContract;
use MoonShine\Contracts\UI\TableBuilderContract;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/component/{pageUri}/{resourceUri?}', name: 'moonshine.component', methods: ['GET'])]
final class ComponentController extends MoonShineController
{
    public function __invoke(CrudRequestContract $crudRequest, RequestContract $request): Response
    {
        $component = $crudRequest->getPage()->getComponents()->findByName(
            $crudRequest->getComponentName(),
        );

        if ($component === null) {
            return $this->response('');
        }

        if ($component instanceof TableBuilderContract) {
            $key = $request->getScalar('_key');
            $component = $this->responseWithTable($component, \is_int($key) || \is_string($key) ? $key : null);
        }

        if (\is_string($component)) {
            return $this->response($component);
        }

        return $this->response($component->render());
    }
}
