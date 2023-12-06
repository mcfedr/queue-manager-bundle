<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\DependencyInjection;

use Mcfedr\QueueManagerBundle\Driver\TestQueueManager;
use Mcfedr\QueueManagerBundle\Manager\QueueManagerRegistry;
use Mcfedr\QueueManagerBundle\RunnerCommand\TestRunnerCommand;
use Mcfedr\QueueManagerBundle\Subscriber\DoctrineResetSubscriber;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @internal
 */
final class McfedrQueueManagerExtensionTest extends WebTestCase
{
    public function testExtension(): void
    {
        $client = self::createClient();
        self::assertTrue($client->getContainer()->has(TestQueueManager::class));
        $service = $client->getContainer()->get(TestQueueManager::class);
        self::assertInstanceOf(TestQueueManager::class, $service);
        $options = $service->getOptions();
        self::assertSame('127.0.0.2', $options['host']);
        self::assertSame('mcfedr_queue', $options['default_queue']);
        self::assertSame(1234, $options['port']);
        self::assertSame(3, $options['retry_limit']);
        self::assertSame(5, $options['sleep_seconds']);

        // Backwards compatibility
        self::assertTrue($client->getContainer()->has('mcfedr_queue_manager.default'));
        $service = $client->getContainer()->get('mcfedr_queue_manager.default');
        self::assertInstanceOf(TestQueueManager::class, $service);

        $parameterOptions = $client->getContainer()->getParameter('mcfedr_queue_manager.default.options');
        self::assertSame('127.0.0.2', $parameterOptions['host']);
        self::assertSame('mcfedr_queue', $parameterOptions['default_queue']);
        self::assertSame(1234, $parameterOptions['port']);
        self::assertSame(3, $parameterOptions['retry_limit']);
        self::assertSame(5, $parameterOptions['sleep_seconds']);

        self::assertTrue($client->getContainer()->has(TestRunnerCommand::class));

        /** @var TestRunnerCommand $command */
        $command = $client->getContainer()->get(TestRunnerCommand::class);
        self::assertInstanceOf(TestRunnerCommand::class, $command);
        self::assertSame('mcfedr:queue:default-runner', $command->getName());
        $commandOptions = $command->getOptions();
        self::assertSame('127.0.0.2', $commandOptions['host']);
        self::assertSame('mcfedr_queue', $commandOptions['default_queue']);
        self::assertSame(1234, $commandOptions['port']);
        self::assertSame(3, $commandOptions['retry_limit']);
        self::assertSame(5, $commandOptions['sleep_seconds']);

        self::assertTrue($client->getContainer()->has(QueueManagerRegistry::class));
        // Backwards compatibility
        self::assertTrue($client->getContainer()->has('mcfedr_queue_manager.registry'));

        // Default subscribers added
        self::assertTrue($client->getContainer()->has(DoctrineResetSubscriber::class));

        // Default managers added
        self::assertTrue($client->getContainer()->has('mcfedr_queue_manager.delay'));
        self::assertTrue($client->getContainer()->has('mcfedr_queue_manager.periodic'));
    }

    public function testExtensionNoDoctrine(): void
    {
        $client = self::createClient([
            'environment' => 'test_no_doctrine',
        ]);
        // Default subscribers not added
        self::assertFalse($client->getContainer()->has(DoctrineResetSubscriber::class));
        // Default managers not added
        self::assertFalse($client->getContainer()->has('mcfedr_queue_manager.delay'));
        self::assertFalse($client->getContainer()->has('mcfedr_queue_manager.periodic'));
    }
}
