<?php

declare(strict_types=1);

namespace Win32ServiceBundle\MessengerSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Win32ServiceBundle\Event\MessengerWorkerMessageFailedEvent;
use Win32ServiceBundle\Event\MessengerWorkerRunningEvent;

final class StopWorkerOnFailureLimitListener implements EventSubscriberInterface
{
    private int $maximumNumberOfFailures;
    private ?LoggerInterface $logger;
    private int $failedMessages = 0;

    public function __construct(int $maximumNumberOfFailures, ?LoggerInterface $logger = null)
    {
        $this->maximumNumberOfFailures = $maximumNumberOfFailures;
        $this->logger = $logger;

        if ($maximumNumberOfFailures <= 0) {
            throw new InvalidArgumentException('Failure limit must be greater than zero.');
        }
    }

    public function onMessageFailed(MessengerWorkerMessageFailedEvent $event): void
    {
        ++$this->failedMessages;
    }

    public function onWorkerRunning(MessengerWorkerRunningEvent $event): void
    {
        if (!$event->isWorkerIdle() && $this->failedMessages >= $this->maximumNumberOfFailures) {
            $this->failedMessages = 0;
            $event->getMessengerServiceRunner()->stop();

            if ($this->logger !== null) {
                $this->logger->info('Worker stopped due to limit of {count} failed message(s) is reached', ['count' => $this->maximumNumberOfFailures]);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessengerWorkerMessageFailedEvent::class => 'onMessageFailed',
            MessengerWorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
