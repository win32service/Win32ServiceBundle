<?php
/**
 * @copy Win32Service (c) 2019
 * Added by : macintoshplus at 19/02/19 13:35
 */

namespace Win32ServiceBundle\DependencyInjection;


use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Win32ServiceBundle\Logger\ThreadNumberProcessor;

class Win32ServiceExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {

        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);
        $container->setParameter('win32service.config', $config);

        $loader = new YamlFileLoader($container, new FileLocator(dirname(__DIR__).'/Resources/config'));
        $loader->load('services.yaml');

        if (!$config['logging_extra']['enable']) {
            return;
        }
        //New service definition
        $definition = new Definition(ThreadNumberProcessor::class);
        //If already register, get the current definition
        if ($container->hasDefinition(ThreadNumberProcessor::class)) {
            $definition = $container->findDefinition(ThreadNumberProcessor::class);
        }
        //If no definition, add the definition into container
        if (!$container->hasDefinition(ThreadNumberProcessor::class)) {
            $container->setDefinition(ThreadNumberProcessor::class, $definition);
        }

        //Add the tag for receive the thread number event
        if (!$definition->hasTag('kernel.event_listener')) {
            $definition->addTag('kernel.event_listener', ['event'=>'service.thread_number', 'method'=>'setThreadNumber']);
        }

        //Add tags for each channel defined
        $channels = $config['logging_extra']['channels'];
        if (count($channels)>0) {
            foreach ($channels as $channel) {
                $definition->addTag('monolog.processor', ['channel' => $channel]);
            }
            return;
        }

        //If no channels defined, the processor is enable for all
        $definition->addTag('monolog.processor');

    }
}
