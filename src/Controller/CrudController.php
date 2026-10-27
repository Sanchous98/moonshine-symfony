<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Controller;

use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\DependencyInjection\RequestContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\Crud\Resources\CrudResource;
use MoonShine\Support\Enums\Ability;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\ToastType;
use MoonShine\Symfony\DependencyInjection\Request;
use MoonShine\Symfony\Form\FormProcessor;
use MoonShine\Symfony\Form\FormResult;
use MoonShine\UI\Enums\HtmlMode;
use Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
use Throwable;

#[AsController]
final class CrudController extends MoonShineController
{
    public function __construct(
        CoreContract $core,
        private readonly FormProcessor $formProcessor,
        private readonly ?LoggerInterface $logger = null,
    ) {
        parent::__construct($core);
    }

    #[Route('/resource/{resourceUri}/crud/mass-delete', name: 'moonshine.crud.massDelete', methods: ['POST', 'DELETE'])]
    public function massDelete(CrudRequestContract $crudRequest, RequestContract $request): Response
    {
        $resource = $crudRequest->getResource();

        if (! $resource instanceof CrudResource) {
            return $this->resourceNotFound();
        }

        if ($denied = $this->denyUnlessAllowed($resource, Action::MASS_DELETE, Ability::MASS_DELETE)) {
            return $denied;
        }

        $resource->setActivePage($resource->getIndexPage());

        $ids = $request->get('ids', []);

        try {
            $resource->massDelete(\is_array($ids) ? array_values(array_filter(
                $ids,
                static fn (mixed $id): bool => \is_int($id) || \is_string($id),
            )) : []);
        } catch (Throwable $e) {
            return $this->failed($resource, $e);
        }

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

        $item = $resource->getItemOrFail();

        if ($denied = $this->denyUnlessAllowed($resource->setItem($item), Action::DELETE, Ability::DELETE)) {
            return $denied;
        }

        try {
            $resource->delete($resource->getCaster()->cast($item));
        } catch (Throwable $e) {
            return $this->failed($resource, $e);
        }

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

        $exists = $crudRequest->getItemID() !== null;
        $item = $exists ? $resource->getItemOrFail() : $resource->getDataInstance();

        $denied = $exists
            ? $this->denyUnlessAllowed($resource->setItem($item), Action::UPDATE, Ability::UPDATE)
            : $this->denyUnlessAllowed($resource, Action::CREATE, Ability::CREATE);

        if ($denied !== null) {
            return $denied;
        }

        $resource->setActivePage($resource->getFormPage());

        $result = $this->formProcessor->process($resource, $item, $this->symfonyRequest($request));

        if (! $result->isValid()) {
            return $this->validationFailed($resource, $request, $result);
        }

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
            $item = $resource->save($resource->getCaster()->cast($result->item), $result->fields);
        } catch (Throwable $e) {
            return $this->failed($resource, $e);
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

    private function validationFailed(CrudResource $resource, RequestContract $request, FormResult $result): Response
    {
        $errors = $result->errors;

        if ($request->isAjax() || $request->isWantsJson()) {
            return new SymfonyJsonResponse([
                'message' => $result->getFirstError(),
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $symfonyRequest = $this->symfonyRequest($request);
        $session = $symfonyRequest->hasSession() ? $symfonyRequest->getSession() : null;

        if ($session instanceof FlashBagAwareSessionInterface) {
            $formName = $request->getScalar('_component_name');
            $formName = \is_string($formName) && $formName !== '' ? $formName : $resource->getUriKey();

            $session->getFlashBag()->add(Request::ERRORS_KEY, [$formName => $errors]);
            $session->getFlashBag()->add(Request::OLD_INPUT_KEY, $request->getExcept(['_token', 'password']));
        }

        $back = $symfonyRequest->headers->get('referer');

        return $this->redirect(\is_string($back) && $back !== ''
            ? $back
            : $resource->getFormPageUrl($resource->getCastedData()));
    }

    private function failed(CrudResource $resource, Throwable $e): Response
    {
        $this->logger?->error($e->getMessage(), ['exception' => $e]);

        return $resource->modifyErrorResponse(
            JsonResponse::make()->toast($e->getMessage(), ToastType::ERROR)->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR),
            $e,
        );
    }

    private function symfonyRequest(RequestContract $request): SymfonyRequest
    {
        if ($request instanceof Request) {
            return $request->getSymfonyRequest();
        }

        throw new \LogicException('MoonShine request is not bound to the Symfony request.');
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
