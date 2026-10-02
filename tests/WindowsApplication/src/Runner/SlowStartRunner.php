<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\WindowsApplication\Runner;

use Win32Service\Model\AbstractServiceRunner;

/**
 * A service whose kernel boot takes more than 30 seconds (see
 * CacheWarmer\SlowStartCacheWarmer), used to check how the Windows Service
 * Manager really behaves with a slow-starting service.
 */
final class SlowStartRunner extends AbstractServiceRunner
{
    protected function setup(): void
    {
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
