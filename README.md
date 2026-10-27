# MoonShine for Symfony

Integration of the [MoonShine](https://github.com/moonshine-software/moonshine) admin panel with the **Symfony framework**.

> The package is still in development: authentication and a Doctrine based resource are not implemented yet.
> See [Limitations](#limitations).

## Requirements

- PHP **8.2+**
- Symfony **7.1+** or **8.0+**

## Installation

Until the package is published on Packagist, install it from a `path` or `vcs` repository:

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

Register the bundle (Flex does it automatically) in `config/bundles.php`:

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

Existing files are kept, pass `--force` to overwrite them.

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
    locales: []
    pages:
        dashboard: 'App\MoonShine\Pages\Dashboard'
        login: 'App\MoonShine\Pages\LoginPage'
    forms: []
```

## Pages and resources

Any service implementing `MoonShine\Contracts\Core\PageContract` or `MoonShine\Contracts\Core\ResourceContract` is registered in the admin panel automatically,
as long as it is autoconfigured. With the default `config/services.yaml` everything under `src/` already is.

Resources extend `MoonShine\Crud\Resources\CrudResource` and implement data access
(`findItem`, `getItems`, `save`, `delete`, `massDelete`). See `tests/Fixtures/Resources/PostResource.php` for a minimal example.

## Limitations

- No authentication: the login page is rendered, but credentials are not checked. Protect the prefix with Symfony Security in the meantime.
- No CSRF protection for the admin forms.
- Form validation rules (`rules()`) are not applied.
- `DoctrineCaster` only wraps entities, there is no ready-made Doctrine resource with queries, pagination and relations.
- File storage and "update on preview" endpoints are not implemented.

## Development

```shell
composer install
composer test
composer analyse
```

## Contributing

We welcome contributions from the community! If you're interested in improving this integration, feel free to open issues, submit PRs, or suggest improvements.
