<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\Application\Handler;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Win32ServiceBundle\Tests\Application\Event\TestMemoryLimitMessage;

#[AsMessageHandler(fromTransport: 'async')]
final class MemoryLimitMessageHandler
{
    /** @var string Buffer to consume memory to stop service */
    private string $buffer = '';

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(TestMemoryLimitMessage $message): void
    {
        $this->logger->info('Memory Limit Message : '.$message->size);
        $this->buffer = str_repeat('-*+45defse', (int) ($message->size / 10));
    }
}
