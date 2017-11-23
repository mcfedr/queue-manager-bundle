<?php

namespace Mcfedr\QueueManagerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $treeBuilder->root('mcfedr_queue_manager')
            ->children()
                ->booleanNode('debug')->defaultFalse()->end()
                ->integerNode('retry_limit')->defaultValue(3)->end()
                ->integerNode('sleep_seconds')->defaultValue(5)->end()
                ->booleanNode('report_memory')->defaultFalse()->end()
                ->booleanNode('doctrine_reset')->defaultTrue()->end()
                ->integerNode('swift_mailer_batch_size')->defaultValue(10)->end()
                ->arrayNode('drivers')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
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
                    ->prototype('array')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('driver')->cannotBeEmpty()->end()
                            ->variableNode('options')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
