<?php

declare(strict_types=1);

namespace MoonShine\Symfony\DependencyInjection;

use Composer\InstalledVersions;
use MoonShine\Contracts\AssetManager\AssetResolverContract;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class AssetResolver implements AssetResolverContract
{
    public function __construct(private ?RequestStack $requestStack = null) {}

    public function get(string $path): string
    {
        if (preg_match('#^(https?:)?//#', $path) === 1) {
            return $path;
        }

        $basePath = $this->requestStack?->getCurrentRequest()?->getBasePath() ?? '';

        return $basePath . '/' . ltrim($path, '/');
    }

    public function getDev(string $path): string
    {
        return $path;
    }

    public function isDev(): bool
    {
        return false;
    }

    public function getVersion(): string
    {
        if (! InstalledVersions::isInstalled('moonshine/ui')) {
            return '1.0.0';
        }

        return substr((string) InstalledVersions::getReference('moonshine/ui'), 0, 8) ?: '1.0.0';
    }

    public function getHotFile(): string
    {
        return '';
    }
}
