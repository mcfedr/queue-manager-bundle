<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Manager;

use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Topic;
use Mcfedr\QueueManagerBundle\Exception\NoSuchJobException;
use Mcfedr\QueueManagerBundle\Manager\PubSubQueueManager;
use Mcfedr\QueueManagerBundle\Queue\PubSubJob;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PubSubQueueManagerTest extends TestCase
{
    /**
     * @var PubSubQueueManager
     */
    private $manager;

    /**
     * @var MockObject|PubSubClient
     */
    private $pubSubClient;

    /**
     * @var MockObject
     */
    private $pubSubClientTopic;

    protected function setUp(): void
    {
        $this->pubSubClient = $this
            ->getMockBuilder(PubSubClient::class)
            ->disableOriginalConstructor()
            ->setMethods(['topic'])
            ->getMock()
        ;

        $this->pubSubClientTopic = $this
            ->getMockBuilder(Topic::class)
            ->disableOriginalConstructor()
            ->setMethods(['publish'])
            ->getMock()
        ;

        $this->pubSubClient->expects(static::any())
            ->method('topic')
            ->with('projects/project/topics/test-topic')
            ->willReturn($this->pubSubClientTopic)
        ;

        $this->manager = new PubSubQueueManager($this->pubSubClient, [
            'default_subscription' => 'test_sub',
            'default_topic' => 'projects/project/topics/test-topic',
            'topics' => [],
        ]);
    }

    public function testPut(): void
    {
        $job = new PubSubJob('test_worker', [], null, null, 0);

        $this->pubSubClientTopic
            ->expects(static::once())
            ->method('publish')
            ->with(['data' => $job->getMessageBody()])
            ->willReturn(['messageIds' => ['650887938849995']])
        ;

        $job = $this->manager->put('test_worker');

        static::assertSame('test_worker', $job->getName());
    }

    public function testDelete(): void
    {
        $job = new PubSubJob('test_worker', [], null, null, 0);
        $this->expectException(NoSuchJobException::class);
        $this->manager->delete($job);
    }
}
