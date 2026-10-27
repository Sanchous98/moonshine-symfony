<?php

declare(strict_types=1);

namespace MoonShine\Symfony;

use MoonShine\Contracts\Core\CrudPageContract;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // MoonShine core resolves pages and resources by class name through the container
        foreach (['moonshine.page', 'moonshine.resource'] as $tag) {
            foreach (array_keys($container->findTaggedServiceIds($tag)) as $id) {
                $definition = $container->getDefinition($id)->setPublic(true);
                $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $id);

                // index/form/detail pages belong to their resource, not to the global page list
                if ($tag === 'moonshine.page' && \is_string($class) && is_subclass_of($class, CrudPageContract::class)) {
                    $definition->clearTag($tag)->setShared(false);
                }
            }
        }
    }
}
