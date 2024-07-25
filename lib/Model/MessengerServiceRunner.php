<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Model;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\RoutableMessageBus;
use Win32Service\Model\AbstractServiceRunner;

final class MessengerServiceRunner extends AbstractServiceRunner
{
    public const SERVICE_TAG_PATTERN = 'win32service.messenger.%s.%%s';

    public function __construct(
        private array $config,
        private RoutableMessageBus $routableBus,
        private ContainerInterface $receiverLocator,
        private EventDispatcherInterface $eventDispatcher,
        private ?LoggerInterface $logger = null,
        private array $receiverNames = [],
        private ?ResetServicesListener $resetServicesListener = null,
        private array $busIds = []
    ) {
    }

    protected function setup(): void
    {
        // TODO: Implement setup() method.
    }

    protected function beforeContinue(): void
    {
        // TODO: Implement beforeContinue() method.
    }

    protected function beforePause(): void
    {
        // TODO: Implement beforePause() method.
    }

    protected function run(int $control): void
    {
        // TODO: Implement run() method.
        sleep(3);
    }

    protected function lastRunIsTooSlow(float $duration): void
    {
        // TODO: Implement lastRunIsTooSlow() method.
    }

    protected function beforeStop(): void
    {
        // TODO: Implement beforeStop() method.
    }
}
