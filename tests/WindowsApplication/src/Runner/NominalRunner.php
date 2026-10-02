<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\WindowsApplication\Runner;

use Win32Service\Model\AbstractServiceRunner;

/**
 * A service that starts and stops immediately, used as the nominal case for the
 * real-condition Windows CI workflow.
 */
final class NominalRunner extends AbstractServiceRunner
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
