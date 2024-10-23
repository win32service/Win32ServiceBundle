<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\Application\Handler;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Win32ServiceBundle\Tests\Application\Event\TestFailedMessage;

#[AsMessageHandler(fromTransport: 'async')]
final class FailMessageHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(TestFailedMessage $message): void
    {
        $this->logger->info('Failed Message');
        throw new \LogicException('Fail to process');
    }
}
