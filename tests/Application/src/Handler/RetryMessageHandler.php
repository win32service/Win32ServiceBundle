<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\Application\Handler;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Win32ServiceBundle\Tests\Application\Event\TestRetryMessage;

#[AsMessageHandler(fromTransport: 'async')]
final class RetryMessageHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(TestRetryMessage $message): void
    {
        $this->logger->info('Retry Message');
        throw new RecoverableMessageHandlingException('Retry Message');
    }
}
