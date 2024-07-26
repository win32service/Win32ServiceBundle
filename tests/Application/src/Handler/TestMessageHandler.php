<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\Application\Handler;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Win32ServiceBundle\Tests\Application\Event\TestMessage;

#[AsMessageHandler(fromTransport: 'async')]
final class TestMessageHandler
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(TestMessage $message): void
    {
        $this->logger->info(__METHOD__.' - message : '.$message->message);
    }
}
