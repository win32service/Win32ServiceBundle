<?php
/**
 * @copy Win32Service (c) 2019
 * Added by : macintoshplus at 19/02/19 13:59
 */

namespace Win32ServiceBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Win32Service\Model\ServiceIdentifier;
use Win32Service\Model\ServiceInformations;
use Win32Service\Service\ServiceAdminManager;

class RegisterServiceCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'win32service:register-services';

    const ALL_SERVICE = 'All';

    /**
     * @var string
     */

    private $projectRoot;
    /**
     * @var array
     */
    private $config;

    protected function configure()
    {
        $this->setDescription("Register all service into Windows Service Manager");
        $this->addOption('service-name', 's', InputOption::VALUE_REQUIRED, 'Register the service with service_id. The value must be equal to the configuration.', self::ALL_SERVICE);
    }

    /**
     * @param string $project_root
     * @required
     */
    public function setProjectRoot(string $project_root) {
        $this->projectRoot = $project_root;
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

        $services = $this->config['services'];

        $adminService = new ServiceAdminManager();

        $windowsLocalEncoding = $this->config['windows_local_encoding'];

        $nbService = 0;
        foreach ($services as $service) {
            if ($serviceToRegister !== self::ALL_SERVICE && $serviceToRegister !== $service['service_id']) {
                continue;
            }
            $threadNumber = $service['thread_count'];

            for ($i = 0; $i < $threadNumber; $i++) {
                $nbService++;
                //Init the service informations
                $serviceInfos = new ServiceInformations(
                    ServiceIdentifier::identify(sprintf($service['service_id'], $i), $service['machine']),
                    mb_convert_encoding(sprintf($service['displayed_name'], $i), $windowsLocalEncoding, 'UTF-8'),
                    mb_convert_encoding($service['description'], $windowsLocalEncoding, 'UTF-8'),
                    mb_convert_encoding($service['script_path'], $windowsLocalEncoding, 'UTF-8'),
                    mb_convert_encoding(sprintf($service['script_params'], $i), $windowsLocalEncoding, 'UTF-8')
                );

                $serviceInfos->defineIfStartIsDelayed($service['delayed_start']);

                $recovery=$service['recovery'];
                $serviceInfos->defineRecoverySettings(
                    $recovery['delay'],
                    $recovery['enable'],
                    $recovery['action1'],
                    $recovery['action2'],
                    $recovery['action3'],
                    $recovery['reboot_msg'],
                    $recovery['command'],
                    $recovery['reset_period']
                );

                if ($service['user']['account'] !== null) {
                    $serviceInfos->defineUserService($service['user']['account'], $service['user']['password']);
                }

                try {
                    $adminService->registerService($serviceInfos);
                    $output->writeln('Registration success for <info>' . $serviceInfos->serviceId() . '</info>');
                } catch (\Exception $e) {
                    $output->writeln('<error> Error : ' . $serviceInfos->serviceId() . '(' . $e->getCode() . ') ' . $e->getMessage() . ' </error>');
                }
            }
        }

        if ($nbService === 0) {
            $output->writeln('<info>No service registred</info>');
            return;
        }

        $output->writeln(sprintf('<info>%d</info> service(s) processed', $nbService));
    }
}
