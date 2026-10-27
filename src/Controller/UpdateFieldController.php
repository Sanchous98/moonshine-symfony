<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Controller;

use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\DependencyInjection\RequestContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Contracts\Page\FormPageContract;
use MoonShine\Crud\Resources\CrudResource;
use MoonShine\Symfony\DependencyInjection\Request;
use MoonShine\Symfony\Validation\Validator;
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
        private readonly Validator $validator,
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

        $value = $request->get('value');

        $page = $resource->getFormPage();
        $rules = $page instanceof FormPageContract ? $page->getRules() : [];

        if (isset($rules[$field->getColumn()])) {
            $errors = $this->validator->validate(
                [$field->getColumn() => $value],
                [$field->getColumn() => $rules[$field->getColumn()]],
                $page instanceof FormPageContract ? $page->validationMessages() : [],
                [$field->getColumn() => $field->getLabel()],
            );

            if ($errors !== []) {
                return new JsonResponse([
                    'message' => (string) (array_values($errors)[0][0] ?? 'The given data was invalid.'),
                    'errors' => $errors,
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // fields read their value by name, the table sends it as "value"
        if ($request instanceof Request) {
            $request->getSymfonyRequest()->attributes->set(Request::INPUT_ATTRIBUTE, [
                $field->getColumn() => $value,
            ]);
        }

        try {
            $resource->setActivePage($resource->getIndexPage());
            $resource->save(
                $resource->getCaster()->cast($resource->getItemOrFail()),
                $this->core->getFieldsCollection([$field]),
            );
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

    private function findField(CrudResource $resource, string $column): ?FieldContract
    {
        foreach ([$resource->getIndexFields(), $resource->getFormFields()] as $fields) {
            $field = $fields->onlyFields()->findByColumn($column);

            if ($field !== null) {
                return $field;
            }
        }

        return null;
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }
}
