<?php

declare(strict_types=1);

/*
 * (c) INSPIRED MINDS
 */

namespace InspiredMinds\ContaoSearchAndReplace\DependencyInjection\Compiler;

use InspiredMinds\ContaoSearchAndReplace\EventListener\GetMetaModelsEditUrlListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class MetaModelsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(GetMetaModelsEditUrlListener::class)) {
            return;
        }

        if (!$container->hasDefinition('metamodels.factory')) {
            $container->removeDefinition(GetMetaModelsEditUrlListener::class);
        } else {
            $container
                ->getDefinition(GetMetaModelsEditUrlListener::class)
                ->setArgument('$metaModelsFactory', new Reference('metamodels.factory'))
            ;
        }
    }
}
