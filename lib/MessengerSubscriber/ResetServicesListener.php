<?php

declare(strict_types=1);

namespace Win32ServiceBundle\MessengerSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symfony\Component\Messenger\Event\MessengerWorkerStoppedEvent;
use Win32ServiceBundle\Event\MessengerWorkerRunningEvent;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ResetServicesListener implements EventSubscriberInterface
{
    private ServicesResetter $servicesResetter;

    public function __construct(ServicesResetter $servicesResetter)
    {
        $this->servicesResetter = $servicesResetter;
    }

    public function resetServices(MessengerWorkerRunningEvent $event): void
    {
        if (!$event->isWorkerIdle()) {
            $this->servicesResetter->reset();
        }
    }

    public function resetServicesAtStop(MessengerWorkerStoppedEvent $event): void
    {
        $this->servicesResetter->reset();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MessengerWorkerRunningEvent::class => ['resetServices', -1024],
            MessengerWorkerStoppedEvent::class => ['resetServicesAtStop', -1024],
        ];
    }
}
