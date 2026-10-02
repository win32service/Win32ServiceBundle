<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\Windows;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Win32ServiceBundle\Tests\WindowsApplication\Runner\SlowStartRunner;

/**
 * Real-condition test, meant to run on an actual Windows machine with the
 * win32service extension installed. It drives the bundle exactly like an
 * operator would, through its console commands, and checks the resulting
 * state directly against the Windows Service Control Manager.
 *
 * It registers two services (a nominal one, and one whose setup() takes more
 * than 30 seconds), starts them, stops them, and removes them.
 */
final class ServiceLifecycleTest extends TestCase
{
    private const CONSOLE_SCRIPT = __DIR__.'/../WindowsApplication/bin/console';
    private const NOMINAL_SERVICE = 'wtst_nominal';
    private const SLOW_START_SERVICE = 'wtst_slowstart';
    private const SERVICES = [self::NOMINAL_SERVICE, self::SLOW_START_SERVICE];

    protected function setUp(): void
    {
        if (\PHP_OS_FAMILY !== 'Windows') {
            self::markTestSkipped('This test exercises the real Windows Service Manager and can only run on Windows.');
        }

        if (!\function_exists('win32_query_service_status')) {
            self::markTestSkipped('The win32service extension is not loaded.');
        }

        $this->cleanupServices();
    }

    protected function tearDown(): void
    {
        if (\PHP_OS_FAMILY === 'Windows' && \function_exists('win32_query_service_status')) {
            $this->cleanupServices();
        }
    }

    public function testRegisterStartStopAndUnregisterServices(): void
    {
        $register = $this->runConsole(['win32service:register'], 30);
        self::assertSame(
            0,
            $register->getExitCode(),
            "Registration failed:\n".$register->getOutput().$register->getErrorOutput()
        );

        foreach (self::SERVICES as $serviceId) {
            self::assertSame(
                'stopped',
                $this->queryState($serviceId),
                \sprintf('Service "%s" must be stopped right after registration.', $serviceId)
            );
        }

        // --- Nominal service: must start within a few seconds. ---
        $start = microtime(true);
        $result = $this->runConsole(['win32service:action', 'start', '--service-name='.self::NOMINAL_SERVICE], 30);
        self::assertSame(
            0,
            $result->getExitCode(),
            "Unable to request start of the nominal service:\n".$result->getOutput().$result->getErrorOutput()
        );

        $reachedRunning = $this->waitForState(self::NOMINAL_SERVICE, 'running', 15);
        $duration = microtime(true) - $start;
        self::assertTrue($reachedRunning, 'The nominal service never reached the "running" state.');
        self::assertLessThan(15.0, $duration, 'The nominal service should start in a few seconds.');

        // --- Slow-start service: setup() runs for more than 30 seconds before
        //     the service can report WIN32_SERVICE_RUNNING, without updating
        //     its checkpoint in the meantime. This checks whether the real
        //     Windows Service Manager kills it as "not responding" or really
        //     waits for it. ---
        $start = microtime(true);
        $result = $this->runConsole(['win32service:action', 'start', '--service-name='.self::SLOW_START_SERVICE], 30);
        self::assertSame(
            0,
            $result->getExitCode(),
            "Unable to request start of the slow-start service:\n".$result->getOutput().$result->getErrorOutput()
        );

        [$reachedRunning, $neverKilled] = $this->waitForRunningWithoutBeingKilled(
            self::SLOW_START_SERVICE,
            SlowStartRunner::SETUP_DURATION_SECONDS + 30
        );
        $duration = microtime(true) - $start;

        self::assertTrue(
            $neverKilled,
            'The Windows Service Manager stopped/killed the service while its setup() (which takes '
            .SlowStartRunner::SETUP_DURATION_SECONDS.' seconds) was still running. This is the exact '
            .'real-condition regression this test is meant to catch.'
        );
        self::assertTrue(
            $reachedRunning,
            'The slow-start service never reached the "running" state within the allotted time.'
        );
        self::assertGreaterThan(
            30.0,
            $duration,
            'The slow-start service setup() is expected to really take more than 30 seconds.'
        );

        // --- Stop both services. ---
        foreach (self::SERVICES as $serviceId) {
            $result = $this->runConsole(['win32service:action', 'stop', '--service-name='.$serviceId], 30);
            self::assertSame(
                0,
                $result->getExitCode(),
                \sprintf("Unable to stop service \"%s\":\n", $serviceId).$result->getOutput().$result->getErrorOutput()
            );
            self::assertTrue(
                $this->waitForState($serviceId, 'stopped', 15),
                \sprintf('Service "%s" never reached the "stopped" state.', $serviceId)
            );
        }

        // --- Unregister (delete) both services. ---
        $unregister = $this->runConsole(['win32service:unregister'], 30);
        self::assertSame(
            0,
            $unregister->getExitCode(),
            "Unregistration failed:\n".$unregister->getOutput().$unregister->getErrorOutput()
        );

        foreach (self::SERVICES as $serviceId) {
            self::assertSame(
                'not_found',
                $this->queryState($serviceId),
                \sprintf('Service "%s" must not exist anymore after unregistration.', $serviceId)
            );
        }
    }

    private function cleanupServices(): void
    {
        foreach (self::SERVICES as $serviceId) {
            if ($this->queryState($serviceId) === 'not_found') {
                continue;
            }

            if ($this->queryState($serviceId) !== 'stopped') {
                $this->runConsole(['win32service:action', 'stop', '--service-name='.$serviceId], 30);
                $this->waitForState($serviceId, 'stopped', 30);
            }

            $this->runConsole(['win32service:unregister', '--service-name='.$serviceId], 30);
        }
    }

    private function waitForState(string $serviceId, string $expectedState, int $timeoutSeconds): bool
    {
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            if ($this->queryState($serviceId) === $expectedState) {
                return true;
            }
            usleep(500000);
        } while (microtime(true) < $deadline);

        return $this->queryState($serviceId) === $expectedState;
    }

    /**
     * Polls the service state until it reaches "running", while checking it
     * never gets stopped/removed in between (which would mean the SCM killed
     * it during the slow startup).
     *
     * @return array{0: bool, 1: bool} [reachedRunning, neverKilled]
     */
    private function waitForRunningWithoutBeingKilled(string $serviceId, int $timeoutSeconds): array
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $sawPending = false;
        do {
            $state = $this->queryState($serviceId);
            if ($state === 'running') {
                return [true, true];
            }
            if ($state === 'start_pending') {
                $sawPending = true;
            }
            if ($sawPending && ($state === 'stopped' || $state === 'not_found')) {
                return [false, false];
            }
            usleep(500000);
        } while (microtime(true) < $deadline);

        return [false, true];
    }

    private function runConsole(array $arguments, int $timeoutSeconds): Process
    {
        $process = new Process(array_merge([\PHP_BINARY, self::CONSOLE_SCRIPT, '--no-interaction'], $arguments));
        $process->setTimeout($timeoutSeconds);
        $process->run();

        return $process;
    }

    private function queryState(string $serviceId): string
    {
        try {
            $status = win32_query_service_status($serviceId, '');
        } catch (\Win32ServiceException $exception) {
            return $exception->getCode() === WIN32_ERROR_SERVICE_DOES_NOT_EXIST ? 'not_found' : 'error';
        }

        return match ($status['CurrentState']) {
            WIN32_SERVICE_RUNNING => 'running',
            WIN32_SERVICE_STOPPED => 'stopped',
            WIN32_SERVICE_START_PENDING => 'start_pending',
            WIN32_SERVICE_STOP_PENDING => 'stop_pending',
            WIN32_SERVICE_PAUSED => 'paused',
            default => 'unknown',
        };
    }
}
