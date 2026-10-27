<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Controller;

use Illuminate\Contracts\Support\Renderable;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\UI\TableBuilderContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Crud\Resources\CrudResource;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\PageType;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Contracts\UI\TableRowContract;
use Stringable;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class MoonShineController
{
    public function __construct(
        protected readonly CoreContract $core,
    ) {}

    protected function response(mixed $content, int $status = Response::HTTP_OK): Response
    {
        return new Response($this->renderToString($content), $status, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    protected function redirect(string $url): RedirectResponse
    {
        return new RedirectResponse($url);
    }

    /**
     * Write endpoints check the same permissions as the buttons and pages
     */
    protected function denyUnlessAllowed(CrudResource $resource, Action $action, Ability $ability): ?Response
    {
        if ($resource->hasAction($action) && $resource->can($ability)) {
            return null;
        }

        return $this->accessDenied();
    }

    /**
     * Async endpoints of a resource page (components, reactive fields) require the access of the page itself
     */
    protected function authorizeResourcePage(CrudRequestContract $crudRequest): ?Response
    {
        $resource = $crudRequest->getResource();

        if (! $resource instanceof CrudResource) {
            return null;
        }

        $ability = match ($crudRequest->getPage()->getPageType()) {
            PageType::DETAIL => Ability::VIEW,
            PageType::FORM => $resource->getItemID() === null ? Ability::CREATE : Ability::UPDATE,
            default => Ability::VIEW_ANY,
        };

        $action = match ($ability) {
            Ability::VIEW_ANY => null,
            Ability::CREATE => Action::CREATE,
            Ability::UPDATE => Action::UPDATE,
            default => Action::VIEW,
        };

        if (($action === null || $resource->hasAction($action)) && $resource->can($ability)) {
            return null;
        }

        return $this->accessDenied();
    }

    protected function accessDenied(): Response
    {
        return JsonResponse::make()
            ->toast('Access Denied.', ToastType::ERROR)
            ->setStatusCode(Response::HTTP_FORBIDDEN);
    }

    protected function renderToString(mixed $value): string
    {
        if ($value instanceof Renderable) {
            return $value->render();
        }

        if ($value instanceof Stringable || \is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @throws \Throwable
     */
    protected function responseWithTable(
        TableBuilderContract $table,
        int|string|null $key = null,
    ): TableBuilderContract|TableRowContract|string {
        if ($key === null) {
            return $table;
        }

        // Async row refresh: render only the requested row
        $row = $table->getRows()->first(
            static fn (TableRowContract $row): bool => (string) $row->getKey() === (string) $key,
        );

        return $row ?? '';
    }
}
