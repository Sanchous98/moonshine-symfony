<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Controller;

use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\DependencyInjection\RequestContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Crud\Resources\CrudResource;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Enums\HtmlMode;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[AsController]
final class CrudController extends MoonShineController
{
    #[Route('/resource/{resourceUri}/crud/mass-delete', name: 'moonshine.crud.massDelete', methods: ['POST', 'DELETE'])]
    public function massDelete(CrudRequestContract $crudRequest, RequestContract $request): Response
    {
        $resource = $crudRequest->getResource();

        if (! $resource instanceof CrudResource) {
            return $this->resourceNotFound();
        }

        $resource->setActivePage($resource->getIndexPage());

        $ids = $request->get('ids', []);

        $resource->massDelete(\is_array($ids) ? array_values(array_filter(
            $ids,
            static fn (mixed $id): bool => \is_int($id) || \is_string($id),
        )) : []);

        return JsonResponse::make()
            ->toast($this->trans('moonshine::ui.deleted'))
            ->redirect($resource->getIndexPageUrl());
    }

    #[Route('/resource/{resourceUri}/crud', name: 'moonshine.crud.store', methods: ['POST'])]
    public function store(CrudRequestContract $crudRequest, RequestContract $request): Response
    {
        return $this->storeOrUpdate($crudRequest, $request);
    }

    #[Route('/resource/{resourceUri}/crud/{resourceItem}/update', name: 'moonshine.crud.update', methods: ['POST', 'PUT'])]
    public function update(CrudRequestContract $crudRequest, RequestContract $request): Response
    {
        return $this->storeOrUpdate($crudRequest, $request);
    }

    #[Route('/resource/{resourceUri}/crud/{resourceItem}/delete', name: 'moonshine.crud.destroy', methods: ['POST', 'DELETE'])]
    public function destroy(CrudRequestContract $crudRequest): Response
    {
        $resource = $crudRequest->getResource();

        if (! $resource instanceof CrudResource) {
            return $this->resourceNotFound();
        }

        $resource->setActivePage($resource->getIndexPage());

        $resource->delete(
            $resource->getCaster()->cast($resource->getItemOrFail()),
        );

        return JsonResponse::make()
            ->toast($this->trans('moonshine::ui.deleted'))
            ->redirect($resource->getIndexPageUrl());
    }

    private function storeOrUpdate(CrudRequestContract $crudRequest, RequestContract $request): Response
    {
        $resource = $crudRequest->getResource();

        if (! $resource instanceof CrudResource) {
            return $this->resourceNotFound();
        }

        $item = $resource->getItemOrInstance();

        $resource->setActivePage($resource->getFormPage());

        $redirectRoute = static function (CrudResource $resource) use ($request): ?string {
            if ($request->has('_without-redirect')) {
                return null;
            }

            $redirect = $request->get('_redirect', $resource->getRedirectAfterSave());

            if ($redirect === null && ! $resource->isCreateInModal() && $resource->isRecentlyCreated()) {
                return $resource->getFormPageUrl($resource->getCastedData());
            }

            return \is_string($redirect) ? $redirect : null;
        };

        try {
            $item = $resource->save($resource->getCaster()->cast($item));
        } catch (Throwable $e) {
            return $resource->modifyErrorResponse(
                JsonResponse::make()->toast($e->getMessage(), ToastType::ERROR)->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR),
                $e,
            );
        }

        $resource->setItem($item->getOriginal());

        if ($request->isAjax() || $request->isWantsJson()) {
            $data = [];
            $castedData = $resource->getCastedData();

            $resource
                ->getFormFields()
                ->onlyFields()
                ->refreshFields()
                ->fillCloned($castedData?->toArray() ?? [], $castedData)
                ->each(function (FieldContract $field) use (&$data): void {
                    $data['htmlData'][] = [
                        'html' => $this->renderToString($field->resolveRefreshAfterApply()->render()),
                        'selector' => "[data-field-selector='{$field->getNameDot()}']",
                        'htmlMode' => HtmlMode::OUTER_HTML->value,
                    ];
                });

            $redirect = $redirectRoute($resource);

            return $resource->modifySaveResponse(
                JsonResponse::make($data)
                    ->toast($this->trans('moonshine::ui.saved'))
                    ->when(
                        $redirect !== null,
                        static fn (JsonResponse $response): JsonResponse => $response->redirect((string) $redirect),
                    )
                    ->setStatusCode($resource->isRecentlyCreated() ? Response::HTTP_CREATED : Response::HTTP_OK),
            );
        }

        return $this->redirect(
            $redirectRoute($resource) ?? $resource->getFormPageUrl($resource->getCastedData()),
        );
    }

    private function resourceNotFound(): Response
    {
        return JsonResponse::make()
            ->toast('Resource not found', ToastType::ERROR)
            ->setStatusCode(Response::HTTP_NOT_FOUND);
    }

    private function trans(string $key): string
    {
        $value = $this->core->getTranslator()->get($key);

        return \is_string($value) ? $value : $key;
    }
}
