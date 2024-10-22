<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\Application\Handler;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Win32ServiceBundle\Tests\Application\Event\TestTimeLimitMessage;

#[AsMessageHandler(fromTransport: 'async')]
final class TimeLimitMessageHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(TestTimeLimitMessage $message): void
    {
        $this->logger->info('Time Limit Message : '.$message->durationInSeconds);
        sleep($message->durationInSeconds);
    }
}
