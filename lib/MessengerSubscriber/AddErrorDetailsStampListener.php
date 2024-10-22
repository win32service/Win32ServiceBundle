<?php

declare(strict_types=1);

namespace Win32ServiceBundle\MessengerSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Win32ServiceBundle\Event\MessengerWorkerMessageFailedEvent;

final class AddErrorDetailsStampListener implements EventSubscriberInterface
{
    public function onMessageFailed(MessengerWorkerMessageFailedEvent $event): void
    {
        $stamp = ErrorDetailsStamp::create($event->getThrowable());
        $previousStamp = $event->getEnvelope()->last(ErrorDetailsStamp::class);

        // Do not append duplicate information
        if ($previousStamp === null || !$previousStamp->equals($stamp)) {
            $event->addStamps($stamp);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // must have higher priority than SendFailedMessageForRetryListener
            MessengerWorkerMessageFailedEvent::class => ['onMessageFailed', 200],
        ];
    }
}
