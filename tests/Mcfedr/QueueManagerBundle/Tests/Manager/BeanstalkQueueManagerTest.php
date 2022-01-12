<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Manager;

use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
use Mcfedr\QueueManagerBundle\Manager\BeanstalkQueueManager;
use Mcfedr\QueueManagerBundle\Queue\BeanstalkJob;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Pheanstalk\Contract\PheanstalkInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class BeanstalkQueueManagerTest extends TestCase
{
    private BeanstalkQueueManager $manager;
    private MockObject|PheanstalkInterface $pheanstalk;

    protected function setUp(): void
    {
        $this->pheanstalk = $this
            ->getMockBuilder(PheanstalkInterface::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->manager = new BeanstalkQueueManager($this->pheanstalk, [
            'default_queue' => 'test_queue',
        ]);
    }

    public function testPut(): void
    {
        $job = new BeanstalkJob('test_worker', [], PheanstalkInterface::DEFAULT_PRIORITY, PheanstalkInterface::DEFAULT_TTR);

        $this->pheanstalk
            ->expects(static::once())
            ->method('useTube')
            ->with('test_queue')
            ->willReturn($this->pheanstalk)
        ;

        $this->pheanstalk
            ->expects(static::once())
            ->method('put')
            ->with($job->getData(), PheanstalkInterface::DEFAULT_PRIORITY, PheanstalkInterface::DEFAULT_DELAY, PheanstalkInterface::DEFAULT_TTR)
            ->willReturn(new \Pheanstalk\Job(0, 'data'))
        ;

        $job = $this->manager->put('test_worker');

        static::assertSame('test_worker', $job->getName());
    }

    public function testDelete(): void
    {
        $job = new BeanstalkJob('test_worker', [], PheanstalkInterface::DEFAULT_PRIORITY, 0, 1);

        $this->pheanstalk
            ->expects(static::once())
            ->method('delete')
            ->with(static::callback(function ($value) {
                return $value instanceof \Pheanstalk\Job && $value->getId() === 1;
            }))
        ;

        $this->manager->delete($job);
    }

    public function testDeleteOther(): void
    {
        $this->expectException(WrongJobException::class);
        $this->manager->delete($this->getMockBuilder(Job::class)->getMock());
    }
}
