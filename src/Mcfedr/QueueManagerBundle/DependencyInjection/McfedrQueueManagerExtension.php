<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\DependencyInjection;

use Aws\Sqs\SqsClient;
use Google\Cloud\PubSub\PubSubClient;
use Mcfedr\QueueManagerBundle\Command\BeanstalkCommand;
use Mcfedr\QueueManagerBundle\Command\DoctrineDelayRunnerCommand;
use Mcfedr\QueueManagerBundle\Command\PubSubRunnerCommand;
use Mcfedr\QueueManagerBundle\Command\SqsRunnerCommand;
use Mcfedr\QueueManagerBundle\Manager\BeanstalkQueueManager;
use Mcfedr\QueueManagerBundle\Manager\DoctrineDelayQueueManager;
use Mcfedr\QueueManagerBundle\Manager\PeriodicQueueManager;
use Mcfedr\QueueManagerBundle\Manager\PubSubQueueManager;
use Mcfedr\QueueManagerBundle\Manager\QueueManager;
use Mcfedr\QueueManagerBundle\Manager\SqsQueueManager;
use Mcfedr\QueueManagerBundle\Queue\Worker;
use Mcfedr\QueueManagerBundle\Subscriber\DoctrineResetSubscriber;
use Mcfedr\QueueManagerBundle\Subscriber\MemoryReportSubscriber;
use Mcfedr\QueueManagerBundle\Subscriber\SwiftMailerSubscriber;
use Pheanstalk\Contract\PheanstalkInterface;
use Pheanstalk\Pheanstalk;
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
            $this->createManager($container, $config, $name, $manager);
            $queueManagers[$name] = true;
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

        if ($config['doctrine_reset'] && \array_key_exists('DoctrineBundle', $container->getParameter('kernel.bundles'))) {
            $doctrineListener = new Definition(DoctrineResetSubscriber::class, [
                new Reference('doctrine', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]);
            $doctrineListener->setPublic(true);
            $doctrineListener->setTags([
                'kernel.event_subscriber' => [],
            ]);
            $container->setDefinition(DoctrineResetSubscriber::class, $doctrineListener);
        }

        if ($config['swift_mailer_batch_size'] >= 0 && \array_key_exists('SwiftmailerBundle', $container->getParameter('kernel.bundles'))) {
            $swiftListener = new Definition(SwiftMailerSubscriber::class, [
                $config['swift_mailer_batch_size'],
                new Reference('service_container'),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]);
            $swiftListener->setPublic(true);
            $swiftListener->setTags([
                'kernel.event_subscriber' => [],
            ]);
            $container->setDefinition(SwiftMailerSubscriber::class, $swiftListener);
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
        if (\array_key_exists('McfedrQueueManagerBundle', $bundles)) {
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
                    'pub_sub' => [
                        'class' => PubSubQueueManager::class,
                        'options' => [
                            'pub_sub_queues' => [],
                        ],
                        'command_class' => PubSubRunnerCommand::class,
                    ],
                ],
            ]);

            if (\array_key_exists('DoctrineBundle', $container->getParameter('kernel.bundles'))) {
                $container->prependExtensionConfig('mcfedr_queue_manager', [
                    'managers' => [
                        'delay' => [
                            'driver' => 'doctrine_delay',
                        ],
                        'periodic' => [
                            'driver' => 'periodic',
                            'options' => [
                                'delay_manager' => 'delay',
                            ],
                        ],
                    ],
                ]);
            }
        }
    }

    private function createManager(ContainerBuilder $container, array $config, string $name, array $managerConfig): void
    {
        if (!isset($config['drivers'][$managerConfig['driver']])) {
            throw new InvalidArgumentException("Manager '{$name}' uses unknown driver '{$managerConfig['driver']}'");
        }

        $managerClass = $config['drivers'][$managerConfig['driver']]['class'];
        $defaultOptions = $config['drivers'][$managerConfig['driver']]['options'] ?? [];
        $options = $managerConfig['options'] ?? [];
        $mergedOptions = array_merge(
            [
                'retry_limit' => $config['retry_limit'],
                'sleep_seconds' => $config['sleep_seconds'],
            ],
            $defaultOptions,
            $options
        );
        $bindings = [
            'array $options' => $mergedOptions,
        ];
        $managerServiceName = "mcfedr_queue_manager.{$name}";

        switch ($managerConfig['driver']) {
            case 'beanstalkd':
                if (isset($mergedOptions['pheanstalk'])) {
                    $bindings[PheanstalkInterface::class.' $pheanstalk'] = new Reference($mergedOptions['pheanstalk']);
                    unset($mergedOptions['pheanstalk']);
                } else {
                    $pheanstalk = (new Definition(Pheanstalk::class, [
                        $mergedOptions['host'],
                        $mergedOptions['port'],
                        $mergedOptions['connection']['timeout'],
                    ]))
                        ->setFactory(Pheanstalk::class.'::create')
                    ;
                    unset($mergedOptions['host'], $mergedOptions['port'], $mergedOptions['connection']);

                    $pheanstalkName = "{$managerServiceName}.pheanstalk";
                    $container->setDefinition($pheanstalkName, $pheanstalk);
                    $bindings[PheanstalkInterface::class.' $pheanstalk'] = new Reference($pheanstalkName);
                }

                break;

            case 'sqs':
                if (!class_exists(SqsClient::class)) {
                    throw new \LogicException('"sqs" requires aws/aws-sdk-php to be installed.');
                }
                if (isset($mergedOptions['sqs_client'])) {
                    $bindings[SqsClient::class.' $sqsClient'] = new Reference($mergedOptions['sqs_client']);
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
                    $bindings[SqsClient::class.' $sqsClient'] = new Reference($sqsClientName);
                }

                break;

            case 'doctrine_delay':
                if (!isset(($container->getParameter('kernel.bundles'))['DoctrineBundle'])) {
                    throw new \LogicException('"doctrine_delay" requires doctrine/doctrine-bundle to be installed.');
                }

                break;

            case 'pub_sub':
                if (!class_exists(PubSubClient::class)) {
                    throw new \LogicException('"pub_sub" requires google/cloud-pubsub to be installed.');
                }
                if (isset($mergedOptions['pub_sub_client'])) {
                    $bindings[PubSubClient::class.' $pubSubClient'] = new Reference($mergedOptions['pub_sub_client']);
                    unset($mergedOptions['pub_sub_client']);
                } else {
                    $pubSubOptions = [];
                    if (\array_key_exists('key_file_path', $mergedOptions)) {
                        $pubSubOptions['keyFilePath'] = $mergedOptions['key_file_path'];
                        unset($mergedOptions['key_file_path']);
                    }
                    $pubSubClient = new Definition(PubSubClient::class, [$pubSubOptions]);
                    $pubSubClientName = "{$managerServiceName}.pub_sub_client";
                    $container->setDefinition($pubSubClientName, $pubSubClient);
                    $bindings[PubSubClient::class.' $pubSubClient'] = new Reference($pubSubClientName);
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

        if (isset($config['drivers'][$managerConfig['driver']]['command_class'])) {
            $commandClass = $config['drivers'][$managerConfig['driver']]['command_class'];
            $commandDefinition = new Definition($commandClass);
            $commandBindings = array_merge(
                [
                    '$name' => "mcfedr:queue:{$name}-runner",
                ],
                $bindings
            );
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
}
