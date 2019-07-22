<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\DependencyInjection;

use Aws\Sqs\SqsClient;
use Doctrine\Common\Persistence\ManagerRegistry;
use Mcfedr\QueueManagerBundle\Command\BeanstalkCommand;
use Mcfedr\QueueManagerBundle\Command\DoctrineDelayRunnerCommand;
use Mcfedr\QueueManagerBundle\Command\SqsRunnerCommand;
use Mcfedr\QueueManagerBundle\Manager\BeanstalkQueueManager;
use Mcfedr\QueueManagerBundle\Manager\DoctrineDelayQueueManager;
use Mcfedr\QueueManagerBundle\Manager\PeriodicQueueManager;
use Mcfedr\QueueManagerBundle\Manager\QueueManager;
use Mcfedr\QueueManagerBundle\Manager\SqsQueueManager;
use Mcfedr\QueueManagerBundle\Queue\Worker;
use Mcfedr\QueueManagerBundle\Subscriber\MemoryReportSubscriber;
use Pheanstalk\Pheanstalk;
use Pheanstalk\PheanstalkInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class McfedrQueueManagerExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container
            ->registerForAutoconfiguration(Worker::class)
            ->addTag('mcfedr_queue_manager.worker')
        ;

        $container
            ->registerForAutoconfiguration(QueueManager::class)
            ->addTag('mcfedr_queue_manager.manager')
        ;

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $queueManagers = [];

        foreach ($config['managers'] as $name => $manager) {
            if (!isset($config['drivers'][$manager['driver']])) {
                throw new InvalidArgumentException("Manager '{$name}' uses unknown driver '{$manager['driver']}'");
            }

            $managerClass = $config['drivers'][$manager['driver']]['class'];
            $defaultOptions = $config['drivers'][$manager['driver']]['options'] ?? [];
            $options = $manager['options'] ?? [];
            $mergedOptions = array_merge([
                'retry_limit' => $config['retry_limit'],
                'sleep_seconds' => $config['sleep_seconds'],
            ], $defaultOptions, $options);
            $bindings = [
                '$options' => $mergedOptions,
            ];
            $managerServiceName = "mcfedr_queue_manager.{$name}";

            switch ($manager['driver']) {
                case 'beanstalkd':
                    if (!interface_exists(PheanstalkInterface::class)) {
                        throw new \LogicException('"pheanstalk" requires pda/pheanstalk to be installed.');
                    }
                    if (isset($mergedOptions['pheanstalk'])) {
                        $bindings[PheanstalkInterface::class] = new Reference($mergedOptions['pheanstalk']);
                        unset($mergedOptions['pheanstalk']);
                    } else {
                        $pheanstalk = new Definition(Pheanstalk::class, [
                            $mergedOptions['host'],
                            $mergedOptions['port'],
                            $mergedOptions['connection']['timeout'],
                            $mergedOptions['connection']['persistent'],
                        ]);
                        unset($mergedOptions['host'], $mergedOptions['port'], $mergedOptions['connection']);

                        $pheanstalkName = "{$managerServiceName}.pheanstalk";
                        $container->setDefinition($pheanstalkName, $pheanstalk);
                        $bindings[PheanstalkInterface::class] = new Reference($pheanstalkName);
                    }

                    break;
                case 'sqs':
                    if (!class_exists(SqsClient::class)) {
                        throw new \LogicException('"sqs" requires aws/aws-sdk-php to be installed.');
                    }
                    if (isset($mergedOptions['sqs_client'])) {
                        $bindings[SqsClient::class] = new Reference($mergedOptions['sqs_client']);
                        unset($mergedOptions['sqs_client']);
                    } else {
                        $sqsOptions = [
                            'region' => $mergedOptions['region'],
                            'version' => '2012-11-05',
                        ];
                        unset($mergedOptions['region']);
                        if (\array_key_exists('credentials', $mergedOptions)) {
                            $sqsOptions['credentials'] = $mergedOptions['credentials'];
                            unset($mergedOptions['credentials']);
                        }
                        $sqsClient = new Definition(SqsClient::class, [$sqsOptions]);
                        $sqsClientName = "{$managerServiceName}.sqs_client";
                        $container->setDefinition($sqsClientName, $sqsClient);
                        $bindings[SqsClient::class] = new Reference($sqsClientName);
                    }

                    break;
                case 'doctrine_delay':
                    if (!interface_exists(ManagerRegistry::class)) {
                        throw new \LogicException('"doctrine_delay" requires doctrine/doctrine-bundle to be installed.');
                    }

                    break;
            }

            $container->setParameter("mcfedr_queue_manager.{$name}.options", $mergedOptions);
            $managerDefinition = new Definition($managerClass);
            $managerDefinition->setBindings($bindings);
            $managerDefinition->setAutoconfigured(true);
            $managerDefinition->setAutowired(true);
            $managerDefinition->setPublic(true);
            $managerDefinition->addTag('mcfedr_queue_manager.manager', ['id' => $name]);

            $container->setDefinition($managerServiceName, $managerDefinition);
            if (!$container->has($managerClass)) {
                $managerServiceAlias = $container->setAlias($managerClass, $managerServiceName);
                $managerServiceAlias->setPublic(true);
            }

            $queueManagers[$name] = true;

            if (isset($config['drivers'][$manager['driver']]['command_class'])) {
                $commandClass = $config['drivers'][$manager['driver']]['command_class'];
                $commandDefinition = new Definition($commandClass);
                $commandBindings = array_merge([
                    '$name' => "mcfedr:queue:{$name}-runner",
                ], $bindings);
                $commandDefinition->setBindings($commandBindings);
                $commandDefinition->setAutoconfigured(true);
                $commandDefinition->setAutowired(true);
                $commandDefinition->setPublic(true);
                $commandDefinition->setTags(
                    ['console.command' => []]
                );
                $commandServiceName = "mcfedr_queue_manager.runner.{$name}";
                $container->setDefinition($commandServiceName, $commandDefinition);
                if (!$container->has($commandClass)) {
                    $commandServiceAlias = $container->setAlias($commandClass, $commandServiceName);
                    $commandServiceAlias->setPublic(true);
                }
            }
        }

        if (\array_key_exists('default', $queueManagers)) {
            $defaultManager = 'default';
        } else {
            reset($queueManagers);
            $defaultManager = key($queueManagers);
        }
        $container->setParameter('mcfedr_queue_manager.default_manager', $defaultManager);

        if ($config['report_memory']) {
            $memoryListener = new Definition(MemoryReportSubscriber::class, [new Reference('logger')]);
            $memoryListener->setTags([
                'kernel.event_subscriber' => [],
            ]);
            $container->setDefinition(MemoryReportSubscriber::class, $memoryListener);
        }

        // Only referring to the classes as strings so as not to load them when not needed
        // This means there is no hard dependancy on Doctrine or SwiftMailer
        if ($config['doctrine_reset'] && class_exists('Doctrine\Bundle\DoctrineBundle\Registry')) {
            $doctrineListener = new Definition('Mcfedr\QueueManagerBundle\Subscriber\DoctrineResetSubscriber', [
                new Reference('doctrine', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]);
            $doctrineListener->setPublic(true);
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
            $swiftListener->setPublic(true);
            $swiftListener->setTags([
                'kernel.event_subscriber' => [],
            ]);
            $container->setDefinition('Mcfedr\QueueManagerBundle\Subscriber\SwiftMailerSubscriber', $swiftListener);
        }
    }

    /**
     * Allow an extension to prepend the extension configurations.
     */
    public function prepend(ContainerBuilder $container): void
    {
        // get all Bundles
        $bundles = $container->getParameter('kernel.bundles');
        // determine if McfedrQueueManagerBundle is registered
        if (isset($bundles['McfedrQueueManagerBundle'])) {
            $container->prependExtensionConfig('mcfedr_queue_manager', [
                'drivers' => [
                    'periodic' => [
                        'class' => PeriodicQueueManager::class,
                        'options' => [
                            'delay_manager' => null,
                            'delay_manager_options' => [],
                        ],
                    ],
                    'beanstalkd' => [
                        'class' => BeanstalkQueueManager::class,
                        'options' => [
                            'host' => '127.0.0.1',
                            'port' => 11300,
                            'default_queue' => 'default',
                            'connection' => [
                                'timeout' => 2,
                                'persistent' => false,
                            ],
                        ],
                        'command_class' => BeanstalkCommand::class,
                    ],
                    'doctrine_delay' => [
                        'class' => DoctrineDelayQueueManager::class,
                        'options' => [
                            'entity_manager' => null,
                            'default_manager' => null,
                            'default_manager_options' => [],
                        ],
                        'command_class' => DoctrineDelayRunnerCommand::class,
                    ],
                    'sqs' => [
                        'class' => SqsQueueManager::class,
                        'options' => [
                            'queues' => [],
                        ],
                        'command_class' => SqsRunnerCommand::class,
                    ],
                ],
            ]);
        }
    }
}
