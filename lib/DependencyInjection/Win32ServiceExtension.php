<?php

declare(strict_types=1);
/**
 * @copy Win32Service (c) 2019
 * Added by : macintoshplus at 19/02/19 13:35
 */

namespace Win32ServiceBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\AbstractArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Win32ServiceBundle\Logger\ThreadNumberEvent;
use Win32ServiceBundle\Logger\ThreadNumberProcessor;
use Win32ServiceBundle\Model\MessengerServiceRunner;

class Win32ServiceExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);
        $config = $this->processMessengerConfig($config);
        $container->setParameter('win32service.config', $config);

        $this->processMessenger($config['messenger'], $config['project_code'], $container);

        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
        $loader->load('services.yaml');

        if (!$config['logging_extra']['enable']) {
            return;
        }
        // New service definition
        $definition = new Definition(ThreadNumberProcessor::class);
        // If already register, get the current definition
        if ($container->hasDefinition(ThreadNumberProcessor::class)) {
            $definition = $container->findDefinition(ThreadNumberProcessor::class);
        }
        // If no definition, add the definition into container
        if (!$container->hasDefinition(ThreadNumberProcessor::class)) {
            $container->setDefinition(ThreadNumberProcessor::class, $definition);
        }

        // Add the tag for receive the thread number event
        if (!$definition->hasTag('kernel.event_listener')) {
            $definition->addTag(
                'kernel.event_listener',
                ['event' => ThreadNumberEvent::NAME, 'method' => 'setThreadNumber']
            );
        }

        // Add tags for each channel defined
        $channels = $config['logging_extra']['channels'];
        if (\count($channels) > 0) {
            foreach ($channels as $channel) {
                $definition->addTag('monolog.processor', ['channel' => $channel]);
            }

            return;
        }

        // If no channels defined, the processor is enable for all
        $definition->addTag('monolog.processor');
    }

    public function processMessenger(array $messengerConfig, string $projectCode, ContainerBuilder $container): void
    {
        foreach ($messengerConfig as $service) {
            $name = sprintf(
                MessengerServiceRunner::SERVICE_TAG_PATTERN,
                $projectCode,
                implode('_', $service['receivers'])
            );
            $arguments = [
                $service,
                new AbstractArgument('Routable message bus'),
                new Reference('messenger.receiver_locator'),
                new Reference('event_dispatcher'),
                new Reference('messenger.bus.default'),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                [],
                new Reference('messenger.listener.reset_services', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                [],
            ];
            $serviceDefinition = new Definition(MessengerServiceRunner::class, $arguments);
            $serviceDefinition->addTag(TagRunnerCompilerPass::WIN32SERVICE_RUNNER_TAG, ['alias' => $name]);
            $serviceDefinition->addTag(TagRunnerCompilerPass::WIN32SERVICE_RUNNER_TAG.'.messenger');
            $container->setDefinition($name, $serviceDefinition);
        }
    }

    private function processMessengerConfig(array $config): array
    {
        foreach ($config['messenger'] as $service) {
            $strlen = \strlen((string) ($service['thread_count'] - 1)) - 2;
            $templatedName = sprintf(
                MessengerServiceRunner::SERVICE_TAG_PATTERN,
                $config['project_code'],
                implode('_', $service['receivers'])
            );
            if (($totalLength = $strlen + \strlen($templatedName)) > 80) {
                throw new \Win32ServiceException(sprintf('The future service identity length "%s" is over 80 chars (%d). Reduce the project code or "receivers" number or name length to keep less than 80 chars.', sprintf($templatedName, $service['thread_count'] - 1), $totalLength));
            }
            $config['services'][] = [
                'machine' => $service['machine'],
                'displayed_name' => $service['displayed_name'],
                'description' => $service['description'],
                'delayed_start' => $service['delayed_start'],
                'user' => $service['user'],
                'thread_count' => $service['thread_count'],
                'script_path' => null,
                'script_params' => '',
                'service_id' => $templatedName,
                'recovery' => [
                    'enable' => true,
                    'delay' => 100,
                    'action1' => WIN32_SC_ACTION_RESTART,
                    'action2' => WIN32_SC_ACTION_RESTART,
                    'action3' => WIN32_SC_ACTION_RESTART,
                    'reboot_msg' => '',
                    'command' => '',
                    'reset_period' => 1,
                ],
                'exit' => [
                    'graceful' => false,
                    'code' => 1,
                ],
                'dependencies' => [],
            ];
        }

        return $config;
    }
}
