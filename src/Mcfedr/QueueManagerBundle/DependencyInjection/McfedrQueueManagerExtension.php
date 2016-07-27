<?php

namespace Mcfedr\QueueManagerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class McfedrQueueManagerExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        foreach ($config['managers'] as $name => $manager) {
            if (!isset($config['drivers'][$manager['driver']])) {
                throw new InvalidArgumentException("Manager '$name' uses unknown driver '{$manager['driver']}'");
            }

            $managerClass = $config['drivers'][$manager['driver']]['class'];
            $defaultOptions = isset($config['drivers'][$manager['driver']]['options']) ? $config['drivers'][$manager['driver']]['options'] : [];
            $options = isset($manager['options']) ? $manager['options'] : [];

            $merged = array_merge([
                'debug' => $config['debug'],
                'retry_limit' => $config['retry_limit'],
                'sleep_seconds' => $config['sleep_seconds']
            ], $defaultOptions, $options);

            $container->setParameter("mcfedr_queue_manager.$name.options", $merged);

            $managerServiceName = "mcfedr_queue_manager.$name";
            $managerDefinition = new Definition($managerClass, [
                $merged
            ]);

            if ((new \ReflectionClass($managerClass))->implementsInterface(ContainerAwareInterface::class)) {
                $managerDefinition->addMethodCall('setContainer', [new Reference('service_container')]);
            }

            $container->setDefinition($managerServiceName, $managerDefinition);

            if (isset($config['drivers'][$manager['driver']]['command_class'])) {
                $commandClass = $config['drivers'][$manager['driver']]['command_class'];
                $commandDefinition = new Definition($commandClass, [
                    "mcfedr:queue:$name-runner",
                    $merged,
                    new Reference($managerServiceName)
                ]);
                $commandDefinition->setTags(
                    ['console.command' => []]
                );
                $container->setDefinition("mcfedr_queue_manager.runner.$name", $commandDefinition);
            }
        }
    }
}
