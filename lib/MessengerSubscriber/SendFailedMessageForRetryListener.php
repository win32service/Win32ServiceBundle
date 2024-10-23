<?php

declare(strict_types=1);

namespace Win32ServiceBundle\MessengerSubscriber;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageRetriedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RecoverableExceptionInterface;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Win32ServiceBundle\Event\MessengerWorkerMessageFailedEvent;

class SendFailedMessageForRetryListener implements EventSubscriberInterface
{
    private ContainerInterface $sendersLocator;
    private ContainerInterface $retryStrategyLocator;
    private ?LoggerInterface $logger;
    private ?EventDispatcherInterface $eventDispatcher;
    private int $historySize;

    public function __construct(ContainerInterface $sendersLocator, ContainerInterface $retryStrategyLocator, ?LoggerInterface $logger = null, ?EventDispatcherInterface $eventDispatcher = null, int $historySize = 10)
    {
        $this->sendersLocator = $sendersLocator;
        $this->retryStrategyLocator = $retryStrategyLocator;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
        $this->historySize = $historySize;
    }

    public function onMessageFailed(MessengerWorkerMessageFailedEvent $event): void
    {
        $retryStrategy = $this->getRetryStrategyForTransport($event->getReceiverName());
        $envelope = $event->getEnvelope();
        $throwable = $event->getThrowable();

        $message = $envelope->getMessage();
        $context = [
            'class' => $message::class,
        ];

        $shouldRetry = $retryStrategy && $this->shouldRetry($throwable, $envelope, $retryStrategy);

        $retryCount = RedeliveryStamp::getRetryCountFromEnvelope($envelope);
        if ($shouldRetry) {
            $event->setForRetry();

            ++$retryCount;

            $delay = $retryStrategy->getWaitingTime($envelope, $throwable);

            if ($this->logger !== null) {
                $this->logger->warning('Error thrown while handling message {class}. Sending for retry #{retryCount} using {delay} ms delay. Error: "{error}"', $context + ['retryCount' => $retryCount, 'delay' => $delay, 'error' => $throwable->getMessage(), 'exception' => $throwable]);
            }

            // add the delay and retry stamp info
            $retryEnvelope = $this->withLimitedHistory($envelope, new DelayStamp($delay), new RedeliveryStamp($retryCount));

            // re-send the message for retry
            $this->getSenderForTransport($event->getReceiverName())->send($retryEnvelope);

            if ($this->eventDispatcher !== null) {
                $this->eventDispatcher->dispatch(new WorkerMessageRetriedEvent($retryEnvelope, $event->getReceiverName()));
            }
        } else {
            if ($this->logger !== null) {
                $this->logger->critical('Error thrown while handling message {class}. Removing from transport after {retryCount} retries. Error: "{error}"', $context + ['retryCount' => $retryCount, 'error' => $throwable->getMessage(), 'exception' => $throwable]);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // must have higher priority than SendFailedMessageToFailureTransportListener
            MessengerWorkerMessageFailedEvent::class => ['onMessageFailed', 100],
        ];
    }

    /**
     * Adds stamps to the envelope by keeping only the First + Last N stamps.
     */
    private function withLimitedHistory(Envelope $envelope, StampInterface ...$stamps): Envelope
    {
        foreach ($stamps as $stamp) {
            $history = $envelope->all($stamp::class);
            if (\count($history) < $this->historySize) {
                $envelope = $envelope->with($stamp);
                continue;
            }

            $history = array_merge(
                [$history[0]],
                \array_slice($history, -$this->historySize + 2),
                [$stamp]
            );

            $envelope = $envelope->withoutAll($stamp::class)->with(...$history);
        }

        return $envelope;
    }

    private function shouldRetry(\Throwable $e, Envelope $envelope, RetryStrategyInterface $retryStrategy): bool
    {
        if ($e instanceof RecoverableExceptionInterface) {
            return true;
        }

        // if one or more nested Exceptions is an instance of RecoverableExceptionInterface we should retry
        // if ALL nested Exceptions are an instance of UnrecoverableExceptionInterface we should not retry
        if ($e instanceof HandlerFailedException) {
            $shouldNotRetry = true;
            foreach ($e->getNestedExceptions() as $nestedException) {
                if ($nestedException instanceof RecoverableExceptionInterface) {
                    return true;
                }

                if (!$nestedException instanceof UnrecoverableExceptionInterface) {
                    $shouldNotRetry = false;
                    break;
                }
            }
            if ($shouldNotRetry) {
                return false;
            }
        }

        if ($e instanceof UnrecoverableExceptionInterface) {
            return false;
        }

        return $retryStrategy->isRetryable($envelope, $e);
    }

    private function getRetryStrategyForTransport(string $alias): ?RetryStrategyInterface
    {
        if ($this->retryStrategyLocator->has($alias)) {
            return $this->retryStrategyLocator->get($alias);
        }

        return null;
    }

    private function getSenderForTransport(string $alias): SenderInterface
    {
        if ($this->sendersLocator->has($alias)) {
            return $this->sendersLocator->get($alias);
        }

        throw new RuntimeException(\sprintf('Could not find sender "%s" based on the same receiver to send the failed message to for retry.', $alias));
    }
}
