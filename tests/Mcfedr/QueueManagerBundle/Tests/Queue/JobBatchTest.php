<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Queue;

use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\JobBatch;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class JobBatchTest extends TestCase
{
    public function testJobs(): void
    {
        $job1 = $this->getMockBuilder(Job::class)->getMock();
        $job2 = $this->getMockBuilder(Job::class)->getMock();
        $batch = new JobBatch([$job1, $job2]);
        self::assertCount(2, $batch);
        self::assertCount(2, $batch->getJobs());
        self::assertCount(0, $batch->getOks());
        self::assertCount(0, $batch->getFails());
        self::assertCount(0, $batch->getRetries());

        $first = $batch->next();
        self::assertSame($job1, $first);
        self::assertSame($job1, $batch->current());
        self::assertCount(1, $batch);
        self::assertCount(1, $batch->getJobs());
        self::assertCount(0, $batch->getOks());
        self::assertCount(0, $batch->getFails());
        self::assertCount(0, $batch->getRetries());

        $second = $batch->next();
        self::assertSame($job2, $second);
        self::assertSame($job2, $batch->current());
        self::assertCount(0, $batch);
        self::assertCount(0, $batch->getJobs());
        self::assertCount(0, $batch->getOks());
        self::assertCount(0, $batch->getFails());
        self::assertCount(0, $batch->getRetries());
    }

    public function testOk(): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();
        $batch = new JobBatch([$job]);
        $batch->next();
        $batch->result(null);

        self::assertCount(0, $batch);
        self::assertCount(0, $batch->getJobs());
        self::assertCount(1, $batch->getOks());
        self::assertSame([$job], $batch->getOks());
        self::assertCount(0, $batch->getFails());
        self::assertCount(0, $batch->getRetries());
    }

    public function testRetry(): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();
        $batch = new JobBatch([$job]);
        $batch->next();
        $batch->result(new \Exception('Fail'));

        self::assertCount(0, $batch);
        self::assertCount(0, $batch->getJobs());
        self::assertCount(0, $batch->getOks());
        self::assertCount(0, $batch->getFails());
        self::assertCount(1, $batch->getRetries());
        self::assertSame([$job], $batch->getRetries());
    }

    public function testFail(): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();
        $batch = new JobBatch([$job]);
        $batch->next();
        $batch->result(new UnrecoverableJobException('Fail'));

        self::assertCount(0, $batch);
        self::assertCount(0, $batch->getJobs());
        self::assertCount(0, $batch->getOks());
        self::assertCount(1, $batch->getFails());
        self::assertSame([$job], $batch->getFails());
        self::assertCount(0, $batch->getRetries());
    }
}
