# MoonShine for Symfony

Integration of the [MoonShine](https://github.com/moonshine-software/moonshine) admin panel with the **Symfony framework**.

## Requirements

- PHP **8.2+**
- Symfony **7.1+** or **8.0+**

## Installation

Until the package is published on Packagist, install it from a `vcs` (or `path`) repository:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/moonshine-software/symfony"
    }
]
```

```shell
composer require moonshine/symfony:4.x-dev
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    MoonShine\Symfony\MoonShineBundle::class => ['all' => true],
];
```

Run the installer:

```shell
php bin/console moonshine:install
```

It creates:

- `config/routes/moonshine.yaml` - admin routes
- `config/packages/moonshine.yaml` - bundle configuration
- `src/MoonShine/Pages/Dashboard.php` and `src/MoonShine/Pages/LoginPage.php`
- `public/vendor/moonshine` - a symlink to the MoonShine UI assets (use `--copy` to copy them instead)

Existing files are kept, pass `--force` to overwrite them. The admin panel is available at `/admin`.

## Configuration

```yaml
# config/packages/moonshine.yaml
moonshine:
    namespace: 'App\MoonShine'
    prefix: '/admin'
    title: 'MoonShine'
    logo: null
    logo_small: null
    layout: 'MoonShine\Symfony\Layout\AppLayout'
    palette: 'MoonShine\ColorManager\Palettes\NeutralPalette'
    locale: 'en'
    # locale switcher: a list of codes or code => label
    locales: { en: 'English', de: 'Deutsch' }
    pages:
        dashboard: 'App\MoonShine\Pages\Dashboard'
        login: 'App\MoonShine\Pages\LoginPage'
    forms: []
    auth:
        enabled: false
        # role required to open the panel, null allows any authenticated user
        role: 'ROLE_ADMIN'
    # CSRF protection of admin requests
    csrf: true
    storage:
        default: public
        disks:
            public:
                root: '%kernel.project_dir%/public/storage'
                url: '/storage'
```

## Authentication

Authentication uses Symfony Security (`composer require symfony/security-bundle`).

1. Enable it in `config/packages/moonshine.yaml`:

    ```yaml
    moonshine:
        auth:
            enabled: true
            role: 'ROLE_ADMIN'
    ```

2. Add the `moonshine` firewall to `config/packages/security.yaml` **before** the `main` firewall
   (the installer prints the same snippet, it is also available in `recipe/security.yaml`):

    ```yaml
    security:
        firewalls:
            moonshine:
                pattern: ^/admin
                provider: app_user_provider
                form_login:
                    login_path: moonshine.login
                    check_path: moonshine.authenticate
                    default_target_path: moonshine.index
                    username_parameter: username
                    password_parameter: password
                    enable_csrf: true
                    csrf_parameter: _token
                    csrf_token_id: moonshine
                logout:
                    path: moonshine.logout
                    target: moonshine.login
                remember_me:
                    secret: '%kernel.secret%'
                    remember_me_parameter: remember
    ```

Every admin route except login and logout requires the configured role, independently of `access_control`.
The profile block shows `getName()`, `getFullName()` or `getDisplayName()` of the user when available,
and `getAvatar()` for the picture.

## Pages and resources

Any service implementing `MoonShine\Contracts\Core\PageContract` or `MoonShine\Contracts\Core\ResourceContract` is registered in the admin panel,
as long as it is autoconfigured (with the default `config/services.yaml` everything under `src/` is).
Index, form and detail pages of a resource are not registered as standalone pages.

### Doctrine resources

`MoonShine\Symfony\Resources\DoctrineResource` provides CRUD for a Doctrine ORM entity
(`composer require symfony/orm-pack`): pagination, sorting, search, filters, query tags, ManyToOne/ManyToMany associations through select fields.
Without a form type, values are written with the PropertyAccess component (setters, adders/removers), see [Forms and validation](#forms-and-validation).

```php
namespace App\MoonShine\Resources;

use App\Entity\Book;
use MoonShine\Symfony\Resources\DoctrineResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Image;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;

/** @extends DoctrineResource<Book> */
final class BookResource extends DoctrineResource
{
    protected string $entity = Book::class;

    protected string $title = 'Books';

    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make('Title', 'title')->sortable()->updateOnPreview(),
            Image::make('Cover', 'cover'),
        ];
    }

    protected function formFields(): iterable
    {
        return [
            ID::make(),
            Text::make('Title', 'title'),
            Select::make('Author', 'author')->options([1 => 'Frank Herbert'])->nullable(),
            Image::make('Cover', 'cover')->dir('books'),
        ];
    }

    protected function detailFields(): iterable
    {
        return $this->indexFields();
    }

    protected function filters(): iterable
    {
        return [Text::make('Title', 'title')];
    }

    protected function search(): array
    {
        return ['title'];
    }
}
```

Fields write values through setters (`setTitle()`) or directly into mapped properties.
Override `query()` to add joins or conditions, `fieldApply()` / `filterApply()` to change how values are written and filtered.

For other data sources extend `MoonShine\Crud\Resources\CrudResource` and implement
`findItem`, `getItems`, `save`, `delete` and `massDelete`, see `tests/Fixtures/Resources/PostResource.php`.

## Forms and validation

### Symfony form types

A form page can delegate mapping and validation to a Symfony form type (`composer require symfony/form`).
The submitted data is handled by the form (data transformers, `EntityType`, adders/removers, constraints of the form and of the entity, `UniqueEntity`),
MoonShine fields are used for rendering. Fields that are not part of the form type (for example `File`/`Image`) are still applied by MoonShine.

```php
use MoonShine\Symfony\Pages\SymfonyFormPage;

final class BookFormPage extends SymfonyFormPage
{
    protected function formType(): string
    {
        return BookType::class;
    }

    protected function formOptions(): array
    {
        return ['validation_groups' => ['Default', 'admin']];
    }
}
```

```php
final class BookType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // non nullable setters need an empty_data
            ->add('title', TextType::class, ['empty_data' => ''])
            ->add('published', CheckboxType::class, ['required' => false])
            ->add('author', EntityType::class, ['class' => Author::class, 'required' => false])
            ->add('tags', EntityType::class, ['class' => Tag::class, 'multiple' => true, 'by_reference' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Book::class]);
    }
}
```

```php
// BookResource
protected function pages(): array
{
    return [IndexPage::class, BookFormPage::class, DetailPage::class];
}
```

Form children are matched with MoonShine fields by name (`title` field <-> `title` child).
The form is submitted without a name, CSRF is verified by the bundle for every admin request.

### Constraints without a form type

`FormPage::rules()` may return Symfony constraints, they are checked with the Symfony validator:

```php
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Crud\Pages\FormPage;
use Symfony\Component\Validator\Constraints as Assert;

final class BookFormPage extends FormPage
{
    protected function rules(DataWrapperContract $item): array
    {
        return ['title' => [new Assert\NotBlank(), new Assert\Length(max: 255)]];
    }
}
```

Laravel style string rules (`'required|max:255'`) are supported as a fallback when `illuminate/validation` is installed.

Async forms receive a `422` response with the errors, regular forms are redirected back with the errors and the submitted values.
Inline edits from the table are validated the same way.

## Files

`File` and `Image` fields store uploads on the configured disks, `dir()` and `disk()` select the location.
Replaced and removed files are deleted from the disk.

## Translations

Messages live in the `moonshine` translation domain, English is bundled.
Add other languages to `translations/moonshine.<locale>.php` (or yaml/xlf) of your application.

## Limitations

- Inline editing of relation fields from the table (`update-field.through-relation`) is not supported.
- Notifications are kept in memory only.
- The package has been tested with Symfony 7.4 on PHP 8.3, Symfony 8 requires PHP 8.4 and has not been tested yet.

## Development

```shell
composer install
composer test
composer analyse
```

## Contributing

We welcome contributions from the community! If you're interested in improving this integration, feel free to open issues, submit PRs, or suggest improvements.
