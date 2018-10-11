<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\DependencyInjection;

use Mcfedr\QueueManagerBundle\Command\TestRunnerCommand;
use Mcfedr\QueueManagerBundle\Driver\TestQueueManager;
use Mcfedr\QueueManagerBundle\Manager\QueueManagerRegistry;
use Mcfedr\QueueManagerBundle\Subscriber\DoctrineResetSubscriber;
use Mcfedr\QueueManagerBundle\Subscriber\SwiftMailerSubscriber;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class McfedrQueueManagerExtensionTest extends WebTestCase
{
    public function testExtension()
    {
        $client = static::createClient();
        $this->assertTrue($client->getContainer()->has(TestQueueManager::class));
        $service = $client->getContainer()->get(TestQueueManager::class);
        $this->assertInstanceOf(TestQueueManager::class, $service);
        $options = $service->getOptions();
        $this->assertCount(6, $options);
        $this->assertEquals('127.0.0.2', $options['host']);
        $this->assertEquals('mcfedr_queue', $options['default_queue']);
        $this->assertEquals('1234', $options['port']);
        $this->assertFalse($options['debug']);
        $this->assertEquals(3, $options['retry_limit']);
        $this->assertEquals(5, $options['sleep_seconds']);

        //Backwards compatibility
        $this->assertTrue($client->getContainer()->has('mcfedr_queue_manager.default'));
        $service = $client->getContainer()->get('mcfedr_queue_manager.default');
        $this->assertInstanceOf(TestQueueManager::class, $service);

        $parameterOptions = $client->getContainer()->getParameter('mcfedr_queue_manager.default.options');
        $this->assertCount(6, $parameterOptions);
        $this->assertEquals('127.0.0.2', $parameterOptions['host']);
        $this->assertEquals('mcfedr_queue', $parameterOptions['default_queue']);
        $this->assertEquals('1234', $parameterOptions['port']);
        $this->assertFalse($parameterOptions['debug']);
        $this->assertEquals(3, $parameterOptions['retry_limit']);
        $this->assertEquals(5, $parameterOptions['sleep_seconds']);

        $this->assertTrue($client->getContainer()->has(TestRunnerCommand::class));
        /** @var TestRunnerCommand $command */
        $command = $client->getContainer()->get(TestRunnerCommand::class);
        $this->assertInstanceOf(TestRunnerCommand::class, $command);
        $this->assertEquals('mcfedr:queue:default-runner', $command->getName());
        $commandOptions = $command->getOptions();
        $this->assertCount(6, $commandOptions);
        $this->assertEquals('127.0.0.2', $commandOptions['host']);
        $this->assertEquals('mcfedr_queue', $commandOptions['default_queue']);
        $this->assertEquals('1234', $commandOptions['port']);
        $this->assertFalse($commandOptions['debug']);
        $this->assertEquals(3, $commandOptions['retry_limit']);
        $this->assertEquals(5, $commandOptions['sleep_seconds']);

        $this->assertTrue($client->getContainer()->has(QueueManagerRegistry::class));
        //Backwards compatibility
        $this->assertTrue($client->getContainer()->has('mcfedr_queue_manager.registry'));

        $this->assertTrue($client->getContainer()->has(DoctrineResetSubscriber::class));

        $this->assertTrue($client->getContainer()->has(SwiftMailerSubscriber::class));
    }
}
