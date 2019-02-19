<?php
/**
 * @copyright Macintoshplus (c) 2019
 * Added by : Macintoshplus at 19/02/19 23:09
 */

namespace Win32ServiceBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;
use Win32ServiceBundle\Service\RunnerManager;

class TagRunnerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->has(RunnerManager::class)) {
            return;
        }

        $definition = $container->findDefinition(RunnerManager::class);

        // find all service IDs with the app.mail_transport tag
        $taggedServices = $container->findTaggedServiceIds('win32service.runner');

        foreach ($taggedServices as $id => $tags) {

            // a service could have the same tag twice
            foreach ($tags as $attributes) {
                $definition->addMethodCall('addRunner', [
                    new Reference($id),
                    $attributes["alias"]
                ]);
            }
        }
    }
}