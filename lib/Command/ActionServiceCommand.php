<?php
/**
 * @copy Win32Service (c) 2019
 * Added by : macintoshplus at 19/02/19 13:59
 */

namespace Win32ServiceBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Win32Service\Model\ServiceIdentifier;
use Win32Service\Service\ServiceStateManager;

class ActionServiceCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'win32service:action-services';

    const ALL_SERVICE = 'All';

    /**
     * @var array
     */
    private $config;

    protected function configure()
    {
        $this->setDescription("Send the action at all service");
        $this->addArgument('control', InputArgument::REQUIRED, "The action you want", 'start');
        $this->addOption('service-name', 's', InputOption::VALUE_REQUIRED, 'Send the controle to the service with service_id. The value must be equal to the configuration.', self::ALL_SERVICE);
        $this->addOption('custom-action', 'c', InputOption::VALUE_REQUIRED, 'The custom control send to the service.', null);
    }

    /**
     * @param array $config
     *
     */
    public function defineBundleConfig(array $config) {
        $this->config = $config;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->config === null) {
            throw new \Exception('The configuration of win32Service is not defined into command');
        }

        $serviceToRegister = $input->getOption('service-name');
        $customAction = $input->getOption('custom-action');
        $action = $input->getArgument('control');

        $actions = ['start', 'stop', 'pause', 'continue', 'custom'];
        if (!in_array($action, $actions)) {
            throw new \InvalidArgumentException('The value of action argument is invalid. Valid values : '.implode(', ', $actions));
        }

        if ($action === 'custom' && ($customAction < 128 || $customAction > 255)) {
            throw new \InvalidArgumentException("The custom control value must be between 128 and 255");
        }

        $services = $this->config['services'];

        $adminService = new ServiceStateManager();

        $nbService = 0;
        foreach ($services as $service) {
            if ($serviceToRegister !== self::ALL_SERVICE && $serviceToRegister !== $service['service_id']) {
                continue;
            }
            $threadNumber = $service['thread_count'];

            for ($i = 0; $i < $threadNumber; $i++) {
                $nbService++;
                //Init the service informations
                $serviceInfos = ServiceIdentifier::identify(sprintf($service['service_id'], $i), $service['machine']);

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
                } catch (\Exception $e) {
                    $output->writeln('<error> Error : ' . $serviceInfos->serviceId() . '(' . $e->getCode() . ') ' . $e->getMessage() . ' </error>');
                }
            }
        }

        if ($nbService === 0) {
            $output->writeln('<info>No signal sent</info>');
            return;
        }

        $output->writeln(sprintf('Signal sent to <info>%d</info> service(s)', $nbService));
    }
}
