<?php

declare(strict_types=1);

namespace Mcfedr\QueueManagerBundle\Tests\Worker;

use Carbon\Carbon;
use Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException;
use Mcfedr\QueueManagerBundle\Manager\QueueManagerRegistry;
use Mcfedr\QueueManagerBundle\Queue\Job;
use Mcfedr\QueueManagerBundle\Runner\JobExecutor;
use Mcfedr\QueueManagerBundle\Worker\PeriodicWorker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class PeriodicWorkerTest extends TestCase
{
    /**
     * @var PeriodicWorker
     */
    private $worker;

    /**
     * @var MockObject|QueueManagerRegistry
     */
    private $registry;

    /**
     * @var JobExecutor|MockObject
     */
    private $executor;

    protected function setUp(): void
    {
        $this->registry = $this->getMockBuilder(QueueManagerRegistry::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->executor = $this->getMockBuilder(JobExecutor::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $this->worker = new PeriodicWorker($this->registry, $this->executor);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
    }

    public function testExecute(): void
    {
        $this->executor->expects(self::once())
            ->method('executeJob')
            ->with(self::callback(static function ($job) {
                if (!$job instanceof Job) {
                    return false;
                }
                if ('test_worker' !== $job->getName()) {
                    return false;
                }
                if ($job->getArguments() !== [
                    'argument_a' => 'a',
                    'job_tokens' => [
                        'token' => 'aaaaaaaa-bbbb-cccc-dddd-aabbccddeeff',
                        'next_token' => 'aaaaaaaa-bbbb-cccc-dddd-aabbccddeef1',
                    ],
                ]) {
                    return false;
                }

                return true;
            }))
        ;

        $this->registry->expects(self::once())
            ->method('put')
            ->withConsecutive([
                PeriodicWorker::class,
                self::callback(function ($arguments) {
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
                self::callback(static function ($options) {
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
                'delay',
            ])
            ->willReturnOnConsecutiveCalls($this->getMockBuilder(Job::class)->getMock())
        ;

        $this->worker->execute([
            'name' => 'test_worker',
            'arguments' => [
                'argument_a' => 'a',
            ],
            'job_tokens' => [
                'token' => 'aaaaaaaa-bbbb-cccc-dddd-aabbccddeeff',
                'next_token' => 'aaaaaaaa-bbbb-cccc-dddd-aabbccddeef1',
            ],
            'period' => 3600,
            'delay_options' => [
                'delay_manager_option_a' => 'a',
            ],
            'delay_manager' => 'delay',
        ]);
    }

    public function testExecuteThrows(): void
    {
        $this->expectException(UnrecoverableJobException::class);
        $this->executor->expects(self::once())
            ->method('executeJob')
            ->with(self::callback(static function ($job) {
                if (!$job instanceof Job) {
                    return false;
                }
                if ('test_worker' !== $job->getName()) {
                    return false;
                }
                if ($job->getArguments() !== [
                    'argument_a' => 'a',
                    'job_tokens' => [
                        'token' => 'aaaaaaaa-bbbb-cccc-dddd-aabbccddeeff',
                        'next_token' => 'aaaaaaaa-bbbb-cccc-dddd-aabbccddeef1',
                    ],
                ]) {
                    return false;
                }

                return true;
            }))
            ->willThrowException(new UnrecoverableJobException('Fail'))
        ;

        $this->registry->expects(self::never())
            ->method('put')
        ;

        $this->worker->execute([
            'name' => 'test_worker',
            'arguments' => [
                'argument_a' => 'a',
            ],
            'job_tokens' => [
                'token' => 'aaaaaaaa-bbbb-cccc-dddd-aabbccddeeff',
                'next_token' => 'aaaaaaaa-bbbb-cccc-dddd-aabbccddeef1',
            ],
            'period' => 3600,
            'delay_options' => [
                'delay_manager_option_a' => 'a',
            ],
            'delay_manager' => 'delay',
        ]);
    }

    /**
     * @dataProvider length
     */
    public function testNextRun($length): void
    {
        Carbon::setTestNow(Carbon::now());
        [$startOfNextPeriod, $endOfNextPeriod] = PeriodicWorker::nextPeriod($length);
        $start = new Carbon("@{$startOfNextPeriod}");
        $end = new Carbon("@{$endOfNextPeriod}");

        $test = PeriodicWorker::nextRun($length);

        self::assertGreaterThanOrEqual($start, $test);
        self::assertLessThanOrEqual($end, $test);
    }

    /**
     * @dataProvider length
     */
    public function testNextPeriod($length): void
    {
        $timeObj = Carbon::createFromTime(12, 0, 0);
        $time = $timeObj->getTimestamp();
        Carbon::setTestNow($timeObj);

        [$startOfNextPeriod, $endOfNextPeriod] = PeriodicWorker::nextPeriod($length);

        self::assertSame($time + 1, $startOfNextPeriod);
        self::assertSame($time + $length, $endOfNextPeriod);

        Carbon::setTestNow(Carbon::createFromTimestamp($time + 1));

        [$startOfNextPeriod, $endOfNextPeriod] = PeriodicWorker::nextPeriod($length);

        self::assertSame($time + $length + 1, $startOfNextPeriod);
        self::assertSame($time + $length + $length, $endOfNextPeriod);

        Carbon::setTestNow(Carbon::createFromTimestamp($time + 15));

        [$startOfNextPeriod, $endOfNextPeriod] = PeriodicWorker::nextPeriod($length);

        self::assertSame($time + $length + 1, $startOfNextPeriod);
        self::assertSame($time + $length + $length, $endOfNextPeriod);
    }

    public static function length(): iterable
    {
        return [
            [3600],
            [100],
            [50],
        ];
    }
}
