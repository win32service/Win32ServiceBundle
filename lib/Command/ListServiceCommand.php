<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Win32Service\Model\RunnerServiceInterface;
use Win32ServiceBundle\DependencyInjection\TagRunnerCompilerPass;
use Win32ServiceBundle\Service\RunnerManager;
use Win32ServiceBundle\Service\ServiceConfigurationManager;

#[AsCommand(name: 'win32service:list', description: 'List all services')]
final class ListServiceCommand extends Command
{
    public function __construct(
        private RunnerManager $runnerManager,
        private ServiceConfigurationManager $serviceConfigurationManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('List all services');
        $allAlias = array_keys($this->runnerManager->getRunners());
        $data = [];

        foreach ($this->serviceConfigurationManager->getFullServiceList() as $serviceInformations) {
            try {
                if (\function_exists('win32_query_service_status') === false) {
                    throw new \Win32ServiceException('Win32Service extension is not installed.');
                }
                $status = win32_query_service_status(
                    $serviceInformations->serviceId(),
                    $serviceInformations->machine()
                );
                $status = match ($status['CurrentState']) {
                    WIN32_SERVICE_CONTINUE_PENDING => 'continue pending',
                    WIN32_SERVICE_PAUSE_PENDING => 'pause pending',
                    WIN32_SERVICE_PAUSED => 'paused',
                    WIN32_SERVICE_RUNNING => 'running',
                    WIN32_SERVICE_START_PENDING => 'start pending',
                    WIN32_SERVICE_STOP_PENDING => 'stop pending',
                    WIN32_SERVICE_STOPPED => 'stopped',
                    default => 'Unknow',
                };
            } catch (\Win32ServiceException $exception) {
                $status = match ($exception->getCode()) {
                    WIN32_ERROR_ACCESS_DENIED => 'Access denied',
                    WIN32_ERROR_SERVICE_DOES_NOT_EXIST => 'Not registred',
                    default => $exception->getMessage(),
                };
            }
            $runnerTagAlias = $this->serviceConfigurationManager->getRunnerAliasForServiceId($serviceInformations->serviceId());
            $data[] = [
                empty($serviceInformations->machine()) ? 'localhost' : $serviceInformations->machine(),
                $serviceInformations->serviceId(),
                \in_array($runnerTagAlias, $allAlias) ? '<info>OK</info>' : sprintf(
                    '<error>No Symfony service implements "%s" with tag "name: \'%s\', alias: \'%s\'"</error>',
                    RunnerServiceInterface::class,
                    TagRunnerCompilerPass::WIN32SERVICE_RUNNER_TAG,
                    $runnerTagAlias,
                ),
                $status,
                $serviceInformations[WIN32_INFO_DISPLAY],
            ];
        }

        $io->table(['Machine', 'ServiceId', 'Runner config', 'State', 'Name'], $data);

        return self::SUCCESS;
    }
}
