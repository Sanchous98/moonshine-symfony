<?php

declare(strict_types=1);

namespace MoonShine\Symfony\Optimizer;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Stores the discovered MoonShine classes, so production requests do not scan the class map
 */
final readonly class OptimizerCacheWarmer implements CacheWarmerInterface
{
    public function __construct(private OptimizerCollection $optimizer) {}

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $path = $this->optimizer->getCachePath();

        (new Filesystem())->dumpFile(
            $path,
            '<?php return ' . var_export($this->optimizer->detect(), true) . ';' . \PHP_EOL,
        );

        return [];
    }
}
