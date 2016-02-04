<?php
/**
 * Created by mcfedr on 04/02/2016 10:42
 */


namespace Mcfedr\QueueManagerBundle\Tests\DependencyInjection;

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
    }
}
