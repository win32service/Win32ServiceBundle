<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Event;

use Win32ServiceBundle\Model\MessengerServiceRunner;

final class MessengerWorkerRunningEvent
{
    public function __construct(private MessengerServiceRunner $messengerServiceRunner, private bool $isWorkerIdle)
    {
    }

    public function getMessengerServiceRunner(): MessengerServiceRunner
    {
        return $this->messengerServiceRunner;
    }

    public function isWorkerIdle(): bool
    {
        return $this->isWorkerIdle;
    }
}
