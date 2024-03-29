<?php
/**
 * @copyright Win32Service (c) 2019
 * Added by : macintoshplus at 19/02/19 21:18
 */

namespace Win32ServiceBundle\Command;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Win32Service\Model\ServiceIdentifier;
use Win32Service\Model\RunnerServiceInterface;
use Win32ServiceBundle\Logger\ThreadNumberEvent;
use Win32ServiceBundle\Service\RunnerManager;
use const atoum\atoum\phar\name;

#[AsCommand(name: 'win32service:run')]
class ExecuteServiceCommand extends Command
{
    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    private ?RunnerManager $service = null;

    private ?EventDispatcherInterface $eventDispatcher = null;

    protected function configure()
    {
        $this->setDescription("Run the service");
        $this->addArgument('service-name', InputArgument::REQUIRED, 'The service name.');
        $this->addArgument('thread', InputArgument::REQUIRED, 'Thread number');
        $this->addOption('max-run', 'r', InputOption::VALUE_REQUIRED, 'Set the max run');
    }

    public function defineBundleConfig(array $config) {
        $this->config = $config;

    }

    public function setService(RunnerManager $service) {
        $this->service = $service;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher) {
        $this->eventDispatcher = $eventDispatcher;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->config === []) {
            throw new Exception('The configuration of win32Service is not defined into command');
        }
        if ($this->service === null) {
            throw new Exception('The service runner manager is not defined into command');
        }

        $serviceName = $input->getArgument('service-name');
        $threadNumber = $input->getArgument('thread');
        $maxRun = $input->getOption('max-run');

        $infos=$this->getServiceInformation($serviceName, $threadNumber);
        if ($infos === null) {
            throw new Exception(sprintf('The information for service %s is not found', $serviceName));
        }

        if ($maxRun === null) {
            $maxRun = $infos['run_max'];
        }

        $runner = $this->service->getRunner($infos['service_id']);
        if ($runner === null) {
            throw new Exception(sprintf('The runner for service "%1$s" is not found. Call method \'add\' on the RunnerManager with the runner instance and the alias "%1$s".', $infos['service_id']));
        }

        if ($this->eventDispatcher !== null) {
            $event = new ThreadNumberEvent($threadNumber);
            $this->eventDispatcher->dispatch($event,ThreadNumberEvent::NAME);
        }

        $runner->setServiceId(ServiceIdentifier::identify($serviceName, $infos['machine']));

        $runner->defineExitModeAndCode($infos['exit']['graceful'], $infos['exit']['code']);

        $runner->doRun(intval($maxRun), $threadNumber);

        return self::SUCCESS;
    }

    private function getServiceInformation(string $serviceToRun, $threadNumber) {
        foreach ($this->config['services'] as $service) {
            if ($serviceToRun === sprintf($service['service_id'], $threadNumber)) {
                return $service;
            }
        }
        return null;
    }
}
