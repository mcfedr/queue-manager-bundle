<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Manager;

use Aws\Sqs\SqsClient;
use Mcfedr\QueueManagerBundle\Manager\SqsQueueManager;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\SqsJob;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class SqsQueueManagerTest extends TestCase
{
    /**
     * @var SqsQueueManager
     */
    private $manager;

    /**
     * @var MockObject|SqsClient
     */
    private $sqsClient;

    protected function setUp(): void
    {
        $this->sqsClient = $this
            ->getMockBuilder(SqsClient::class)
            ->disableOriginalConstructor()
            ->setMethods(['sendMessage'])
            ->getMock()
        ;

        $this->manager = new SqsQueueManager($this->sqsClient, [
            'default_url' => 'http://sqs.com',
            'queues' => [],
        ]);
    }

    public function testPut(): void
    {
        $this->sqsClient
            ->expects($this->once())
            ->method('sendMessage')
            ->with([
                'QueueUrl' => 'http://sqs.com',
                'MessageBody' => '{"name":"test_worker","arguments":[],"retryCount":0,"visibilityTimeout":null}',
            ])
        ;

        $job = $this->manager->put('test_worker');

        $this->assertSame('test_worker', $job->getName());
    }

    /**
     * @expectedException \Mcfedr\QueueManagerBundle\Exception\NoSuchJobException
     */
    public function testDelete(): void
    {
        $this->manager->delete(new SqsJob('test_worker', [], 0, 'queue.com', null, 0));
    }

    /**
     * @expectedException \Mcfedr\QueueManagerBundle\Exception\WrongJobException
     */
    public function testDeleteOther(): void
    {
        $this->manager->delete($this->getMockBuilder(Job::class)->getMock());
    }
}
