<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Manager;

use Mcfedr\QueueManagerBundle\Exception\WrongJobException;
use Mcfedr\QueueManagerBundle\Manager\PeriodicQueueManager;
use Mcfedr\QueueManagerBundle\Manager\QueueManagerRegistry;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Queue\PeriodicJob;
use Mcfedr\QueueManagerBundle\Worker\PeriodicWorker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PeriodicQueueManagerTest extends TestCase
{
    /**
     * @var PeriodicQueueManager
     */
    private $manager;

    /**
     * @var MockObject|QueueManagerRegistry
     */
    private $registry;

    protected function setUp(): void
    {
        $this->registry = $this->getMockBuilder(QueueManagerRegistry::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->manager = new PeriodicQueueManager($this->registry, [
            'delay_manager' => 'delay',
            'delay_manager_options' => [
                'delay_manager_option_a' => 'a',
            ],
        ]);
    }

    public function testPut(): void
    {
        $fakeJob = $this->getMockBuilder(Job::class)->getMock();
        $this->registry->expects(static::once())
            ->method('put')
            ->with(
                PeriodicWorker::class,
                static::callback(function ($arguments) {
                    $this->assertCount(6, $arguments);
                    $this->assertArrayHasKey('name', $arguments);
                    $this->assertSame('test_worker', $arguments['name']);
                    $this->assertArrayHasKey('arguments', $arguments);
                    $this->assertCount(1, $arguments['arguments']);

                    $this->assertArrayHasKey('argument_a', $arguments['arguments']);
                    $this->assertSame('a', $arguments['arguments']['argument_a']);

                    $this->assertArrayHasKey('job_tokens', $arguments);
                    $this->assertCount(2, $arguments['job_tokens']);
                    $this->assertArrayHasKey('token', $arguments['job_tokens']);
                    $this->assertNotEmpty($arguments['job_tokens']['token']);
                    $this->assertArrayHasKey('next_token', $arguments['job_tokens']);
                    $this->assertNotEmpty($arguments['job_tokens']['next_token']);

                    $this->assertArrayHasKey('period', $arguments);
                    $this->assertSame(3600, $arguments['period']);
                    $this->assertArrayHasKey('delay_options', $arguments);
                    $this->assertCount(1, $arguments['delay_options']);
                    $this->assertArrayHasKey('delay_manager_option_a', $arguments['delay_options']);
                    $this->assertSame('a', $arguments['delay_options']['delay_manager_option_a']);
                    $this->assertArrayHasKey('delay_manager', $arguments);
                    $this->assertSame('delay', $arguments['delay_manager']);

                    return true;
                }),
                static::callback(function ($options) {
                    if (!\is_array($options)) {
                        return false;
                    }
                    if (!isset($options['delay_manager_option_a']) || 'a' !== $options['delay_manager_option_a']) {
                        return false;
                    }
                    if (!isset($options['time']) || !$options['time'] instanceof \DateTime) {
                        return false;
                    }

                    return true;
                }),
                'delay'
            )
            ->willReturn($fakeJob)
        ;

        $job = $this->manager->put('test_worker', [
            'argument_a' => 'a',
        ], ['period' => 3600]);

        static::assertInstanceOf(PeriodicJob::class, $job);
        static::assertNotEmpty($job->getJobToken());
    }

    public function testNoPeriod(): void
    {
        $fakeJob = $this->getMockBuilder(Job::class)->getMock();
        $this->registry->expects(static::once())
            ->method('put')
            ->with('test_worker', [
                'argument_a' => 'a',
            ], [
                'delay_manager_option_a' => 'a',
            ], 'delay')
            ->willReturn($fakeJob)
        ;

        $job = $this->manager->put('test_worker', [
            'argument_a' => 'a',
        ]);

        static::assertSame($fakeJob, $job);
    }

    public function testDelete(): void
    {
        $this->expectException(WrongJobException::class);
        $this->manager->delete($this->getMockBuilder(Job::class)->getMock());
    }
}
