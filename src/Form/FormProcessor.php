<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Form;

use LogicException;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Contracts\Page\FormPageContract;
use MoonShine\Crud\Resources\CrudResource;
use MoonShine\Symfony\Validation\LaravelValidator;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Maps and validates submitted data of a resource form page:
 * - a Symfony form type when the page implements SymfonyFormPageContract
 * - Symfony constraints returned by FormPage::rules()
 * - Laravel style string rules as a fallback (requires illuminate/validation)
 */
final readonly class FormProcessor
{
    public function __construct(
        private ValidatorInterface $validator,
        private ?FormFactoryInterface $formFactory = null,
        private ?LaravelValidator $laravelValidator = null,
    ) {}

    /**
     * @param string|null $onlyField submit a single field, keeping the others untouched
     */
    public function process(CrudResource $resource, mixed $item, Request $request, ?string $onlyField = null): FormResult
    {
        $page = $resource->getFormPage();
        $fields = $resource->getFormFields()->onlyFields();

        if ($onlyField !== null) {
            $fields = $fields->filter(static fn (FieldContract $field): bool => $field->getColumn() === $onlyField);
        }

        if ($page instanceof SymfonyFormPageContract) {
            return $this->submitForm($page, $item, $request, $fields, $onlyField);
        }

        if (! $page instanceof FormPageContract) {
            return new FormResult([], $item, $fields);
        }

        $page->prepareForValidation();

        $rules = $page->getRules();

        if ($onlyField !== null) {
            $rules = array_intersect_key($rules, [$onlyField => true]);
        }

        if ($rules === []) {
            return new FormResult([], $item, $fields);
        }

        $data = $this->getSubmittedData($request);

        $constraints = array_filter($rules, $this->isConstraintRule(...));
        $stringRules = array_diff_key($rules, $constraints);

        $errors = $constraints === [] ? [] : $this->violationsToErrors(
            $this->validator->validate($data, new Collection(
                fields: $constraints,
                allowExtraFields: true,
                allowMissingFields: true,
            )),
        );

        if ($stringRules !== []) {
            if ($this->laravelValidator === null) {
                throw new LogicException('String validation rules require "illuminate/validation", use Symfony constraints or install the package.');
            }

            $labels = [];

            foreach ($fields as $field) {
                $labels[$field->getNameDot()] = $field->getLabel();
            }

            $errors = array_merge_recursive($errors, $this->laravelValidator->validate(
                $data,
                $stringRules,
                $page->validationMessages(),
                $labels,
            ));
        }

        return new FormResult($errors, $item, $fields);
    }

    private function submitForm(
        SymfonyFormPageContract $page,
        mixed $item,
        Request $request,
        FieldsContract $fields,
        ?string $onlyField,
    ): FormResult {
        if ($this->formFactory === null) {
            throw new LogicException(\sprintf('Form page "%s" requires "symfony/form".', $page::class));
        }

        $builder = $this->formFactory->createNamedBuilder('', $page->getFormType(), $item, [
            ...$page->getFormOptions(),
            'method' => $request->getMethod(),
            // MoonShine forms carry service fields (_token, _component_name, hidden_*), CSRF is checked for every admin request
            'allow_extra_fields' => true,
            'csrf_protection' => false,
        ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, self::normalizeCheckboxes(...));

        $form = $builder->getForm();

        if ($onlyField !== null) {
            if (! $form->has($onlyField)) {
                // the field is not handled by the form type, MoonShine applies it
                return new FormResult([], $item, $fields);
            }

            $form->submit([$onlyField => $this->getSubmittedData($request)[$onlyField] ?? null], clearMissing: false);
        } elseif ($request->getContentTypeFormat() === 'json') {
            $form->submit([...$request->getPayload()->all(), ...$request->files->all()]);
        } else {
            $form->handleRequest($request);

            if (! $form->isSubmitted()) {
                $form->submit([]);
            }
        }

        $errors = [];

        foreach ($form->getErrors(deep: true) as $error) {
            if ($error instanceof FormError) {
                $errors[$this->errorPath($error->getOrigin() ?? $form)][] = $error->getMessage();
            }
        }

        // fields mapped by the form are not applied again by MoonShine
        $mapped = array_keys($form->all());

        return new FormResult(
            $errors,
            $form->getData(),
            $fields->filter(static fn (FieldContract $field): bool => ! \in_array($field->getColumn(), $mapped, true)),
        );
    }

    /**
     * MoonShine switchers submit "0" when unchecked, a CheckboxType treats any submitted value as checked
     */
    private static function normalizeCheckboxes(FormEvent $event): void
    {
        $data = $event->getData();

        if (! \is_array($data)) {
            return;
        }

        foreach ($event->getForm()->all() as $name => $child) {
            if (! \array_key_exists($name, $data)) {
                continue;
            }

            $type = $child->getConfig()->getType();

            while ($type !== null && ! $type->getInnerType() instanceof CheckboxType) {
                $type = $type->getParent();
            }

            if ($type !== null && \in_array($data[$name], ['0', '', 'false', false], true)) {
                unset($data[$name]);
            }
        }

        $event->setData($data);
    }

    /**
     * Dot path of a form element, the way MoonShine fields name their errors
     *
     * @param FormInterface<mixed> $form
     */
    private function errorPath(FormInterface $form): string
    {
        $names = [];

        while ($form->getParent() !== null) {
            $names[] = $form->getName();
            $form = $form->getParent();
        }

        return $names === [] ? '_form' : implode('.', array_reverse($names));
    }

    /**
     * @return array<string, list<string>>
     */
    private function violationsToErrors(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            // "[title][0]" -> "title.0"
            $path = trim(str_replace(['][', '[', ']'], ['.', '', ''], $violation->getPropertyPath()), '.');
            $errors[$path === '' ? '_form' : $path][] = (string) $violation->getMessage();
        }

        return $errors;
    }

    private function isConstraintRule(mixed $rule): bool
    {
        if ($rule instanceof Constraint) {
            return true;
        }

        if (! \is_array($rule) || $rule === []) {
            return false;
        }

        foreach ($rule as $item) {
            if (! $item instanceof Constraint) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function getSubmittedData(Request $request): array
    {
        $data = $request->getContentTypeFormat() === 'json'
            ? $request->getPayload()->all()
            : $request->request->all();

        $overrides = $request->attributes->get(\MoonShine\Symfony\DependencyInjection\Request::INPUT_ATTRIBUTE, []);

        return array_replace_recursive($data, $request->files->all(), \is_array($overrides) ? $overrides : []);
    }
}
