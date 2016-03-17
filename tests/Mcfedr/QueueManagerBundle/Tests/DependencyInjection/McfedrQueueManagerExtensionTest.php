<?php
/**
 * Created by mcfedr on 04/02/2016 10:42
 */


namespace Mcfedr\QueueManagerBundle\Tests\DependencyInjection;

use Mcfedr\QueueManagerBundle\Command\TestRunnerCommand;
use Mcfedr\QueueManagerBundle\Driver\TestQueueManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class McfedrQueueManagerExtensionTest extends WebTestCase
{
    public function testExtension()
    {
        $client = static::createClient();
        $this->assertTrue($client->getContainer()->has('mcfedr_queue_manager.default'));
        $service = $client->getContainer()->get('mcfedr_queue_manager.default');
        $this->assertInstanceOf(TestQueueManager::class, $service);
        $options = $service->getOptions();
        $this->assertCount(4, $options);
        $this->assertEquals('127.0.0.2', $options['host']);
        $this->assertEquals('mcfedr_queue', $options['default_queue']);
        $this->assertEquals('1234', $options['port']);
        $this->assertFalse($options['debug']);

        $parameterOptions = $client->getContainer()->getParameter('mcfedr_queue_manager.default.options');
        $this->assertCount(4, $parameterOptions);
        $this->assertEquals('127.0.0.2', $parameterOptions['host']);
        $this->assertEquals('mcfedr_queue', $parameterOptions['default_queue']);
        $this->assertEquals('1234', $parameterOptions['port']);
        $this->assertFalse($parameterOptions['debug']);

        $this->assertTrue($client->getContainer()->has('mcfedr_queue_manager.runner.default'));
        /** @var TestRunnerCommand $command */
        $command = $client->getContainer()->get('mcfedr_queue_manager.runner.default');
        $this->assertInstanceOf(TestRunnerCommand::class, $command);
        $this->assertEquals('mcfedr:queue:default-runner', $command->getName());
        $commandOptions = $command->getOptions();
        $this->assertCount(4, $commandOptions);
        $this->assertEquals('127.0.0.2', $commandOptions['host']);
        $this->assertEquals('mcfedr_queue', $commandOptions['default_queue']);
        $this->assertEquals('1234', $commandOptions['port']);
        $this->assertFalse($commandOptions['debug']);
    }
}
