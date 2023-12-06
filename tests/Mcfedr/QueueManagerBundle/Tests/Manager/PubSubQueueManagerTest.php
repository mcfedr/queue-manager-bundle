<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Manager;

use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Topic;
use Mcfedr\QueueManagerBundle\Exception\NoSuchJobException;
use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
use Mcfedr\QueueManagerBundle\Manager\PubSubQueueManager;
use Mcfedr\QueueManagerBundle\Queue\Job;
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

        $this->pubSubClient->expects(self::any())
            ->method('topic')
            ->with('projects/project/topics/test-topic')
            ->willReturn($this->pubSubClientTopic)
        ;

        $this->manager = new PubSubQueueManager($this->pubSubClient, [
            'default_subscription' => 'test_sub',
            'default_topic' => 'projects/project/topics/test-topic',
            'pub_sub_queues' => [],
        ]);
    }

    public function testPut(): void
    {
        $job = new PubSubJob('test_worker', [], null, 0);

        $this->pubSubClientTopic
            ->expects(self::once())
            ->method('publish')
            ->with(['data' => $job->getMessageBody()])
            ->willReturn(['messageIds' => ['650887938849995']])
        ;

        $job = $this->manager->put('test_worker');

        self::assertSame('test_worker', $job->getName());
    }

    public function testDelete(): void
    {
        $job = new PubSubJob('test_worker', [], null, 0);
        $this->expectException(NoSuchJobException::class);
        $this->manager->delete($job);
    }

    public function testDeleteOther(): void
    {
        $this->expectException(WrongJobException::class);
        $this->manager->delete($this->getMockBuilder(Job::class)->getMock());
    }
}
