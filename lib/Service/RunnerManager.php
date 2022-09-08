<?php
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
    private $runner;

    public function __construct()
    {
        $this->runner = [];
    }

    public function addRunner(RunnerServiceInterface $runner, string $alias) {
        $this->runner[$alias] = $runner;
    }

    /**
     * @return RunnerServiceInterface|null
     */
    public function getRunner(string $alias) {
        if (!isset($this->runner[$alias])) {
            return null;
        }

        return $this->runner[$alias];
    }
}