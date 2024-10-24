<?php

declare(strict_types=1);
/**
 * @copyright Win32Service (c) 2019
 * Added by : macintoshplus at 19/02/19 21:18
 */

namespace Win32ServiceBundle\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Win32Service\Model\ServiceIdentifier;
use Win32ServiceBundle\Logger\ThreadNumberEvent;
use Win32ServiceBundle\Service\RunnerManager;
use Win32ServiceBundle\Service\ServiceConfigurationManager;

#[AsCommand(name: 'win32service:run')]
class ExecuteServiceCommand extends Command
{
    public function __construct(
        private ServiceConfigurationManager $serviceConfigurationManager,
        private RunnerManager $service,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private ?LoggerInterface $logger = null
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Run the service');
        $this->addArgument('service-name', InputArgument::REQUIRED, 'The service name.');
        $this->addArgument('thread', InputArgument::REQUIRED, 'Thread number');
        $this->addOption('max-run', 'r', InputOption::VALUE_REQUIRED, 'Set the max run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceName = $input->getArgument('service-name');
        $threadNumber = (int) $input->getArgument('thread') ?? 1;
        $maxRun = $input->getOption('max-run');

        $infos = $this->serviceConfigurationManager->getServiceInformations($serviceName);

        if ($maxRun === null) {
            $maxRun = -1;
        }

        $runner = $this->service->getRunner($this->serviceConfigurationManager->getRunnerAliasForServiceId($serviceName));
        if ($runner === null) {
            throw new \Win32ServiceException(sprintf('The runner for service "%1$s" is not found. Call method \'add\' on the RunnerManager with the runner instance and the alias "%1$s".', $infos['service_id']));
        }

        if ($this->eventDispatcher !== null) {
            $event = new ThreadNumberEvent($threadNumber);
            $this->eventDispatcher->dispatch($event, ThreadNumberEvent::NAME);
        }

        $runner->setServiceId(ServiceIdentifier::identify($serviceName, $infos->machine()));
        $rawConfig = $this->serviceConfigurationManager->getServiceRawConfiguration($serviceName);
        $this->logger?->info(
            'Configure exit graceful and code',
            ['exit_graceful' => $rawConfig['exit']['graceful'], 'exit_code' => $rawConfig['exit']['code']]
        );
        $runner->defineExitModeAndCode($rawConfig['exit']['graceful'], $rawConfig['exit']['code']);

        $runner->doRun((int) $maxRun, $threadNumber);

        return self::SUCCESS;
    }
}
