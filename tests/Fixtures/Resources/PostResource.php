<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Fixtures\Resources;

use Illuminate\Support\Collection;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Pages\DetailPage;
use MoonShine\Crud\Pages\IndexPage;
use MoonShine\Crud\Resources\CrudResource;
use MoonShine\Symfony\Tests\Fixtures\Pages\PostFormPage;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * In-memory resource used to exercise the CRUD endpoints
 *
 * @extends CrudResource<array{id: int, title: string}>
 */
final class PostResource extends CrudResource
{
    /** @var array<int, array{id: int, title: string}> */
    public static array $items = [];

    protected string $title = 'Posts';

    protected ?string $casterKeyName = 'id';

    protected function pages(): array
    {
        return [
            IndexPage::class,
            PostFormPage::class,
            DetailPage::class,
        ];
    }

    public static function seed(): void
    {
        self::$items = [
            1 => ['id' => 1, 'title' => 'First post'],
            2 => ['id' => 2, 'title' => 'Second post'],
        ];
    }

    protected function indexFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Title', 'title'),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Title', 'title')->reactive(
                static function (FieldsContract $fields, mixed $value): FieldsContract {
                    $fields->findByColumn('slug')?->setValue(strtolower(str_replace(' ', '-', (string) $value)));

                    return $fields;
                },
            ),
            Text::make('Slug', 'slug')->reactive(),
        ];
    }

    protected function detailFields(): iterable
    {
        return $this->indexFields();
    }

    public function findItem(bool $orFail = false): ?DataWrapperContract
    {
        $item = self::$items[(int) $this->getItemID()] ?? null;

        if ($item === null) {
            return $orFail ? throw new NotFoundHttpException() : null;
        }

        return $this->getCaster()->cast($item);
    }

    public function getItems(): Collection
    {
        return new Collection(array_values(self::$items));
    }

    public function massDelete(array $ids): void
    {
        foreach ($ids as $id) {
            unset(self::$items[(int) $id]);
        }
    }

    public function delete(DataWrapperContract $item, ?FieldsContract $fields = null): bool
    {
        unset(self::$items[(int) $item->getKey()]);

        return true;
    }

    public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract
    {
        $fields ??= $this->getFormFields()->onlyFields();

        $data = $item->getOriginal();

        $fields->each(function (FieldContract $field) use (&$data): void {
            $data = $field->apply(
                static function (mixed $data, mixed $value, FieldContract $field): mixed {
                    if ($value !== false) {
                        data_set($data, $field->getColumn(), $value);
                    }

                    return $data;
                },
                $data,
            );
        });

        $this->isRecentlyCreated = empty($data['id']);

        if ($this->isRecentlyCreated) {
            $data['id'] = self::$items === [] ? 1 : max(array_keys(self::$items)) + 1;
        }

        self::$items[(int) $data['id']] = $data;

        return $this->getCaster()->cast($data);
    }

    #[AsyncMethod]
    public function ping(): string
    {
        return 'pong';
    }
}
