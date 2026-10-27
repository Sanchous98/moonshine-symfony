<?php

declare(strict_types=1);

namespace MoonShine\Symfony;

use MoonShine\Contracts\Core\CrudPageContract;
use MoonShine\Contracts\UI\LayoutContract;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // MoonShine core resolves pages and resources by class name through the container
        // layouts fill the menu and the assets in their constructor, those are reset after every request:
        // a new layout instance is built for every page
        foreach ($container->getDefinitions() as $definition) {
            $class = $definition->getClass();

            if ($definition->isAbstract() || ! \is_string($class)) {
                continue;
            }

            // safe for services whose class cannot be loaded
            $reflection = $container->getReflectionClass($container->getParameterBag()->resolveValue($class), false);

            // resolved by class name through the container, like pages and resources
            if ($reflection?->implementsInterface(LayoutContract::class)) {
                $definition->setShared(false)->setPublic(true);
            }
        }

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
