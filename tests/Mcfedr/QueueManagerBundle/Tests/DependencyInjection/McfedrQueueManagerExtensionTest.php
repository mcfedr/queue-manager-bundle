<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\DependencyInjection;

use Mcfedr\QueueManagerBundle\Driver\TestQueueManager;
use Mcfedr\QueueManagerBundle\Manager\QueueManagerRegistry;
use Mcfedr\QueueManagerBundle\RunnerCommand\TestRunnerCommand;
use Mcfedr\QueueManagerBundle\Subscriber\DoctrineResetSubscriber;
use Mcfedr\QueueManagerBundle\Subscriber\SwiftMailerSubscriber;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @internal
 */
final class McfedrQueueManagerExtensionTest extends WebTestCase
{
    public function testExtension(): void
    {
        $client = static::createClient();
        static::assertTrue($client->getContainer()->has(TestQueueManager::class));
        $service = $client->getContainer()->get(TestQueueManager::class);
        static::assertInstanceOf(TestQueueManager::class, $service);
        $options = $service->getOptions();
        static::assertSame('127.0.0.2', $options['host']);
        static::assertSame('mcfedr_queue', $options['default_queue']);
        static::assertSame(1234, $options['port']);
        static::assertSame(3, $options['retry_limit']);
        static::assertSame(5, $options['sleep_seconds']);

        //Backwards compatibility
        static::assertTrue($client->getContainer()->has('mcfedr_queue_manager.default'));
        $service = $client->getContainer()->get('mcfedr_queue_manager.default');
        static::assertInstanceOf(TestQueueManager::class, $service);

        $parameterOptions = $client->getContainer()->getParameter('mcfedr_queue_manager.default.options');
        static::assertSame('127.0.0.2', $parameterOptions['host']);
        static::assertSame('mcfedr_queue', $parameterOptions['default_queue']);
        static::assertSame(1234, $parameterOptions['port']);
        static::assertSame(3, $parameterOptions['retry_limit']);
        static::assertSame(5, $parameterOptions['sleep_seconds']);

        static::assertTrue($client->getContainer()->has(TestRunnerCommand::class));
        /** @var TestRunnerCommand $command */
        $command = $client->getContainer()->get(TestRunnerCommand::class);
        static::assertInstanceOf(TestRunnerCommand::class, $command);
        static::assertSame('mcfedr:queue:default-runner', $command->getName());
        $commandOptions = $command->getOptions();
        static::assertSame('127.0.0.2', $commandOptions['host']);
        static::assertSame('mcfedr_queue', $commandOptions['default_queue']);
        static::assertSame(1234, $commandOptions['port']);
        static::assertSame(3, $commandOptions['retry_limit']);
        static::assertSame(5, $commandOptions['sleep_seconds']);

        static::assertTrue($client->getContainer()->has(QueueManagerRegistry::class));
        //Backwards compatibility
        static::assertTrue($client->getContainer()->has('mcfedr_queue_manager.registry'));

        static::assertTrue($client->getContainer()->has(DoctrineResetSubscriber::class));

        static::assertTrue($client->getContainer()->has(SwiftMailerSubscriber::class));
    }
}
