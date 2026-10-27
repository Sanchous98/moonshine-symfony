<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Controller;

use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\DependencyInjection\RequestContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Resources\CrudResource;
use MoonShine\Support\Enums\Ability;
use MoonShine\UI\Contracts\HasUpdateOnPreviewContract;
use MoonShine\Support\Enums\Action;
use MoonShine\Symfony\DependencyInjection\Request;
use MoonShine\Symfony\Form\FormProcessor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Saves a single field edited in the table (updateOnPreview)
 */
#[AsController]
final class UpdateFieldController extends MoonShineController
{
    public function __construct(
        CoreContract $core,
        private readonly FormProcessor $formProcessor,
    ) {
        parent::__construct($core);
    }

    #[Route('/update-field/column/{resourceUri}/{resourceItem}', name: 'moonshine.update-field.through-column', methods: ['PUT', 'POST'])]
    public function throughColumn(CrudRequestContract $crudRequest, RequestContract $request): Response
    {
        $resource = $crudRequest->getResource();

        if (! $resource instanceof CrudResource) {
            return $this->error('Resource not found', Response::HTTP_NOT_FOUND);
        }

        $column = $request->getScalar('field');

        $field = \is_string($column) ? $this->findField($resource, $column) : null;

        if ($field === null) {
            return $this->error('Field not found', Response::HTTP_NOT_FOUND);
        }

        if (! $field instanceof HasUpdateOnPreviewContract || ! $field->isUpdateOnPreview()) {
            return $this->accessDenied();
        }

        if (! $request instanceof Request) {
            return $this->error('MoonShine request is not bound to the Symfony request.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // fields read their value by name, the table sends it as "value"
        $symfonyRequest = $request->getSymfonyRequest();
        $symfonyRequest->attributes->set(Request::INPUT_ATTRIBUTE, [
            $field->getColumn() => $request->get('value'),
        ]);

        $resource->setActivePage($resource->getIndexPage());

        $item = $resource->getItemOrFail();

        if ($denied = $this->denyUnlessAllowed($resource->setItem($item), Action::UPDATE, Ability::UPDATE)) {
            return $denied;
        }

        $result = $this->formProcessor->process(
            $resource,
            $item,
            $symfonyRequest,
            onlyField: $field->getColumn(),
        );

        if (! $result->isValid()) {
            return new JsonResponse([
                'message' => $result->getFirstError(),
                'errors' => $result->errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $resource->save($resource->getCaster()->cast($result->item), $result->fields);
        } catch (Throwable $e) {
            return $this->error($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/update-field/relation/{resourceUri}/{pageUri}/{resourceItem}', name: 'moonshine.update-field.through-relation', methods: ['PUT', 'POST'])]
    public function throughRelation(): Response
    {
        return $this->error('Updating relation fields from the table is not supported', Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Like MoonShine for Laravel: only index fields marked with updateOnPreview() can be saved from the table
     */
    private function findField(CrudResource $resource, string $column): ?FieldContract
    {
        return $resource->getIndexFields()->withoutWrappers()->findByColumn($column);
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }
}
