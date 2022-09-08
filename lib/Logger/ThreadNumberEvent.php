<?php
/**
 * @copyright Macintoshplus (c) 2019
 * Added by : Macintoshplus at 20/02/19 09:09
 */

namespace Win32ServiceBundle\Logger;

use Symfony\Contracts\EventDispatcher\Event;

class ThreadNumberEvent extends Event
{
    public const NAME = 'win32service.thread_number';

    public function __construct(private int $threadNumber)
    {
    }

    public function getThreadNumber(): int
    {
        return $this->threadNumber;
    }
}
