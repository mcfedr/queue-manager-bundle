<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Mcfedr\QueueManagerBundle\Event\FailedJobEvent;
use Mcfedr\QueueManagerBundle\Event\FinishedJobEvent;
use Mcfedr\QueueManagerBundle\Subscriber\DoctrineResetSubscriber;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class DoctrineResetSubscriberTest extends TestCase
{
    public function testOnJobFailed(): void
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->disableOriginalConstructor()->getMock();
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();

        $registry->expects(static::once())
            ->method('getConnection')
            ->willReturn($connection)
        ;
        $registry->expects(static::once())
            ->method('resetManager')
        ;
        $connection->expects(static::once())
            ->method('close')
        ;

        $subscriber = new DoctrineResetSubscriber($registry);
        $subscriber->onJobFailed($this->getMockBuilder(FailedJobEvent::class)->disableOriginalConstructor()->getMock());
    }

    public function testOnJobFinished(): void
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->disableOriginalConstructor()->getMock();
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();

        $registry->expects(static::once())
            ->method('getConnection')
            ->willReturn($connection)
        ;
        $registry->expects(static::once())
            ->method('resetManager')
        ;
        $connection->expects(static::once())
            ->method('close')
        ;

        $subscriber = new DoctrineResetSubscriber($registry);
        $subscriber->onJobFinished($this->getMockBuilder(FinishedJobEvent::class)->disableOriginalConstructor()->getMock());
    }

    public function testNoDoctrine(): void
    {
        $subscriber = new DoctrineResetSubscriber();
        $subscriber->onJobFailed($this->getMockBuilder(FailedJobEvent::class)->disableOriginalConstructor()->getMock());
        $subscriber->onJobFinished($this->getMockBuilder(FinishedJobEvent::class)->disableOriginalConstructor()->getMock());
        static::assertTrue(true);
    }
}
