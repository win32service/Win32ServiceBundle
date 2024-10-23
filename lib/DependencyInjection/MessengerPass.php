<?php

declare(strict_types=1);

namespace Win32ServiceBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;
use Symfony\Component\DependencyInjection\Reference;
use Win32ServiceBundle\MessengerSubscriber\SendFailedMessageForRetryListener;
use Win32ServiceBundle\MessengerSubscriber\SendFailedMessageToFailureTransportListener;

final class MessengerPass implements CompilerPassInterface
{
    private string $busTag = 'messenger.bus';
    private string $receiverTag = 'messenger.receiver';
    private string $win32ServiceRunnerTag = TagRunnerCompilerPass::WIN32SERVICE_RUNNER_TAG.'.messenger';

    public function process(ContainerBuilder $container): void
    {
        $this->processService($container);
        $this->processRetryConfig($container);
        $this->processFailledConfig($container);
    }

    private function processService(ContainerBuilder $container): void
    {
        $busIds = [];
        foreach ($container->findTaggedServiceIds($this->busTag) as $busId => $tags) {
            $busIds[] = $busId;
        }

        $receiverMapping = [];
        foreach ($container->findTaggedServiceIds($this->receiverTag) as $id => $tags) {
            $receiverMapping[$id] = new Reference($id);

            foreach ($tags as $tag) {
                if (isset($tag['alias'])) {
                    $receiverMapping[$tag['alias']] = $receiverMapping[$id];
                }
            }
        }

        $receiverNames = [];
        foreach ($receiverMapping as $name => $reference) {
            $receiverNames[(string) $reference] = $name;
        }

        foreach ($container->findTaggedServiceIds($this->win32ServiceRunnerTag) as $win32ServiceId => $tags) {
            $serviceRunnerDefinition = $container->getDefinition($win32ServiceId);

            $serviceRunnerDefinition->replaceArgument(1, new Reference('messenger.routable_message_bus'));

            $serviceRunnerDefinition->replaceArgument(6, array_values($receiverNames));
            try {
                $serviceRunnerDefinition->replaceArgument(8, $busIds);
            } catch (OutOfBoundsException $e) {
                // ignore to preserve compatibility with symfony/framework-bundle < 5.4
            }
        }
    }

    private function processFailledConfig(ContainerBuilder $container): void
    {
        if (
            $container->hasDefinition('messenger.failure.send_failed_message_to_failure_transport_listener') === false
            || $container->hasDefinition(SendFailedMessageToFailureTransportListener::class) === false
        ) {
            return;
        }

        $serviceSF = $container->findDefinition('messenger.failure.send_failed_message_to_failure_transport_listener');

        $serviceWin32 = $container->findDefinition(SendFailedMessageToFailureTransportListener::class);
        $serviceWin32->replaceArgument('$failureSenders', $serviceSF->getArgument(0));
    }

    private function processRetryConfig(ContainerBuilder $container): void
    {
        if (
            $container->hasDefinition('messenger.retry.send_failed_message_for_retry_listener') === false
            || $container->hasDefinition(SendFailedMessageForRetryListener::class) === false
        ) {
            return;
        }

        $serviceSF = $container->findDefinition('messenger.retry.send_failed_message_for_retry_listener');

        $serviceWin32 = $container->findDefinition(SendFailedMessageForRetryListener::class);
        $serviceWin32->replaceArgument('$sendersLocator', $serviceSF->getArgument(0));
    }
}
