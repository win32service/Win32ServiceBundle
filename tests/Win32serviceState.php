<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Model;

function win32_start_service_ctrl_dispatcher(string $serviceName): bool
{
    return Win32serviceState::getInstance()->setServiceName($serviceName);
}

function win32_set_service_status(int $newState): void
{
    Win32serviceState::getInstance()->changeState($newState);
}

function win32_get_last_control_message(): int
{
    return Win32serviceState::getInstance()->getLastControlMessage();
}

class Win32serviceState
{
    private static ?self $instance = null;

    private int $state = WIN32_SERVICE_STOPPED;

    private ?string $serviceName = null;

    private int $lastControlMessage = WIN32_SERVICE_CONTROL_INTERROGATE;

    public static function getInstance(): Win32serviceState
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function setServiceName(string $serviceName): bool
    {
        if ($this->serviceName === null) {
            $this->serviceName = $serviceName;

            return true;
        }

        return false;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function changeState(int $newState): void
    {
        $this->state = $newState;
    }

    public function getState(): int
    {
        return $this->state;
    }

    public function getLastControlMessage(): int
    {
        return $this->lastControlMessage;
    }

    public function setLastControlMessage(int $newControlMessage): void
    {
        $this->lastControlMessage = $newControlMessage;
    }
}
