<?php
/**
 * @copy Win32Service (c) 2019
 * Added by : macintoshplus at 19/02/19 15:59
 */

namespace Win32ServiceBundle\Command;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Win32Service\Model\ServiceIdentifier;
use Win32Service\Service\ServiceAdminManager;

#[AsCommand(name: 'win32service:unregister')]
class UnregisterServiceCommand extends Command
{
    const ALL_SERVICE = 'All';

    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    protected function configure()
    {
        $this->setDescription("Unregister all service into Windows Service Manager");
        $this->addOption('service-name', 's', InputOption::VALUE_REQUIRED,
            'Register the service with service_id. The value must be equal to the configuration.', self::ALL_SERVICE);
    }

    public function defineBundleConfig(array $config)
    {
        $this->config = $config;

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->config === []) {
            throw new Exception('The configuration of win32Service is not defined into command');
        }

        $serviceToRegister = $input->getOption('service-name');

        $services = $this->config['services'];

        $adminService = new ServiceAdminManager();

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
                    $adminService->unregisterService($serviceInfos);
                    $output->writeln('Unregistration success for <info>' . $serviceInfos->serviceId() . '</info>');
                } catch (Exception $e) {
                    $output->writeln('<error> Error : ' . $serviceInfos->serviceId() . '(' . $e->getCode() . ') ' . $e->getMessage() . ' </error>');
                }
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
