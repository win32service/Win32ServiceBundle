<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Event;

use Win32ServiceBundle\Model\MessengerServiceRunner;

final class MessengerWorkerStartedEvent
{
    public function __construct(
        private MessengerServiceRunner $messengerServiceRunner
    ) {
    }

    public function getMessengerServiceRunner(): MessengerServiceRunner
    {
        return $this->messengerServiceRunner;
    }
}
