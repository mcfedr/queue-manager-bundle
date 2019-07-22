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
        static::assertCount(2, $batch);
        static::assertCount(2, $batch->getJobs());
        static::assertCount(0, $batch->getOks());
        static::assertCount(0, $batch->getFails());
        static::assertCount(0, $batch->getRetries());

        $first = $batch->next();
        static::assertSame($job1, $first);
        static::assertSame($job1, $batch->current());
        static::assertCount(1, $batch);
        static::assertCount(1, $batch->getJobs());
        static::assertCount(0, $batch->getOks());
        static::assertCount(0, $batch->getFails());
        static::assertCount(0, $batch->getRetries());

        $second = $batch->next();
        static::assertSame($job2, $second);
        static::assertSame($job2, $batch->current());
        static::assertCount(0, $batch);
        static::assertCount(0, $batch->getJobs());
        static::assertCount(0, $batch->getOks());
        static::assertCount(0, $batch->getFails());
        static::assertCount(0, $batch->getRetries());
    }

    public function testOk(): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();
        $batch = new JobBatch([$job]);
        $batch->next();
        $batch->result(null);

        static::assertCount(0, $batch);
        static::assertCount(0, $batch->getJobs());
        static::assertCount(1, $batch->getOks());
        static::assertSame([$job], $batch->getOks());
        static::assertCount(0, $batch->getFails());
        static::assertCount(0, $batch->getRetries());
    }

    public function testRetry(): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();
        $batch = new JobBatch([$job]);
        $batch->next();
        $batch->result(new \Exception('Fail'));

        static::assertCount(0, $batch);
        static::assertCount(0, $batch->getJobs());
        static::assertCount(0, $batch->getOks());
        static::assertCount(0, $batch->getFails());
        static::assertCount(1, $batch->getRetries());
        static::assertSame([$job], $batch->getRetries());
    }

    public function testFail(): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();
        $batch = new JobBatch([$job]);
        $batch->next();
        $batch->result(new UnrecoverableJobException('Fail'));

        static::assertCount(0, $batch);
        static::assertCount(0, $batch->getJobs());
        static::assertCount(0, $batch->getOks());
        static::assertCount(1, $batch->getFails());
        static::assertSame([$job], $batch->getFails());
        static::assertCount(0, $batch->getRetries());
    }
}
