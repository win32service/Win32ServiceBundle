<?php

declare(strict_types=1);

namespace Win32ServiceBundle\MessengerSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Win32ServiceBundle\Event\MessengerWorkerRunningEvent;

final class StopWorkerOnMessageLimitListener implements EventSubscriberInterface
{
    private int $maximumNumberOfMessages;
    private ?LoggerInterface $logger;
    private int $receivedMessages = 0;

    public function __construct(int $maximumNumberOfMessages, ?LoggerInterface $logger = null)
    {
        $this->maximumNumberOfMessages = $maximumNumberOfMessages;
        $this->logger = $logger;

        if ($maximumNumberOfMessages <= 0) {
            throw new InvalidArgumentException('Message limit must be greater than zero.');
        }
    }

    public function onWorkerRunning(MessengerWorkerRunningEvent $event): void
    {
        if (!$event->isWorkerIdle() && ++$this->receivedMessages >= $this->maximumNumberOfMessages) {
            $this->receivedMessages = 0;
            $event->getMessengerServiceRunner()->stop();

            if ($this->logger !== null) {
                $this->logger->info('Worker stopped due to maximum count of {count} messages processed', ['count' => $this->maximumNumberOfMessages]);
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
