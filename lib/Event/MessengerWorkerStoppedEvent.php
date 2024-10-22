<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Event;

use Win32ServiceBundle\Model\MessengerServiceRunner;

final class MessengerWorkerStoppedEvent
{
    private MessengerServiceRunner $messengerServiceRunner;

    public function __construct(MessengerServiceRunner $messengerServiceRunner)
    {
        $this->messengerServiceRunner = $messengerServiceRunner;
    }

    public function getMessengerServiceRunner(): MessengerServiceRunner
    {
        return $this->messengerServiceRunner;
    }
}
