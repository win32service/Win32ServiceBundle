<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Model;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\EventListener\ResetServicesListener;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RejectRedeliveredMessageException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\RoutableMessageBus;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Win32Service\Model\AbstractServiceRunner;
use Win32ServiceBundle\Event\MessengerWorkerMessageFailedEvent;
use Win32ServiceBundle\Event\MessengerWorkerMessageHandledEvent;
use Win32ServiceBundle\Event\MessengerWorkerRunningEvent;
use Win32ServiceBundle\Event\MessengerWorkerStartedEvent;
use Win32ServiceBundle\MessengerSubscriber\StopWorkerOnFailureLimitListener;
use Win32ServiceBundle\MessengerSubscriber\StopWorkerOnMemoryLimitListener;
use Win32ServiceBundle\MessengerSubscriber\StopWorkerOnMessageLimitListener;
use Win32ServiceBundle\MessengerSubscriber\StopWorkerOnTimeLimitListener;

final class MessengerServiceRunner extends AbstractServiceRunner
{
    public const SERVICE_TAG_PATTERN = 'win32service.messenger.%s.%%s';
    /**
     * @var array<string, ReceiverInterface>
     */
    private array $receivers;
    private bool $shouldStop = false;

    private array $acks = [];
    private \SplObjectStorage $unacks;

    public function __construct(
        private array $config,
        private RoutableMessageBus $routableBus,
        private ContainerInterface $receiverLocator,
        private EventDispatcherInterface $eventDispatcher,
        private MessageBusInterface $bus,
        private ?LoggerInterface $logger = null,
        private array $receiverNames = [],
        private ?ResetServicesListener $resetServicesListener = null,
        private array $busIds = []
    ) {
        $this->unacks = new \SplObjectStorage();
    }

    protected function setup(): void
    {
        $this->eventDispatcher->addSubscriber($this->resetServicesListener);
        $limit = (int) $this->config['limit'];
        if ($limit > 0) {
            $this->eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener($limit, $this->logger));
        }
        $failureLimit = (int) $this->config['failure-limit'];
        if ($failureLimit > 0) {
            $this->eventDispatcher->addSubscriber(new StopWorkerOnFailureLimitListener($failureLimit, $this->logger));
        }
        $timeLimit = (int) $this->config['time-limit'];
        if ($timeLimit > 0) {
            $this->eventDispatcher->addSubscriber(new StopWorkerOnTimeLimitListener($timeLimit, $this->logger));
        }
        $memoryLimit = (string) $this->config['memory-limit'];
        if ($memoryLimit > 0) {
            $this->eventDispatcher->addSubscriber(new StopWorkerOnMemoryLimitListener(
                $this->convertToBytes($memoryLimit),
                $this->logger
            ));
        }

        $this->receivers = [];
        foreach ($this->config['receivers'] as $receiverName) {
            if (!$this->receiverLocator->has($receiverName)) {
                $message = sprintf('The receiver "%s" does not exist.', $receiverName);
                if ($this->receiverNames) {
                    $message .= sprintf(' Valid receivers are: %s.', implode(', ', $this->receiverNames));
                }

                throw new RuntimeException($message);
            }

            $this->receivers[$receiverName] = $this->receiverLocator->get($receiverName);
        }
    }

    public function stop(): void
    {
        if ($this->logger !== null) {
            $this->logger->info('Stopping worker.', ['transport_names' => array_keys($this->receivers)]);
        }

        $this->shouldStop = true;
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
        $this->eventDispatcher->dispatch(new MessengerWorkerStartedEvent($this));

        $envelopeHandled = false;
        foreach ($this->receivers as $transportName => $receiver) {
            $envelopes = $receiver->get();

            foreach ($envelopes as $envelope) {
                $envelopeHandled = true;

                $this->handleMessage($envelope, $transportName);
                $this->eventDispatcher->dispatch(new MessengerWorkerRunningEvent($this, false));

                if ($this->shouldStop) {
                    break 2;
                }
            }

            if ($envelopeHandled) {
                break;
            }
        }
        if (!$envelopeHandled && $this->flush(false)) {
            return;
        }

        if (!$envelopeHandled) {
            $this->eventDispatcher->dispatch(new MessengerWorkerRunningEvent($this, true));
        }
    }

    protected function lastRunIsTooSlow(float $duration): void
    {
        $this->logger->info('Last run is too low. Max 30s.', ['duration' => $duration]);
    }

    protected function beforeStop(): void
    {
        // TODO: Implement beforeStop() method.
    }

    private function handleMessage(Envelope $envelope, string $transportName): void
    {
        $event = new WorkerMessageReceivedEvent($envelope, $transportName);
        $this->eventDispatcher->dispatch($event);
        $envelope = $event->getEnvelope();

        if (!$event->shouldHandle()) {
            return;
        }

        $acked = false;
        $ack = function (Envelope $envelope, ?\Throwable $e = null) use ($transportName, &$acked) {
            $acked = true;
            $this->acks[] = [$transportName, $envelope, $e];
        };

        try {
            $e = null;
            $envelope = $this->bus->dispatch($envelope->with(
                new ReceivedStamp($transportName),
                new ConsumedByWorkerStamp(),
                new AckStamp($ack)
            ));
        } catch (\Throwable $e) {
        }

        $noAutoAckStamp = $envelope->last(NoAutoAckStamp::class);

        if (!$acked && !$noAutoAckStamp) {
            $this->acks[] = [$transportName, $envelope, $e];
        } elseif ($noAutoAckStamp) {
            $this->unacks[$noAutoAckStamp->getHandlerDescriptor()->getBatchHandler()] = [
                $envelope->withoutAll(AckStamp::class),
                $transportName,
            ];
        }

        $this->ack();
    }

    private function ack(): bool
    {
        $acks = $this->acks;
        $this->acks = [];

        foreach ($acks as [$transportName, $envelope, $e]) {
            $receiver = $this->receivers[$transportName];

            if ($e !== null) {
                if ($rejectFirst = $e instanceof RejectRedeliveredMessageException) {
                    // redelivered messages are rejected first so that continuous failures in an event listener or while
                    // publishing for retry does not cause infinite redelivery loops
                    $receiver->reject($envelope);
                }

                if ($e instanceof HandlerFailedException) {
                    $envelope = $e->getEnvelope();
                }

                $failedEvent = new MessengerWorkerMessageFailedEvent($envelope, $transportName, $e);

                $this->eventDispatcher->dispatch($failedEvent);
                $envelope = $failedEvent->getEnvelope();

                if (!$rejectFirst) {
                    $receiver->reject($envelope);
                }

                continue;
            }

            $handledEvent = new MessengerWorkerMessageHandledEvent($envelope, $transportName);
            $this->eventDispatcher->dispatch($handledEvent);
            $envelope = $handledEvent->getEnvelope();

            if ($this->logger !== null) {
                $message = $envelope->getMessage();
                $context = [
                    'class' => $message::class,
                ];
                $this->logger->info('{class} was handled successfully (acknowledging to transport).', $context);
            }

            $receiver->ack($envelope);
        }

        return (bool) $acks;
    }

    private function flush(bool $force): bool
    {
        $unacks = $this->unacks;

        if (!$unacks->count()) {
            return false;
        }

        $this->unacks = new \SplObjectStorage();

        foreach ($unacks as $batchHandler) {
            [$envelope, $transportName] = $unacks[$batchHandler];
            try {
                $this->bus->dispatch($envelope->with(new FlushBatchHandlersStamp($force)));
                $envelope = $envelope->withoutAll(NoAutoAckStamp::class);
                unset($unacks[$batchHandler], $batchHandler);
            } catch (\Throwable $e) {
                $this->acks[] = [$transportName, $envelope, $e];
            }
        }

        return $this->ack();
    }

    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = strtolower($memoryLimit);
        $max = ltrim($memoryLimit, '+');
        if (str_starts_with($max, '0x')) {
            $max = \intval($max, 16);
        } elseif (str_starts_with($max, '0')) {
            $max = \intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr(rtrim($memoryLimit, 'b'), -1)) {
            case 't':
                $max *= 1024;
                // no break
            case 'g':
                $max *= 1024;
                // no break
            case 'm':
                $max *= 1024;
                // no break
            case 'k':
                $max *= 1024;
        }

        return $max;
    }
}
