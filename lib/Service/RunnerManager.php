<?php

declare(strict_types=1);
/**
 * @copyright Macintoshplus (c) 2019
 * Added by : Macintoshplus at 19/02/19 23:06
 */

namespace Win32ServiceBundle\Service;

use Win32Service\Model\RunnerServiceInterface;

class RunnerManager
{
    /**
     * @var RunnerServiceInterface[]
     */
    private array $runner = [];

    public function addRunner(RunnerServiceInterface $runner, string $alias): void
    {
        $this->runner[$alias] = $runner;
    }

    public function getRunner(string $alias): ?RunnerServiceInterface
    {
        return $this->runner[$alias] ?? null;
    }

    /** @return array<string, RunnerServiceInterface> */
    public function getRunners(): array
    {
        return $this->runner;
    }
}
