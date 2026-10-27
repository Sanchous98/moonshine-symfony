<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Controller;

use Illuminate\Support\Collection;
use MoonShine\Contracts\Core\DependencyInjection\CrudRequestContract;
use MoonShine\Contracts\Core\DependencyInjection\RequestContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Contracts\UI\HasReactivityContract;
use MoonShine\Crud\JsonResponse;
use MoonShine\UI\Components\FieldsGroup;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[Route('/reactive/{pageUri}/{resourceUri?}/{resourceItem?}', name: 'moonshine.reactive', methods: ['POST'])]
final class ReactiveController extends MoonShineController
{
    public function __invoke(CrudRequestContract $crudRequest, RequestContract $request): Response
    {
        $form = $crudRequest->getPage()->getComponents()->findForm(
            $crudRequest->getComponentName(),
        );

        if (! $form instanceof FormBuilderContract) {
            return new JsonResponse();
        }

        $fields = $form
            ->getPreparedFields()
            ->onlyFields()
            ->reactiveFields();

        $casted = null;
        $except = [];
        $rawValues = $request->get('values', []);

        $values = (new Collection(\is_array($rawValues) ? $rawValues : []))
            ->map(static function (mixed $value, string $column) use ($fields, &$casted, &$except): mixed {
                $field = $fields->findByColumn($column);

                if (! $field instanceof HasReactivityContract) {
                    return $value;
                }

                return $field->prepareReactivityValue($value, $casted, $except);
            });

        $valuesArray = $values->toArray();

        $fields->fill($valuesArray, $casted instanceof DataWrapperContract ? $casted : null);

        foreach ($fields as $field) {
            $fields = $field->formName($form->getName())->getReactiveCallback(
                $fields,
                data_get($values, $field->getColumn()),
                $valuesArray,
                [],
            );
        }

        $values = $fields->mapWithKeys(
            static fn (FieldContract $field): array => [$field->getColumn() => $field->getReactiveValue()],
        );

        $fields = $fields->mapWithKeys(
            fn (FieldContract $field): array => [
                $field->getColumn() => $this->renderToString(FieldsGroup::make([$field])->render()),
            ],
        );

        return JsonResponse::make([
            'form' => $form,
            'fields' => $fields,
            'values' => $values,
        ]);
    }
}
