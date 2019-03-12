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
        $this->assertCount(2, $batch);
        $this->assertCount(2, $batch->getJobs());
        $this->assertCount(0, $batch->getOks());
        $this->assertCount(0, $batch->getFails());
        $this->assertCount(0, $batch->getRetries());

        $first = $batch->next();
        $this->assertSame($job1, $first);
        $this->assertSame($job1, $batch->current());
        $this->assertCount(1, $batch);
        $this->assertCount(1, $batch->getJobs());
        $this->assertCount(0, $batch->getOks());
        $this->assertCount(0, $batch->getFails());
        $this->assertCount(0, $batch->getRetries());

        $second = $batch->next();
        $this->assertSame($job2, $second);
        $this->assertSame($job2, $batch->current());
        $this->assertCount(0, $batch);
        $this->assertCount(0, $batch->getJobs());
        $this->assertCount(0, $batch->getOks());
        $this->assertCount(0, $batch->getFails());
        $this->assertCount(0, $batch->getRetries());
    }

    public function testOk(): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();
        $batch = new JobBatch([$job]);
        $batch->next();
        $batch->result(null);

        $this->assertCount(0, $batch);
        $this->assertCount(0, $batch->getJobs());
        $this->assertCount(1, $batch->getOks());
        $this->assertSame([$job], $batch->getOks());
        $this->assertCount(0, $batch->getFails());
        $this->assertCount(0, $batch->getRetries());
    }

    public function testRetry(): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();
        $batch = new JobBatch([$job]);
        $batch->next();
        $batch->result(new \Exception('Fail'));

        $this->assertCount(0, $batch);
        $this->assertCount(0, $batch->getJobs());
        $this->assertCount(0, $batch->getOks());
        $this->assertCount(0, $batch->getFails());
        $this->assertCount(1, $batch->getRetries());
        $this->assertSame([$job], $batch->getRetries());
    }

    public function testFail(): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();
        $batch = new JobBatch([$job]);
        $batch->next();
        $batch->result(new UnrecoverableJobException('Fail'));

        $this->assertCount(0, $batch);
        $this->assertCount(0, $batch->getJobs());
        $this->assertCount(0, $batch->getOks());
        $this->assertCount(1, $batch->getFails());
        $this->assertSame([$job], $batch->getFails());
        $this->assertCount(0, $batch->getRetries());
    }
}
