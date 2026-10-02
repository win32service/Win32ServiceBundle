<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\WindowsApplication\Runner;

use Win32Service\Model\AbstractServiceRunner;

/**
 * A service whose setup() takes more than 30 seconds, used to check how the
 * Windows Service Manager really behaves with a slow-starting service (the
 * service reports WIN32_SERVICE_START_PENDING once and never updates its
 * checkpoint while setup() is running).
 */
final class SlowStartRunner extends AbstractServiceRunner
{
    public const SETUP_DURATION_SECONDS = 35;

    protected function setup(): void
    {
        sleep(self::SETUP_DURATION_SECONDS);
    }

    protected function run(int $control): void
    {
        usleep(200000);
    }

    protected function beforePause(): void
    {
    }

    protected function beforeContinue(): void
    {
    }

    protected function beforeStop(): void
    {
    }

    protected function lastRunIsTooSlow(float $duration): void
    {
    }
}
