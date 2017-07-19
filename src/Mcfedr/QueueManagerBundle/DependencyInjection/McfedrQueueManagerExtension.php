<?php

namespace Mcfedr\QueueManagerBundle\DependencyInjection;

use Mcfedr\QueueManagerBundle\Manager\QueueManagerRegistry;
use Mcfedr\QueueManagerBundle\Subscriber\DoctrineResetSubscriber;
use Mcfedr\QueueManagerBundle\Subscriber\MemoryReportSubscriber;
use Mcfedr\QueueManagerBundle\Subscriber\SwiftMailerSubscriber;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class McfedrQueueManagerExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $queueManagers = [];

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
                'sleep_seconds' => $config['sleep_seconds'],
            ], $defaultOptions, $options);

            $container->setParameter("mcfedr_queue_manager.$name.options", $merged);

            $managerServiceName = "mcfedr_queue_manager.$name";
            $managerDefinition = new Definition($managerClass, [
                $merged,
            ]);

            if ((new \ReflectionClass($managerClass))->implementsInterface(ContainerAwareInterface::class)) {
                $managerDefinition->addMethodCall('setContainer', [new Reference('service_container')]);
            }

            $container->setDefinition($managerServiceName, $managerDefinition);

            $queueManagers[$name] = new Reference($managerServiceName);

            if (isset($config['drivers'][$manager['driver']]['command_class'])) {
                $commandClass = $config['drivers'][$manager['driver']]['command_class'];
                $commandDefinition = new Definition($commandClass, [
                    "mcfedr:queue:$name-runner",
                    $merged,
                    new Reference($managerServiceName),
                ]);
                $commandDefinition->setTags(
                    ['console.command' => []]
                );
                $container->setDefinition("mcfedr_queue_manager.runner.$name", $commandDefinition);
            }
        }

        if (array_key_exists('default', $queueManagers)) {
            $defaultManager = 'default';
        } else {
            reset($queueManagers);
            $defaultManager = key($queueManagers);
        }

        $container->setDefinition('mcfedr_queue_manager.registry', new Definition(QueueManagerRegistry::class, [$queueManagers, $defaultManager]));

        if ($config['report_memory']) {
            $memoryListener = new Definition(MemoryReportSubscriber::class, [new Reference('logger')]);
            $memoryListener->setTags([
                'kernel.event_subscriber' => [],
            ]);
            $container->setDefinition('mcfedr_queue_manager.memory_report_subscriber', $memoryListener);
        }

        if ($config['doctrine_reset']) {
            $doctrineListener = new Definition(DoctrineResetSubscriber::class, [
                new Reference('doctrine', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]);
            $doctrineListener->setTags([
                'kernel.event_subscriber' => [],
            ]);
            $container->setDefinition('mcfedr_queue_manager.doctrine_reset_subscriber', $doctrineListener);
        }

        if ($config['swift_mailer_batch_size'] >= 0) {
            $swiftListener = new Definition(SwiftMailerSubscriber::class, [
                new Reference('service_container'),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['swift_mailer_batch_size'],
            ]);
            $swiftListener->setTags([
                'kernel.event_subscriber' => [],
            ]);
            $container->setDefinition('mcfedr_queue_manager.swift_mailer_subscriber', $swiftListener);
        }
    }
}
