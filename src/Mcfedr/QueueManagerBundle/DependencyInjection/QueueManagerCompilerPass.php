<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\DependencyInjection;

use Mcfedr\QueueManagerBundle\Manager\QueueManagerRegistry;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class QueueManagerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->createMap($container, JobExecutor::class, 0, 'mcfedr_queue_manager.worker', 'getName');
        $this->createMap($container, QueueManagerRegistry::class, 0, 'mcfedr_queue_manager.manager', null, 1);
    }

    private function createMap(ContainerBuilder $container, string $callerId, int $argument, string $tag, ?string $method = null, ?int $idsArgument = null): void
    {
        $serviceIds = $container->findTaggedServiceIds($tag);

        $serviceMap = [];
        foreach ($serviceIds as $id => $tags) {
            foreach ($tags as $attributes) {
                if (isset($attributes['id'])) {
                    $serviceMap[$attributes['id']] = new Reference($id);
                } elseif ($method && method_exists(($class = $container->getDefinition($id)->getClass()), $method)) {
                    $serviceMap[$class::$method()] = new Reference($id);
                } else {
                    $serviceMap[$id] = new Reference($id);
                }
            }
        }

        foreach ($container->getAliases() as $alias => $id) {
            if (isset($serviceIds[(string) $id])) {
                $serviceMap[$alias] = new Reference((string) $id);
            }
        }

        $definition = $container->getDefinition($callerId);
        $definition->replaceArgument($argument, ServiceLocatorTagPass::register($container, $serviceMap, $callerId));

        if ($idsArgument !== null) {
            $definition->replaceArgument($idsArgument, array_keys($serviceMap));
        }
    }
}
