<?php

declare(strict_types=1);
/**
 * @copy Win32Service (c) 2019
 * Added by : macintoshplus at 19/02/19 13:59
 */

namespace Win32ServiceBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Win32Service\Model\ServiceInformations;
use Win32Service\Service\ServiceStateManager;
use Win32ServiceBundle\Service\ServiceConfigurationManager;

#[AsCommand(name: 'win32service:action')]
class ActionServiceCommand extends Command
{
    public const ALL_SERVICE = 'All';

    public function __construct(private ServiceConfigurationManager $serviceConfigurationManager)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Send the action at all service');
        $this->addArgument('control', InputArgument::REQUIRED, 'The action you want');
        $this->addOption(
            'service-name',
            's',
            InputOption::VALUE_REQUIRED,
            'Send the controle to the service with service_id. The value must be equal to the configuration.',
            self::ALL_SERVICE
        );
        $this->addOption(
            'custom-action',
            'c',
            InputOption::VALUE_REQUIRED,
            'The custom control send to the service.',
            null
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceToAction = $input->getOption('service-name');
        $customAction = $input->getOption('custom-action');
        $action = $input->getArgument('control');

        $adminService = new ServiceStateManager();

        $actions = ['start', 'stop', 'pause', 'continue', 'custom'];
        if (!\in_array($action, $actions)) {
            throw new \InvalidArgumentException('The value of action argument is invalid. Valid values : '.implode(', ', $actions));
        }

        if ($action === 'custom' && ($customAction < 128 || $customAction > 255)) {
            throw new \InvalidArgumentException('The custom control value must be between 128 and 255');
        }

        if ($serviceToAction !== self::ALL_SERVICE) {
            $serviceInfos = $this->serviceConfigurationManager->getServiceConfiguration($serviceToAction);

            $this->sendAction($adminService, $action, $serviceInfos, $customAction);
            $output->writeln('Sending control to <info>'.$serviceInfos->serviceId().'</info> : OK');

            return self::SUCCESS;
        }

        $nbService = 0;
        foreach ($this->serviceConfigurationManager->getFullServiceList() as $serviceInfos) {
            try {
                ++$nbService;
                $this->sendAction($adminService, $action, $serviceInfos, $customAction);
                $output->writeln('Sending control to <info>'.$serviceInfos->serviceId().'</info> : OK');
            } catch (\Exception $e) {
                $output->writeln('<error> Error : '.$serviceInfos->serviceId().'('.$e->getCode().') '.$e->getMessage().' </error>');
            }
        }

        if ($nbService === 0) {
            $output->writeln('<info>No signal sent</info>');

            return self::FAILURE;
        }

        $output->writeln(sprintf('Signal sent to <info>%d</info> service(s)', $nbService));

        return self::SUCCESS;
    }

    private function sendAction(
        ServiceStateManager $adminService,
        string $action,
        ServiceInformations $serviceInfos,
        int $customAction
    ): void {
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
    }
}
