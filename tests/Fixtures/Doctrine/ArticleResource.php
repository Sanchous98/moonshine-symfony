<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Tests\Fixtures\Doctrine;

use MoonShine\Crud\QueryTags\QueryTag;
use MoonShine\Symfony\Resources\DoctrineResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends DoctrineResource<Article>
 */
final class ArticleResource extends DoctrineResource
{
    protected string $entity = Article::class;

    protected string $title = 'Articles';

    protected int $itemsPerPage = 2;

    protected string $sortColumn = 'id';

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Title', 'title')->sortable()->updateOnPreview(),
            Switcher::make('Published', 'published'),
            Number::make('Views', 'views'),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Title', 'title'),
            Switcher::make('Published', 'published'),
            Number::make('Views', 'views')->nullable(),
            Select::make('Category', 'category')->options($this->options(Category::class))->nullable(),
            Select::make('Tags', 'tags')->options($this->options(Tag::class))->multiple(),
        ];
    }

    protected function detailFields(): iterable
    {
        return $this->indexFields();
    }

    protected function filters(): iterable
    {
        return [
            Text::make('Title', 'title'),
            Switcher::make('Published', 'published'),
            Select::make('Category', 'category')->options($this->options(Category::class))->nullable(),
        ];
    }

    protected function search(): array
    {
        return ['id', 'title'];
    }

    /**
     * @param class-string<Category|Tag> $class
     *
     * @return array<int, string>
     */
    private function options(string $class): array
    {
        $options = [];

        foreach ($this->getEntityManager()->getRepository($class)->findAll() as $entity) {
            $options[(int) $entity->getId()] = $entity->getName();
        }

        return $options;
    }

    protected function queryTags(): array
    {
        return [
            QueryTag::make('Published', static fn (QueryBuilder $builder): QueryBuilder => $builder->andWhere('e.published = true')),
        ];
    }
}
