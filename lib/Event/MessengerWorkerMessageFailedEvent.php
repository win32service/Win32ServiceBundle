<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Event;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\AbstractWorkerMessageEvent;

final class MessengerWorkerMessageFailedEvent extends AbstractWorkerMessageEvent
{
    private \Throwable $throwable;
    private bool $willRetry = false;

    public function __construct(Envelope $envelope, string $receiverName, \Throwable $error)
    {
        $this->throwable = $error;

        parent::__construct($envelope, $receiverName);
    }

    public function getThrowable(): \Throwable
    {
        return $this->throwable;
    }

    public function willRetry(): bool
    {
        return $this->willRetry;
    }

    public function setForRetry(): void
    {
        $this->willRetry = true;
    }
}
