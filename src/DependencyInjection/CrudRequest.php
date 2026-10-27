<?php

declare(strict_types=1);

namespace MoonShine\Symfony\DependencyInjection;

use Illuminate\Support\Str;
use MoonShine\Contracts\Core\CrudResourceContract;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\PageContract;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class CrudRequest implements CrudRequestContract
{
    public function __construct(
        private CoreContract $core,
        private RequestStack $requestStack,
    ) {}

    public function getResource(): ?CrudResourceContract
    {
        $uri = $this->getResourceUri();

        if ($uri === null) {
            return null;
        }

        $resource = $this->core->getResources()->findByUri($uri);

        return $resource instanceof CrudResourceContract ? $resource : null;
    }

    public function getResourceUri(): ?string
    {
        return $this->getRouteParam('resourceUri');
    }

    public function hasResource(): bool
    {
        return $this->getResource() !== null;
    }

    public function findPage(): ?PageContract
    {
        $uri = $this->getPageUri();

        if ($uri === null) {
            return null;
        }

        $resource = $this->getResource();

        if ($resource !== null) {
            return $resource->getPages()->findByUri($uri);
        }

        return $this->core->getPages()->findByUri($uri);
    }

    public function getPage(): PageContract
    {
        return $this->findPage() ?? throw new NotFoundHttpException('MoonShine page not found');
    }

    public function getPageUri(): ?string
    {
        return $this->getRouteParam('pageUri');
    }

    public function getItemID(): int|string|null
    {
        $id = $this->core->getRequest()->getScalar('resourceItem');

        if (\is_int($id) || \is_string($id)) {
            return $id;
        }

        return $this->getRouteParam('resourceItem');
    }

    public function getComponentName(): string
    {
        $name = $this->core->getRequest()->getScalar('_component_name', '');

        return Str::of(\is_string($name) ? $name : '')
            ->before('-unique-')
            ->value();
    }

    public function getFragmentLoad(): ?string
    {
        $fragment = $this->core->getRequest()->getScalar('_fragment-load');

        return \is_string($fragment) ? $fragment : null;
    }

    public function isFragmentLoad(?string $name = null): bool
    {
        $fragment = $this->getFragmentLoad();

        if ($fragment !== null && $name !== null) {
            return $fragment === $name;
        }

        return $fragment !== null;
    }

    public function isMoonShineRequest(): bool
    {
        $route = $this->requestStack->getCurrentRequest()?->attributes->get('_route');

        return \is_string($route) && str_starts_with($route, 'moonshine.');
    }

    public function getParentResourceId(): ?string
    {
        $parentId = $this->core->getRequest()->getScalar('_parentId');

        return \is_string($parentId) ? $parentId : null;
    }

    public function getParentRelationName(): ?string
    {
        if (($parentResource = $this->getParentResourceId()) === null) {
            return null;
        }

        return Str::of($parentResource)
            ->replace('-' . $this->getParentRelationId(), '')
            ->camel()
            ->value();
    }

    public function getParentRelationId(): ?string
    {
        $parentResource = $this->getParentResourceId();

        return $parentResource === null ? null : Str::of($parentResource)->after('-')->value();
    }

    private function getRouteParam(string $name): ?string
    {
        $value = $this->requestStack->getCurrentRequest()?->attributes->get($name);

        return \is_scalar($value) && $value !== '' ? (string) $value : null;
    }
}
