<?php

declare(strict_types=1);

namespace Win32ServiceBundle\MessengerSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Win32ServiceBundle\Event\MessengerWorkerRunningEvent;
use Win32ServiceBundle\Event\MessengerWorkerStartedEvent;

final class StopWorkerOnTimeLimitListener implements EventSubscriberInterface
{
    private int $timeLimitInSeconds;
    private ?LoggerInterface $logger;
    private $endTime;

    public function __construct(int $timeLimitInSeconds, ?LoggerInterface $logger = null)
    {
        $this->timeLimitInSeconds = $timeLimitInSeconds;
        $this->logger = $logger;

        if ($timeLimitInSeconds <= 0) {
            throw new InvalidArgumentException('Time limit must be greater than zero.');
        }
    }

    public function onWorkerStarted(): void
    {
        $startTime = microtime(true);
        $this->endTime = $startTime + $this->timeLimitInSeconds;
    }

    public function onWorkerRunning(MessengerWorkerRunningEvent $event): void
    {
        if ($this->endTime < microtime(true)) {
            $event->getMessengerServiceRunner()->stop();
            if ($this->logger !== null) {
                $this->logger->info('Worker stopped due to time limit of {timeLimit}s exceeded', ['timeLimit' => $this->timeLimitInSeconds]);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessengerWorkerStartedEvent::class => 'onWorkerStarted',
            MessengerWorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
