<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Manager;

use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
use Mcfedr\QueueManagerBundle\Manager\QueueManager;
use Mcfedr\QueueManagerBundle\Manager\QueueManagerRegistry;
use Mcfedr\QueueManagerBundle\Queue\Job;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
final class QueueManagerRegistryTest extends TestCase
{
    /**
     * @var MockObject|QueueManager
     */
    private $default;

    /**
     * @var MockObject|QueueManager
     */
    private $delay;

    /**
     * @var QueueManagerRegistry
     */
    private $queueManagerRegistry;

    protected function setUp(): void
    {
        $this->default = $this->getMockBuilder(QueueManager::class)->getMock();
        $this->delay = $this->getMockBuilder(QueueManager::class)->getMock();
        $this->queueManagerRegistry = new QueueManagerRegistry(new ServiceLocator([
            'default' => function () {
                return $this->default;
            },
            'delay' => function () {
                return $this->delay;
            },
        ]), ['default', 'delay'], 'default');
    }

    public function testDelete(): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();
        $this->default->expects(self::once())->method('delete')->with($job);
        $this->delay->expects(self::never())->method('delete');

        $this->queueManagerRegistry->delete($job, 'default');
    }

    public function testDeleteUnnamed(): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();
        $this->default->expects(self::once())->method('delete')->with($job);
        $this->delay->expects(self::never())->method('delete');

        $this->queueManagerRegistry->delete($job);
    }

    public function testDeleteUnnamedWrong(): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();
        $this->default->expects(self::once())->method('delete')->with($job)->willThrowException(new WrongJobException());
        $this->delay->expects(self::once())->method('delete')->with($job);

        $this->queueManagerRegistry->delete($job);
    }

    public function testDeleteNoMatch(): void
    {
        $job = $this->getMockBuilder(Job::class)->getMock();
        $this->default->expects(self::once())->method('delete')->with($job)->willThrowException(new WrongJobException());
        $this->delay->expects(self::once())->method('delete')->with($job)->willThrowException(new WrongJobException());
        $this->expectException(WrongJobException::class);

        $this->queueManagerRegistry->delete($job);
    }

    public function testPut(): void
    {
        $this->default->expects(self::once())->method('put')->with('name', ['args'], ['options']);
        $this->delay->expects(self::never())->method('put');

        $this->queueManagerRegistry->put('name', ['args'], ['options']);
    }

    public function testPutNamed(): void
    {
        $this->default->expects(self::never())->method('put');
        $this->delay->expects(self::once())->method('put')->with('name', ['args'], ['options']);

        $this->queueManagerRegistry->put('name', ['args'], ['options'], 'delay');
    }
}
