<?php

declare(strict_types=1);
/**
 * @copy Win32Service (c) 2019
 * Added by : macintoshplus at 19/02/19 15:59
 */

namespace Win32ServiceBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Win32Service\Service\ServiceAdminManager;
use Win32ServiceBundle\Service\ServiceConfigurationManager;

#[AsCommand(name: 'win32service:unregister')]
class UnregisterServiceCommand extends Command
{
    public const ALL_SERVICE = 'All';

    public function __construct(private ServiceConfigurationManager $serviceConfigurationManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Unregister all service into Windows Service Manager');
        $this->addOption(
            'service-name',
            's',
            InputOption::VALUE_REQUIRED,
            'Register the service with service_id. The value must be equal to the configuration.',
            self::ALL_SERVICE
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $serviceToUnregister = $input->getOption('service-name');
        $adminService = new ServiceAdminManager();

        if ($serviceToUnregister !== self::ALL_SERVICE) {
            $serviceInfos = $this->serviceConfigurationManager->getServiceInformations($serviceToUnregister);

            $adminService->unregisterService($serviceInfos);
            $output->writeln('Unregistration success for <info>'.$serviceInfos->serviceId().'</info>');

            return self::SUCCESS;
        }

        $nbService = 0;
        foreach ($this->serviceConfigurationManager->getFullServiceList() as $serviceInfos) {
            try {
                $adminService->unregisterService($serviceInfos);
                ++$nbService;
                $output->writeln('Unregistration success for <info>'.$serviceInfos->serviceId().'</info>');
            } catch (\Exception $e) {
                $output->writeln('<error> Error : '.$serviceInfos->serviceId().'('.$e->getCode().') '.$e->getMessage().' </error>');
            }
        }

        if ($nbService === 0) {
            $output->writeln('<info>No service unregistred</info>');

            return self::FAILURE;
        }

        $output->writeln(sprintf('<info>%d</info> service(s) processed', $nbService));

        return self::SUCCESS;
    }
}
