<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\DependencyInjection;

use Mcfedr\QueueManagerBundle\Controller\PubSubController;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('mcfedr_queue_manager');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode
            ->children()
            ->integerNode('retry_limit')->defaultValue(3)->end()
            ->integerNode('sleep_seconds')->defaultValue(5)->end()
            ->booleanNode('report_memory')->defaultFalse()->end()
            ->booleanNode('doctrine_reset')->defaultTrue()->end()
            ->integerNode('swift_mailer_batch_size')->defaultValue(10)->end()
            ->arrayNode('drivers')
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->children()
            ->scalarNode('class')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('command_class')->end()
            ->variableNode('options')->end()
            ->end()
            ->end()
            ->end()
            ->arrayNode('managers')
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('driver')->cannotBeEmpty()->end()
            ->arrayNode('options')
            ->children()
            // Generic
            ->scalarNode('host')->end()
            ->integerNode('port')->end()
            ->scalarNode('default_queue')->end()
            //Beanstalk
            ->arrayNode('connection')
            ->children()
            ->integerNode('timeout')->end()
            ->booleanNode('persistent')->end()
            ->end()
            ->end()
            ->scalarNode('pheanstalk')->end()
            //Doctrine Delay
            ->scalarNode('entity_manager')->end()
            ->scalarNode('default_manager')->end()
            ->variableNode('default_manager_options')->end()
            //SQS
            ->arrayNode('queues')
            ->scalarPrototype()->end()
            ->end()
            ->scalarNode('default_url')->end()
            ->scalarNode('region')->end()
            ->variableNode('credentials')->end()
            ->scalarNode('sqs_client')->end()
            //Pub/Sub
            ->arrayNode('pub_sub_queues')
            ->arrayPrototype()
            ->children()
            ->scalarNode('topic')->end()
            ->scalarNode('subscription')->end()
            ->scalarNode('audience')->defaultValue(PubSubController::DEFAULT_AUDIENCE)->end()
            ->end()
            ->end()
            ->end()
            ->variableNode('projectId')->end()
            ->scalarNode('default_subscription')->end()
            ->scalarNode('default_topic')->end()
            ->scalarNode('pub_sub_client')->end()
            ->scalarNode('key_file_path')->end()
            // Periodic
            ->scalarNode('delay_manager')->end()
            ->variableNode('delay_manager_options')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
