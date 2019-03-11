<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Subscriber;

use Mcfedr\QueueManagerBundle\Event\FailedJobEvent;
use Mcfedr\QueueManagerBundle\Event\FinishedJobEvent;
use Mcfedr\QueueManagerBundle\Subscriber\SwiftMailerSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;

/**
 * @internal
 */
final class SwiftMailerSubscriberTest extends TestCase
{
    public function testOnJobFailed(): void
    {
        $container = $this->getMockBuilder(Container::class)->getMock();
        $listener = $this->getMockBuilder(SwiftMailerSubscriber::class)
            ->setConstructorArgs([2, $container])
            ->setMethods(['onTerminate'])
            ->getMock()
        ;

        $listener->expects($this->exactly(2))->method('onTerminate');

        $jobEvent = $this->getMockBuilder(FailedJobEvent::class)->disableOriginalConstructor()->getMock();
        $listener->onJobFailed($jobEvent);
        $listener->onJobFailed($jobEvent);
        $listener->onJobFailed($jobEvent);
        $listener->onJobFailed($jobEvent);
        $listener->onJobFailed($jobEvent);
    }

    public function testOnJobFinished(): void
    {
        $container = $this->getMockBuilder(Container::class)->getMock();
        $listener = $this->getMockBuilder(SwiftMailerSubscriber::class)
            ->setConstructorArgs([2, $container])
            ->setMethods(['onTerminate'])
            ->getMock()
        ;

        $listener->expects($this->exactly(2))->method('onTerminate');

        $jobEvent = $this->getMockBuilder(FinishedJobEvent::class)->disableOriginalConstructor()->getMock();
        $listener->onJobFinished($jobEvent);
        $listener->onJobFinished($jobEvent);
        $listener->onJobFinished($jobEvent);
        $listener->onJobFinished($jobEvent);
        $listener->onJobFinished($jobEvent);
    }
}
