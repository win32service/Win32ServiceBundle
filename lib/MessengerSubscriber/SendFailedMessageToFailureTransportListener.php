<?php

declare(strict_types=1);

namespace Win32ServiceBundle\MessengerSubscriber;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Win32ServiceBundle\Event\MessengerWorkerMessageFailedEvent;

class SendFailedMessageToFailureTransportListener implements EventSubscriberInterface
{
    private ContainerInterface $failureSenders;
    private ?LoggerInterface $logger;

    public function __construct(ContainerInterface $failureSenders, ?LoggerInterface $logger = null)
    {
        $this->failureSenders = $failureSenders;
        $this->logger = $logger;
    }

    public function onMessageFailed(MessengerWorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        if (!$this->failureSenders->has($event->getReceiverName())) {
            return;
        }

        $failureSender = $this->failureSenders->get($event->getReceiverName());
        if ($failureSender === null) {
            return;
        }

        $envelope = $event->getEnvelope();

        // avoid re-sending to the failed sender
        if ($envelope->last(SentToFailureTransportStamp::class) !== null) {
            return;
        }

        $envelope = $envelope->with(
            new SentToFailureTransportStamp($event->getReceiverName()),
            new DelayStamp(0),
            new RedeliveryStamp(0)
        );

        if ($this->logger !== null) {
            $this->logger->info('Rejected message {class} will be sent to the failure transport {transport}.', [
                'class' => \get_class($envelope->getMessage()),
                'transport' => $failureSender::class,
            ]);
        }

        $failureSender->send($envelope);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessengerWorkerMessageFailedEvent::class => ['onMessageFailed', -100],
        ];
    }
}
