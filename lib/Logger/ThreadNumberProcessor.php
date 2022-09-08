<?php
/**
 * @copyright Macintoshplus (c) 2019
 * Added by : Macintoshplus at 20/02/19 09:09
 */

namespace Win32ServiceBundle\Logger;


use Symfony\Contracts\EventDispatcher\Event;

class ThreadNumberProcessor
{
    private ?int $threadNumber = null;

    public function setThreadNumber(Event $evt): void {
        if (!$evt instanceof ThreadNumberEvent) {
            return;
        }
        $this->threadNumber = $evt->getThreadNumber();
    }


    public function __invoke(array $record): array
    {
        $record['extra']['threadNumber'] = $this->threadNumber;
        return $record;
    }

}
