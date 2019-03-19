<?php
/**
 * @copy Win32Service (c) 2019
 * Added by : macintoshplus at 19/02/19 13:34
 */

namespace Win32ServiceBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('win32service');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('windows_local_encoding')->defaultValue('ISO-8859-15')->end()
                ->arrayNode('logging_extra')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enable')->defaultFalse()->end()
                        ->arrayNode('channels')
                            ->defaultValue([])
                            ->scalarPrototype()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('services')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('service_id')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('machine')->defaultValue('')->end()
                            ->scalarNode('displayed_name')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('script_path')->defaultNull()->end()
                            ->scalarNode('script_params')->defaultValue('')->end()
                            ->integerNode('run_max')->defaultValue(1000)->min(-1)->end()
                            ->integerNode('thread_count')->defaultValue(1)->min(1)->end()
                            ->scalarNode('description')->defaultValue('')->end()
                            ->booleanNode('delayed_start')->defaultFalse()->end()
                            ->arrayNode('exit')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->booleanNode('graceful')->defaultTrue()->end()
                                    ->integerNode('code')->defaultValue(0)->end()
                                ->end()
                            ->end()
                            ->arrayNode('user')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('account')->defaultNull()->end()
                                    ->scalarNode('password')->defaultNull()->end()
                                ->end()
                            ->end()
                            ->arrayNode('recovery')
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->booleanNode('enable')->defaultFalse()->end()
                                    ->integerNode('delay')->defaultValue(60000)->min(100)->end()
                                    ->enumNode('action1')
                                        ->values([WIN32_SC_ACTION_NONE, WIN32_SC_ACTION_REBOOT, WIN32_SC_ACTION_RESTART, WIN32_SC_ACTION_RUN_COMMAND])
                                        ->defaultValue(WIN32_SC_ACTION_NONE)
                                    ->end()
                                    ->enumNode('action2')
                                        ->values([WIN32_SC_ACTION_NONE, WIN32_SC_ACTION_REBOOT, WIN32_SC_ACTION_RESTART, WIN32_SC_ACTION_RUN_COMMAND])
                                        ->defaultValue(WIN32_SC_ACTION_NONE)
                                    ->end()
                                    ->enumNode('action3')
                                        ->values([WIN32_SC_ACTION_NONE, WIN32_SC_ACTION_REBOOT, WIN32_SC_ACTION_RESTART, WIN32_SC_ACTION_RUN_COMMAND])
                                        ->defaultValue(WIN32_SC_ACTION_NONE)
                                    ->end()
                                    ->scalarNode('reboot_msg')->defaultValue("")->end()
                                    ->scalarNode('command')->defaultValue("")->end()
                                    ->integerNode('reset_period')->defaultValue(86400)->min(1)->end()
                                ->end()
                            ->end()
                            ->arrayNode('dependencies')
                                ->defaultValue([])
                                ->scalarPrototype()
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
