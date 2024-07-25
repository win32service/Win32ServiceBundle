<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Service;

use Win32Service\Model\ServiceIdentifier;
use Win32Service\Model\ServiceInformations;
use Win32ServiceBundle\Command\ExecuteServiceCommand;

final class ServiceConfigurationManager
{
    private array $serviceIds = [];
    private array $serviceIdsToRunnerAlias = [];

    public function __construct(private array $configuration)
    {
        if ($configuration === []) {
            throw new \Win32ServiceException('The configuration of win32Service is not defined');
        }

        $services = $configuration['services'];

        foreach ($services as $service) {
            $threadNumber = $service['thread_count'];
            $runnerAlias = $service['service_id'];
            $scriptParams = $service['script_params'];
            $scriptPath = $service['script_path'];

            for ($i = 0; $i < $threadNumber; ++$i) {
                $serviceThreadId = sprintf($runnerAlias, $i);

                $path = $service['script_path'];
                $args = sprintf($scriptParams, $i);

                if ($scriptPath === null) {
                    $path = realpath($_SERVER['PHP_SELF']);
                    $args = sprintf('%s %s %d', ExecuteServiceCommand::getDefaultName(), $serviceThreadId, $i);
                }

                $service['service_id'] = $serviceThreadId;
                $service['script_path'] = $path;
                $service['script_params'] = $args;

                if (isset($this->serviceIds[$serviceThreadId]) === true) {
                    throw new \Win32ServiceException(sprintf('The Win32Service "%s" is already defined. if the parameter "thread_count" is greater than 1, please add "%d" in "service_id" parameter. Otherwise, check if no other service have same name.', $serviceThreadId));
                }
                $this->serviceIds[$serviceThreadId] = $service;
                $this->serviceIdsToRunnerAlias[$serviceThreadId] = $runnerAlias;
            }
        }
    }

    /** @return \Generator<int, ServiceInformations> */
    public function getFullServiceList(): \Generator
    {
        foreach ($this->serviceIds as $serviceId => $service) {
            yield $this->getServiceConfiguration($serviceId);
        }
    }

    public function getServiceConfiguration(string $serviceId): ServiceInformations
    {
        if (isset($this->serviceIds[$serviceId]) === false) {
            throw new \Win32ServiceException(sprintf('The Win32Service "%s" is not defined.', $serviceId));
        }
        $service = $this->serviceIds[$serviceId];
        $windowsLocalEncoding = $this->configuration['windows_local_encoding'];

        $serviceInfos = new ServiceInformations(
            ServiceIdentifier::identify($serviceId, $service['machine']),
            mb_convert_encoding($service['displayed_name'], $windowsLocalEncoding, 'UTF-8'),
            mb_convert_encoding($service['description'], $windowsLocalEncoding, 'UTF-8'),
            mb_convert_encoding($service['script_path'], $windowsLocalEncoding, 'UTF-8'),
            mb_convert_encoding($service['script_params'], $windowsLocalEncoding, 'UTF-8')
        );

        $serviceInfos->defineIfStartIsDelayed($service['delayed_start']);

        $recovery = $service['recovery'];
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

        if (\count($service['dependencies']) > 0) {
            $serviceInfos->defineDependencies($service['dependencies']);
        }

        return $serviceInfos;
    }

    public function getRunnerAliasForServiceId(string $serviceId): string
    {
        return $this->serviceIdsToRunnerAlias[$serviceId] ?? throw new \Win32ServiceException('The Win32Service "'.$serviceId.'" have no alias defined.');
    }
}
