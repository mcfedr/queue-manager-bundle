<?php

namespace Mcfedr\QueueManagerBundle\DependencyInjection;

use Mcfedr\QueueManagerBundle\Manager\QueueManagerRegistry;
use Mcfedr\QueueManagerBundle\Subscriber\MemoryReportSubscriber;
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
            if (!$container->has($managerClass)) {
                $container->addAliases([
                    $managerClass => $managerServiceName,
                ]);
            }

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
                $commandServiceName = "mcfedr_queue_manager.runner.$name";
                $container->setDefinition($commandServiceName, $commandDefinition);
                if (!$container->has($commandClass)) {
                    $container->addAliases([
                        $commandClass => $commandServiceName,
                    ]);
                }
            }
        }

        if (array_key_exists('default', $queueManagers)) {
            $defaultManager = 'default';
        } else {
            reset($queueManagers);
            $defaultManager = key($queueManagers);
        }

        $container->setDefinition(QueueManagerRegistry::class, new Definition(QueueManagerRegistry::class, [$queueManagers, $defaultManager]));
        $container->addAliases([
            'mcfedr_queue_manager.registry' => QueueManagerRegistry::class,
        ]);

        if ($config['report_memory']) {
            $memoryListener = new Definition(MemoryReportSubscriber::class, [new Reference('logger')]);
            $memoryListener->setTags([
                'kernel.event_subscriber' => [],
            ]);
            $container->setDefinition(MemoryReportSubscriber::class, $memoryListener);
        }

        if ($config['doctrine_reset'] && class_exists('Doctrine\Bundle\DoctrineBundle\Registry')) {
            $doctrineListener = new Definition('Mcfedr\QueueManagerBundle\Subscriber\DoctrineResetSubscriber', [
                new Reference('doctrine', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]);
            $doctrineListener->setTags([
                'kernel.event_subscriber' => [],
            ]);
            $container->setDefinition('Mcfedr\QueueManagerBundle\Subscriber\DoctrineResetSubscriber', $doctrineListener);
        }

        if ($config['swift_mailer_batch_size'] >= 0 && class_exists('Symfony\Bundle\SwiftmailerBundle\EventListener\EmailSenderListener')) {
            $swiftListener = new Definition('Mcfedr\QueueManagerBundle\Subscriber\SwiftMailerSubscriber', [
                $config['swift_mailer_batch_size'],
                new Reference('service_container'),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]);
            $swiftListener->setTags([
                'kernel.event_subscriber' => [],
            ]);
            $container->setDefinition('Mcfedr\QueueManagerBundle\Subscriber\SwiftMailerSubscriber', $swiftListener);
        }
    }
}
