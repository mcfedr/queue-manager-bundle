<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Subscriber;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
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
        $registry = $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock();
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();

        $registry->expects($this->once())
            ->method('getConnection')
            ->willReturn($connection)
        ;
        $registry->expects($this->once())
            ->method('resetManager')
        ;
        $connection->expects($this->once())
            ->method('close')
        ;

        $subscriber = new DoctrineResetSubscriber($registry);
        $subscriber->onJobFailed($this->getMockBuilder(FailedJobEvent::class)->disableOriginalConstructor()->getMock());
    }

    public function testOnJobFinished(): void
    {
        $registry = $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock();
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();

        $registry->expects($this->once())
            ->method('getConnection')
            ->willReturn($connection)
        ;
        $registry->expects($this->once())
            ->method('resetManager')
        ;
        $connection->expects($this->once())
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
        $this->assertTrue(true);
    }
}
