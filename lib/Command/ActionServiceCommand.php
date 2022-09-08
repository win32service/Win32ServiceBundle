<?php
/**
 * @copy Win32Service (c) 2019
 * Added by : macintoshplus at 19/02/19 13:59
 */

namespace Win32ServiceBundle\Command;

use Exception;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Win32Service\Model\ServiceIdentifier;
use Win32Service\Service\ServiceStateManager;
use const atoum\atoum\phar\name;

#[AsCommand(name: 'win32service:action')]
class ActionServiceCommand extends Command
{
    const ALL_SERVICE = 'All';

    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    protected function configure()
    {
        $this->setDescription("Send the action at all service");
        $this->addArgument('control', InputArgument::REQUIRED, "The action you want");
        $this->addOption('service-name', 's', InputOption::VALUE_REQUIRED, 'Send the controle to the service with service_id. The value must be equal to the configuration.', self::ALL_SERVICE);
        $this->addOption('custom-action', 'c', InputOption::VALUE_REQUIRED, 'The custom control send to the service.', null);
    }

    public function defineBundleConfig(array $config) {
        $this->config = $config;

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->config === []) {
            throw new Exception('The configuration of win32Service is not defined into command');
        }

        $serviceToAction = $input->getOption('service-name');
        $customAction = $input->getOption('custom-action');
        $action = $input->getArgument('control');

        $actions = ['start', 'stop', 'pause', 'continue', 'custom'];
        if (!in_array($action, $actions)) {
            throw new InvalidArgumentException('The value of action argument is invalid. Valid values : '.implode(', ', $actions));
        }

        if ($action === 'custom' && ($customAction < 128 || $customAction > 255)) {
            throw new InvalidArgumentException("The custom control value must be between 128 and 255");
        }

        $services = $this->config['services'];

        $adminService = new ServiceStateManager();

        $nbService = 0;
        foreach ($services as $service) {

            $threadNumber = $service['thread_count'];

            for ($i = 0; $i < $threadNumber; $i++) {
                $serviceThreadId = sprintf($service['service_id'], $i);
                if ($serviceToAction !== self::ALL_SERVICE && $serviceToAction !== $service['service_id'] && $serviceThreadId !== $serviceToAction) {
                    continue;
                }

                $nbService++;
                //Init the service informations
                $serviceInfos = ServiceIdentifier::identify($serviceThreadId, $service['machine']);

                try {
                    switch ($action) {
                        case 'start':
                            $adminService->startService($serviceInfos);
                            break;
                        case 'stop':
                            $adminService->stopService($serviceInfos);
                            break;
                        case 'pause':
                            $adminService->pauseService($serviceInfos);
                            break;
                        case 'continue':
                            $adminService->continueService($serviceInfos);
                            break;
                        case 'custom':
                            $adminService->sendCustomControl($serviceInfos, $customAction);
                            break;
                        default:
                            break;
                    }
                    $output->writeln('Sending control to <info>' . $serviceInfos->serviceId() . '</info> : OK');
                } catch (Exception $e) {
                    $output->writeln('<error> Error : ' . $serviceInfos->serviceId() . '(' . $e->getCode() . ') ' . $e->getMessage() . ' </error>');
                }
            }
        }

        if ($nbService === 0) {
            $output->writeln('<info>No signal sent</info>');
            return self::FAILURE;
        }

        $output->writeln(sprintf('Signal sent to <info>%d</info> service(s)', $nbService));
        return self::SUCCESS;
    }
}
