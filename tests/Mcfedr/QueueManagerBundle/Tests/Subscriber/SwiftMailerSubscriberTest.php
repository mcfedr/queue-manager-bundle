<?php

namespace Mcfedr\QueueManagerBundle\Tests\Subscriber;

use Mcfedr\QueueManagerBundle\Event\FailedJobEvent;
use Mcfedr\QueueManagerBundle\Subscriber\SwiftMailerSubscriber;
use Symfony\Component\DependencyInjection\Container;

class SwiftMailerSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function testOnJobFailed()
    {
        $container = $this->getMockBuilder(Container::class)->getMock();
        $listener = $this->getMockBuilder(SwiftMailerSubscriber::class)
            ->setConstructorArgs([2, $container])
            ->setMethods(['onTerminate'])
            ->getMock();

        $listener->expects($this->exactly(2))->method('onTerminate');

        $jobEvent = $this->getMockBuilder(FailedJobEvent::class)->disableOriginalConstructor()->getMock();
        $listener->onJobFailed($jobEvent);
        $listener->onJobFailed($jobEvent);
        $listener->onJobFailed($jobEvent);
        $listener->onJobFailed($jobEvent);
        $listener->onJobFailed($jobEvent);
    }
}
