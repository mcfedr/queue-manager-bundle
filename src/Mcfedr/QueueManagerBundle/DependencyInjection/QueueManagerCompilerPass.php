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
        $this->createMap($container, JobExecutor::class, 'mcfedr_queue_manager.worker');
        $this->createMap($container, QueueManagerRegistry::class, 'mcfedr_queue_manager.manager', 'mcfedr_queue_manager.manager_ids');
    }

    private function createMap(ContainerBuilder $container, string $callerId, string $tag, ?string $parameter = null): void
    {
        $serviceIds = $container->findTaggedServiceIds($tag);

        $serviceMap = [];
        foreach ($serviceIds as $id => $tags) {
            foreach ($tags as $attributes) {
                if (isset($attributes['id'])) {
                    $serviceMap[$attributes['id']] = new Reference($id);
                } else {
                    $serviceMap[$id] = new Reference($id);
                }
            }
        }

        if ($parameter) {
            $container->setParameter($parameter, array_keys($serviceMap));
        }

        foreach ($container->getAliases() as $alias => $id) {
            if (isset($serviceIds[(string) $id])) {
                $serviceMap[$alias] = new Reference((string) $id);
            }
        }

        $container
            ->getDefinition($callerId)
            ->addTag('container.service_subscriber.locator', ['id' => (string) ServiceLocatorTagPass::register($container, $serviceMap, $callerId)])
        ;
    }
}
