<?php

declare(strict_types=1);

namespace MoonShine\Symfony;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // MoonShine core resolves pages and resources by class name through the container
        foreach (['moonshine.page', 'moonshine.resource'] as $tag) {
            foreach (array_keys($container->findTaggedServiceIds($tag)) as $id) {
                $container->getDefinition($id)->setPublic(true);
            }
        }
    }
}
