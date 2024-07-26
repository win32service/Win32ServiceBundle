<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\Application\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Win32ServiceBundle\Tests\Application\Event\TestMessage;

#[AsCommand('test:send-message', 'Send a Test Message')]
final class SendMessageCommand extends Command
{
    public function __construct(private MessageBusInterface $messageBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('message', InputArgument::REQUIRED, 'Your test message');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $message = $input->getArgument('message');

        $this->messageBus->dispatch(new TestMessage($message));

        return self::SUCCESS;
    }
}
