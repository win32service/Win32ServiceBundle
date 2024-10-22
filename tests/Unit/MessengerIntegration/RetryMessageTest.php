<?php

declare(strict_types=1);

namespace Win32ServiceBundle\Tests\Unit\MessengerIntegration;

require_once \dirname(__DIR__, 2).'/win32service_mock_function.php';

use Doctrine\DBAL\Driver\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Win32Service\Model\ServiceIdentifier;
use Win32ServiceBundle\Model\MessengerServiceRunner;
use Win32ServiceBundle\Service\RunnerManager;
use Win32ServiceBundle\Service\ServiceConfigurationManager;
use Win32ServiceBundle\Tests\Application\Event\TestRetryMessage;

final class RetryMessageTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        $container = static::getContainer();

        /** @var Connection $connexion */
        $connexion = $container->get('doctrine.dbal.default_connection');
        $connexion->rollBack();
    }

    public function testRetryMessage(): void
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
        $messengerBus->dispatch(new TestRetryMessage());

        $c = $connexion->query('SELECT count(*) FROM messenger_messages WHERE queue_name = \'default\'');

        $this->assertSame(1, (int) $c->fetchOne());

        $runnerManager = $container->get(RunnerManager::class);
        $serviceConfigurationManager = $container->get(ServiceConfigurationManager::class);
        /** @var MessengerServiceRunner $runner */
        $runner = $runnerManager->getRunner($serviceConfigurationManager->getRunnerAliasForServiceId($serviceName));
        $runner->setServiceId(new ServiceIdentifier($serviceName));
        $runner->doRun(1, 0);

        $c = $connexion->query('SELECT count(*) FROM messenger_messages WHERE queue_name = \'default\'');

        $this->assertSame(2, (int) $c->fetchOne());
    }
}
