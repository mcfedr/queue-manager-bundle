<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\DependencyInjection;

use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class QueueManagerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $workers = $container->findTaggedServiceIds('mcfedr_queue_manager.worker');

        $workersMap = [];
        foreach ($workers as $id => $tags) {
            foreach ($tags as $attributes) {
                if (isset($attributes['id'])) {
                    $workersMap[$attributes['id']] = new Reference($id);
                } else {
                    $workersMap[$id] = new Reference($id);
                }
            }
        }

        foreach ($container->getAliases() as $alias => $id) {
            if (isset($workers[(string) $id])) {
                $workersMap[$alias] = new Reference((string) $id);
            }
        }

        $container
            ->getDefinition(JobExecutor::class)
            ->addTag('container.service_subscriber.locator', ['id' => (string) ServiceLocatorTagPass::register($container, $workersMap, JobExecutor::class)]);
    }
}
