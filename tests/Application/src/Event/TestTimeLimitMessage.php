<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\Application\Event;

final class TestTimeLimitMessage
{
    public function __construct(public int $durationInSeconds)
    {
    }
}
