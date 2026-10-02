<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\WindowsApplication\CacheWarmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Simulates a slow Symfony cache warmup (heavy container compilation,
 * annotations/attributes scanning, Doctrine metadata, ...), a common
 * real-world cause of Windows services taking more than 30 seconds to start.
 *
 * Cache warmup runs during kernel boot, before the service even registers its
 * control dispatcher with the Windows Service Manager, so this delays the
 * "wtst_slowstart" service more realistically than an artificial sleep() in
 * its own business code would.
 */
final class SlowStartCacheWarmer implements CacheWarmerInterface
{
    public const DELAY_SECONDS = 35;

    public function isOptional(): bool
    {
        return false;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        sleep(self::DELAY_SECONDS);

        return [];
    }
}
