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
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $projectRoot;

    protected function configure()
    {
        $this->setDescription("Register all service into Windows Service Manager");
        $this->addOption('service-name', 's', InputOption::VALUE_REQUIRED, 'Register the service with service_id. The value must be equal to the configuration.', self::ALL_SERVICE);
    }

    /**
     * @param array $config
     *
     */
    public function defineBundleConfig(array $config) {
        $this->config = $config;
    }

    /**
     * @param string $projectRoot
     * @required
     */
    public function defineProjectRoot(string $projectRoot) {
        $this->projectRoot = $projectRoot;
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
                $path = $service['script_path'];
                $args = sprintf($service['script_params'], $i);
                if ($path === null) {
                    $path = sprintf('%s\\bin\\console', $this->projectRoot);
                    $args = sprintf('win32service:run-service %s %d', sprintf($service['service_id'], $i), $i );
                }

                $serviceInfos = new ServiceInformations(
                    ServiceIdentifier::identify(sprintf($service['service_id'], $i), $service['machine']),
                    mb_convert_encoding(sprintf($service['displayed_name'], $i), $windowsLocalEncoding, 'UTF-8'),
                    mb_convert_encoding($service['description'], $windowsLocalEncoding, 'UTF-8'),
                    mb_convert_encoding($path, $windowsLocalEncoding, 'UTF-8'),
                    mb_convert_encoding($args, $windowsLocalEncoding, 'UTF-8')
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

                if (count($service['dependencies']) >0) {
                    $serviceInfos->defineDependencies($service['dependencies']);
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
