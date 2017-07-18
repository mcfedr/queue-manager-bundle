<?php

namespace Mcfedr\QueueManagerBundle\Tests\Subscriber;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use Mcfedr\QueueManagerBundle\Event\FailedJobEvent;
use Mcfedr\QueueManagerBundle\Subscriber\DoctrineResetSubscriber;

class DoctrineResetSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function testOnJobFailed()
    {
        $registry = $this->getMockBuilder(Registry::class)->disableOriginalConstructor()->getMock();
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();

        $registry->expects($this->once())
            ->method('getConnection')
            ->willReturn($connection);
        $registry->expects($this->once())
            ->method('resetManager');
        $connection->expects($this->once())
            ->method('close');

        $subscriber = new DoctrineResetSubscriber($registry);
        $subscriber->onJobFailed($this->getMockBuilder(FailedJobEvent::class)->disableOriginalConstructor()->getMock());
    }

    public function testNoDoctrine()
    {
        $subscriber = new DoctrineResetSubscriber();
        $subscriber->onJobFailed($this->getMockBuilder(FailedJobEvent::class)->disableOriginalConstructor()->getMock());
    }
}
