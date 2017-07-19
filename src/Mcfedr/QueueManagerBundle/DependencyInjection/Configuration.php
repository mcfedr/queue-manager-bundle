<?php

namespace Mcfedr\QueueManagerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('mcfedr_queue_manager')
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
