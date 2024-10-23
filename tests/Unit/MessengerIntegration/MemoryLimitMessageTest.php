<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\Unit\MessengerIntegration;

require_once \dirname(__DIR__, 2).'/Win32serviceState.php';

use Doctrine\DBAL\Driver\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Win32Service\Model\AbstractServiceRunner;
use Win32Service\Model\ServiceIdentifier;
use Win32Service\Model\Win32serviceState;
use Win32ServiceBundle\Model\MessengerServiceRunner;
use Win32ServiceBundle\Service\RunnerManager;
use Win32ServiceBundle\Service\ServiceConfigurationManager;
use Win32ServiceBundle\Tests\Application\Event\TestMemoryLimitMessage;
use Win32ServiceBundle\Tests\Application\Event\TestMessage;

final class MemoryLimitMessageTest extends KernelTestCase
{
    protected function setUp(): void
    {
        Win32serviceState::reset();
    }

    protected function tearDown(): void
    {
        $container = static::getContainer();

        /** @var Connection $connexion */
        $connexion = $container->get('doctrine.dbal.default_connection');
        $connexion->rollBack();
    }

    public function testMemoryLimitMessage(): void
    {
        $serviceName = 'win32service.demo.messenger.async.0';
        self::bootKernel();
        $container = static::getContainer();

        /** @var Connection $connexion */
        $connexion = $container->get('doctrine.dbal.default_connection');
        $connexion->beginTransaction();
        $connexion->query('DELETE FROM messenger_messages');
        /** @var MessageBusInterface $messengerBus */
        $messengerBus = $container->get('messenger.bus.default');
        $messengerBus->dispatch(new TestMemoryLimitMessage( /* 129 Mio */1024 * 1024 * 129));
        $messengerBus->dispatch(new TestMessage('message 1'));

        $c = $connexion->query('SELECT count(*) FROM messenger_messages WHERE queue_name = \'default\'');

        $this->assertSame(2, (int) $c->fetchOne());

        $runnerManager = $container->get(RunnerManager::class);
        $serviceConfigurationManager = $container->get(ServiceConfigurationManager::class);
        /** @var MessengerServiceRunner $runner */
        $runner = $runnerManager->getRunner($serviceConfigurationManager->getRunnerAliasForServiceId($serviceName));
        $runner->setServiceId(new ServiceIdentifier($serviceName));
        $runner->doRun(5, 0);

        $rClass = new \ReflectionClass(AbstractServiceRunner::class);
        $value = $rClass->getProperty('stopRequested');
        $value->setAccessible(true);

        $this->assertTrue($value->getValue($runner));

        $c = $connexion->query('SELECT count(*) FROM messenger_messages WHERE queue_name = \'default\' AND delivered_at IS NULL');

        $this->assertSame(1, (int) $c->fetchOne());

        $c = $connexion->query('SELECT count(*) FROM messenger_messages WHERE queue_name = \'default\' AND delivered_at IS NOT NULL');

        $this->assertSame(1, (int) $c->fetchOne());
    }
}
