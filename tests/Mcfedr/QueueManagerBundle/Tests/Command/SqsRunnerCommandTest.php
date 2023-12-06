<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Command;

use Aws\Sqs\SqsClient;
use Mcfedr\QueueManagerBundle\Command\SqsRunnerCommand;
use Mcfedr\QueueManagerBundle\Queue\JobBatch;
use Mcfedr\QueueManagerBundle\Queue\SqsJob;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
final class SqsRunnerCommandTest extends TestCase
{
    /**
     * @var RunnerMock
     */
    private $command;

    /**
     * @var MockObject|SqsClient
     */
    private $sqsClient;

    /**
     * @var JobExecutor|MockObject
     */
    private $jobExecutor;

    protected function setUp(): void
    {
        $this->sqsClient = $this
            ->getMockBuilder(SqsClient::class)
            ->disableOriginalConstructor()
            ->setMethods(['sendMessageBatch', 'deleteMessageBatch', 'changeMessageVisibilityBatch', 'receiveMessage'])
            ->getMock()
        ;

        $this->jobExecutor = $this
            ->getMockBuilder(JobExecutor::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->command = new RunnerMock($this->sqsClient, 'sqs', [
            'default_url' => 'http://sqs.com',
            'queues' => [],
        ], $this->jobExecutor);
    }

    public function testGetJobs(): void
    {
        $this->sqsClient->expects(self::once())
            ->method('receiveMessage')
            ->with([
                'QueueUrl' => 'http://sqs.com',
                'WaitTimeSeconds' => 20,
                'VisibilityTimeout' => 30,
                'MaxNumberOfMessages' => 10,
            ])
            ->willReturn([
                'Messages' => [
                    [
                        'Body' => json_encode([
                            'name' => 'job_name1',
                            'arguments' => ['first1', 'second1'],
                            'retryCount' => 0,
                        ]),
                        'MessageId' => 'id1',
                        'ReceiptHandle' => 'handle1',
                    ],
                    [
                        'Body' => json_encode([
                            'name' => 'job_name2',
                            'arguments' => ['first2', 'second2'],
                            'retryCount' => 0,
                        ]),
                        'MessageId' => 'id2',
                        'ReceiptHandle' => 'handle2',
                    ],
                ],
            ])
        ;

        $this->command->handleInput(new ArrayInput([], $this->command->getDefinition()));
        $batch = $this->command->getJobs();
        self::assertCount(2, $batch);
        $job = $batch->next();
        self::assertSame('job_name1', $job->getName());
        self::assertSame(['first1', 'second1'], $job->getArguments());
        self::assertInstanceOf(SqsJob::class, $job);
        self::assertSame('id1', $job->getId());
        self::assertSame('http://sqs.com', $job->getUrl());
        self::assertSame('handle1', $job->getReceiptHandle());
        self::assertNull($job->getVisibilityTimeout());
        self::assertSame(0, $job->getDelay());
        $job = $batch->next();
        self::assertSame('job_name2', $job->getName());
        self::assertSame(['first2', 'second2'], $job->getArguments());
        self::assertInstanceOf(SqsJob::class, $job);
        self::assertSame('id2', $job->getId());
        self::assertSame('http://sqs.com', $job->getUrl());
        self::assertSame('handle2', $job->getReceiptHandle());
        self::assertNull($job->getVisibilityTimeout());
        self::assertSame(0, $job->getDelay());
    }

    public function testGetJobsEmpty(): void
    {
        $this->sqsClient->expects(self::once())
            ->method('receiveMessage')
            ->with([
                'QueueUrl' => 'http://sqs.com',
                'WaitTimeSeconds' => 20,
                'VisibilityTimeout' => 30,
                'MaxNumberOfMessages' => 10,
            ])
            ->willReturn([
                'Messages' => [
                ],
            ])
        ;

        $this->command->handleInput(new ArrayInput([], $this->command->getDefinition()));
        $batch = $this->command->getJobs();
        self::assertNull($batch);
    }

    public function testGetInvalid(): void
    {
        $this->sqsClient->expects(self::once())
            ->method('receiveMessage')
            ->with([
                'QueueUrl' => 'http://sqs.com',
                'WaitTimeSeconds' => 20,
                'VisibilityTimeout' => 30,
                'MaxNumberOfMessages' => 10,
            ])
            ->willReturn([
                'Messages' => [
                    [
                        'Body' => json_encode([
                            'name' => 'job_name1',
                            'arguments' => ['first1', 'second1'],
                            'retryCount' => 0,
                        ]),
                        'MessageId' => 'id1',
                        'ReceiptHandle' => 'handle1',
                    ],
                    [
                        'Body' => json_encode([
                            'arguments' => ['first2', 'second2'],
                            'retryCount' => 0,
                        ]),
                        'MessageId' => 'id2',
                        'ReceiptHandle' => 'handle2',
                    ],
                ],
            ])
        ;

        $this->sqsClient->expects(self::once())
            ->method('deleteMessageBatch')
            ->with([
                'QueueUrl' => 'http://sqs.com',
                'Entries' => [
                    [
                        'Id' => 'E1',
                        'ReceiptHandle' => 'handle2',
                    ],
                ],
            ])
        ;

        $this->command->handleInput(new ArrayInput([], $this->command->getDefinition()));
        $batch = $this->command->getJobs();
        self::assertCount(1, $batch);
        $job = $batch->next();
        self::assertSame('job_name1', $job->getName());
        self::assertSame(['first1', 'second1'], $job->getArguments());
        self::assertInstanceOf(SqsJob::class, $job);
        self::assertSame('id1', $job->getId());
        self::assertSame('http://sqs.com', $job->getUrl());
        self::assertSame('handle1', $job->getReceiptHandle());
        self::assertNull($job->getVisibilityTimeout());
        self::assertSame(0, $job->getDelay());
    }

    public function testGetTimeout(): void
    {
        $this->sqsClient->expects(self::once())
            ->method('receiveMessage')
            ->with([
                'QueueUrl' => 'http://sqs.com',
                'WaitTimeSeconds' => 20,
                'VisibilityTimeout' => 30,
                'MaxNumberOfMessages' => 10,
            ])
            ->willReturn([
                'Messages' => [
                    [
                        'Body' => json_encode([
                            'name' => 'job_name1',
                            'arguments' => ['first1', 'second1'],
                            'retryCount' => 0,
                        ]),
                        'MessageId' => 'id1',
                        'ReceiptHandle' => 'handle1',
                    ],
                    [
                        'Body' => json_encode([
                            'name' => 'job_name2',
                            'arguments' => ['first2', 'second2'],
                            'retryCount' => 0,
                            'visibilityTimeout' => 60,
                        ]),
                        'MessageId' => 'id2',
                        'ReceiptHandle' => 'handle2',
                    ],
                ],
            ])
        ;

        $this->sqsClient->expects(self::once())
            ->method('changeMessageVisibilityBatch')
            ->with([
                'QueueUrl' => 'http://sqs.com',
                'Entries' => [
                    [
                        'Id' => 'E1',
                        'ReceiptHandle' => 'handle2',
                        'VisibilityTimeout' => 60,
                    ],
                ],
            ])
        ;

        $this->command->handleInput(new ArrayInput([], $this->command->getDefinition()));
        $batch = $this->command->getJobs();
        self::assertCount(2, $batch);
        $job = $batch->next();
        self::assertSame('job_name1', $job->getName());
        self::assertSame(['first1', 'second1'], $job->getArguments());
        self::assertInstanceOf(SqsJob::class, $job);
        self::assertSame('id1', $job->getId());
        self::assertSame('http://sqs.com', $job->getUrl());
        self::assertSame('handle1', $job->getReceiptHandle());
        self::assertNull($job->getVisibilityTimeout());
        self::assertSame(0, $job->getDelay());
        $job = $batch->next();
        self::assertSame('job_name2', $job->getName());
        self::assertSame(['first2', 'second2'], $job->getArguments());
        self::assertInstanceOf(SqsJob::class, $job);
        self::assertSame('id2', $job->getId());
        self::assertSame('http://sqs.com', $job->getUrl());
        self::assertSame('handle2', $job->getReceiptHandle());
        self::assertSame(60, $job->getVisibilityTimeout());
        self::assertSame(0, $job->getDelay());
    }

    public function testFinishJobs(): void
    {
        $this->sqsClient->expects(self::once())
            ->method('deleteMessageBatch')
            ->with([
                'QueueUrl' => 'http://sqs.com',
                'Entries' => [
                    [
                        'Id' => 'J1',
                        'ReceiptHandle' => 'handle2',
                    ],
                ],
            ])
        ;

        $this->command->finishJobs(new JobBatch([], [new SqsJob('job_name1', ['first1', 'second1'], 0, 'http://sqs.com', null, 0, 'handle2')], [], [], ['url' => 'http://sqs.com']));
    }

    public function testFinishFailJobs(): void
    {
        $this->sqsClient->expects(self::once())
            ->method('deleteMessageBatch')
            ->with([
                'QueueUrl' => 'http://sqs.com',
                'Entries' => [
                    [
                        'Id' => 'J1',
                        'ReceiptHandle' => 'handle2',
                    ],
                ],
            ])
        ;

        $this->command->finishJobs(new JobBatch([], [], [new SqsJob('job_name1', ['first1', 'second1'], 0, 'http://sqs.com', null, 0, 'handle2')], [], ['url' => 'http://sqs.com']));
    }

    public function testFinishRetryJobs(): void
    {
        $this->sqsClient->expects(self::once())
            ->method('sendMessageBatch')
            ->with([
                'QueueUrl' => 'http://sqs.com',
                'Entries' => [
                    [
                        'Id' => 'R1',
                        'MessageBody' => json_encode(['name' => 'job_name1', 'arguments' => ['first1', 'second1'], 'retryCount' => 1, 'visibilityTimeout' => null]),
                        'DelaySeconds' => 30,
                    ],
                ],
            ])
        ;

        $this->sqsClient->expects(self::once())
            ->method('deleteMessageBatch')
            ->with([
                'QueueUrl' => 'http://sqs.com',
                'Entries' => [
                    [
                        'Id' => 'J1',
                        'ReceiptHandle' => 'handle2',
                    ],
                ],
            ])
        ;

        $this->command->finishJobs(new JobBatch([], [], [], [new SqsJob('job_name1', ['first1', 'second1'], 0, 'http://sqs.com', null, 0, 'handle2')], ['url' => 'http://sqs.com']));
    }

    public function testFinishLeftOverJobs(): void
    {
        $this->sqsClient->expects(self::once())
            ->method('changeMessageVisibilityBatch')
            ->with([
                'QueueUrl' => 'http://sqs.com',
                'Entries' => [
                    [
                        'Id' => 'V1',
                        'ReceiptHandle' => 'handle2',
                        'VisibilityTimeout' => 0,
                    ],
                ],
            ])
        ;

        $this->command->finishJobs(new JobBatch([new SqsJob('job_name1', ['first1', 'second1'], 0, 'http://sqs.com', null, 0, 'handle2')], [], [], [], ['url' => 'http://sqs.com']));
    }
}

class RunnerMock extends SqsRunnerCommand
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
