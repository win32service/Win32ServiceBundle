<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\Application\Event;

final class TestMemoryLimitMessage
{
    public function __construct(public int $size)
    {
    }
}
