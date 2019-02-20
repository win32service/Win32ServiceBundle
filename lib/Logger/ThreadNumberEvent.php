<?php
/**
 * @copyright Macintoshplus (c) 2019
 * Added by : Macintoshplus at 20/02/19 09:09
 */

namespace Win32ServiceBundle\Logger;

use Symfony\Component\EventDispatcher\Event;

class ThreadNumberEvent extends Event
{
    public const NAME = 'win32service.thread_number';

    private $threadNumber;

    /**
     * ThreadNumberEvent constructor.
     * @param int $threadNumber
     */
    public function __construct(int $threadNumber)
    {
        $this->threadNumber = $threadNumber;
    }

    /**
     * @return int
     */
    public function getThreadNumber(): int
    {
        return $this->threadNumber;
    }
}
