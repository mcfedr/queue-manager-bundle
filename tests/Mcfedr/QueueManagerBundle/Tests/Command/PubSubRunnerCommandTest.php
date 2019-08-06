<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Command;

use Google\Cloud\PubSub\Message;
use Google\Cloud\PubSub\PubSubClient;
use Google\Cloud\PubSub\Subscription;
use Mcfedr\QueueManagerBundle\Command\PubSubRunnerCommand;
use Mcfedr\QueueManagerBundle\Queue\JobBatch;
use Mcfedr\QueueManagerBundle\Queue\PubSubJob;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
final class PubSubRunnerCommandTest extends TestCase
{
    /**
     * @var PubSubRunnerMock
     */
    private $command;

    /**
     * @var MockObject|PubSubClient
     */
    private $pubSubClient;

    /**
     * @var JobExecutor|MockObject
     */
    private $jobExecutor;

    /**
     * @var MockObject
     */
    private $pubSubClientSubscription;

    protected function setUp(): void
    {
        $this->pubSubClient = $this
            ->getMockBuilder(PubSubClient::class)
            ->disableOriginalConstructor()
            ->setMethods(['subscription'])
            ->getMock()
        ;

        $this->pubSubClientSubscription = $this
            ->getMockBuilder(Subscription::class)
            ->disableOriginalConstructor()
            ->setMethods(['pull', 'acknowledgeBatch', 'acknowledge'])
            ->getMock()
        ;

        $this->pubSubClient->expects(static::once())
            ->method('subscription')
            ->with('test_sub')
            ->willReturn($this->pubSubClientSubscription)
        ;

        $this->jobExecutor = $this
            ->getMockBuilder(JobExecutor::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->command = new PubSubRunnerMock($this->pubSubClient, 'gcp', [
            'default_subscription' => 'test_sub',
            'default_topic' => 'projects/project/topics/test-topic',
            'pub_sub_queues' => [],
        ], $this->jobExecutor);
    }

    public function testGetJobs(): void
    {
        $message = ['data' => '{"name":"job_name1","arguments":{"job":0},"retryCount":0}', 'messageId' => '1', 'publishTime' => '2019-07-26T08:39:44.558Z', 'attributes' => []];
        $message1 = ['data' => '{"name":"job_name2","arguments":{"job":1},"retryCount":0}', 'messageId' => '2', 'publishTime' => '2019-07-26T08:39:44.558Z', 'attributes' => []];
        $meta = ['ackId' => '1', 'subscription' => null];
        $meta1 = ['ackId' => '2', 'subscription' => null];

        $this->pubSubClientSubscription->expects(static::once())
            ->method('pull')
            ->with(['maxMessages' => 10])
            ->willReturn([(new Message($message, $meta)), (new Message($message1, $meta1))])
        ;

        $this->command->handleInput(new ArrayInput([], $this->command->getDefinition()));
        $batch = $this->command->getJobs();
        static::assertCount(2, $batch);
        $job = $batch->next();
        static::assertSame('job_name1', $job->getName());
        static::assertSame(['job' => 0], $job->getArguments());
        static::assertInstanceOf(PubSubJob::class, $job);
        static::assertSame('1', $job->getId());
        static::assertSame('test_sub', $batch->getOption('subscription'));
        static::assertSame('1', $job->getAckId());
        $job = $batch->next();
        static::assertSame('job_name2', $job->getName());
        static::assertSame(['job' => 1], $job->getArguments());
        static::assertInstanceOf(PubSubJob::class, $job);
        static::assertSame('2', $job->getId());
        static::assertSame('test_sub', $batch->getOption('subscription'));
        static::assertSame('2', $job->getAckId());
    }

    public function testGetJobsEmpty(): void
    {
        $this->pubSubClientSubscription->expects(static::once())
            ->method('pull')
            ->with(['maxMessages' => 10])
            ->willReturn([])
        ;

        $this->command->handleInput(new ArrayInput([], $this->command->getDefinition()));
        $batch = $this->command->getJobs();
        static::assertNull($batch);
    }

    public function testFinishJobs(): void
    {
        $pubSubMessage = (new Message(['messageId' => 1], ['ackId' => 2]));
        $job = new PubSubJob(
            'job_name1',
            ['first1', 'second1'],
            1,
            0,
            2
        );
        $this->pubSubClientSubscription->expects(static::once())
            ->method('acknowledgeBatch')
            ->with([$pubSubMessage])
        ;

        $this->command->finishJobs(new JobBatch([], [$job], [], [], ['subscription' => 'test_sub']));
    }
}

class PubSubRunnerMock extends PubSubRunnerCommand
{
    public function getJobs(): ?JobBatch
    {
        return parent::getJobs();
    }

    public function handleInput(InputInterface $input): void
    {
        parent::handleInput($input);
    }

    public function finishJobs(JobBatch $batch): void
    {
        parent::finishJobs($batch);
    }
}
