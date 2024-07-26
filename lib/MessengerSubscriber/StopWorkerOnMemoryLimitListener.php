<?php

declare(strict_types=1);

namespace Win32ServiceBundle\MessengerSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Win32ServiceBundle\Event\MessengerWorkerRunningEvent;

final class StopWorkerOnMemoryLimitListener implements EventSubscriberInterface
{
    private int $memoryLimit;
    private ?LoggerInterface $logger;
    private $memoryResolver;

    public function __construct(int $memoryLimit, ?LoggerInterface $logger = null, ?callable $memoryResolver = null)
    {
        $this->memoryLimit = $memoryLimit;
        $this->logger = $logger;
        $this->memoryResolver = $memoryResolver ?: static function () {
            return memory_get_usage(true);
        };
    }

    public function onWorkerRunning(MessengerWorkerRunningEvent $event): void
    {
        $memoryResolver = $this->memoryResolver;
        $usedMemory = $memoryResolver();
        if ($usedMemory > $this->memoryLimit) {
            $event->getMessengerServiceRunner()->stop();
            if ($this->logger !== null) {
                $this->logger->info('Worker stopped due to memory limit of {limit} bytes exceeded ({memory} bytes used)', ['limit' => $this->memoryLimit, 'memory' => $usedMemory]);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessengerWorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
