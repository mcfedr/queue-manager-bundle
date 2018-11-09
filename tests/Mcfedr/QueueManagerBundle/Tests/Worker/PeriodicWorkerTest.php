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

class PeriodicWorkerTest extends TestCase
{
    /**
     * @var PeriodicWorker
     */
    private $worker;

    /**
     * @var QueueManagerRegistry|MockObject
     */
    private $registry;

    /**
     * @var JobExecutor|MockObject
     */
    private $executor;

    public function setUp()
    {
        $this->registry = $this->getMockBuilder(QueueManagerRegistry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->executor = $this->getMockBuilder(JobExecutor::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->worker = new PeriodicWorker($this->registry, $this->executor);
    }

    public function tearDown()
    {
        Carbon::setTestNow(null);
    }

    public function testExecute()
    {
        $this->executor->expects($this->once())
            ->method('executeJob')
            ->with($this->callback(function ($job) {
                if (!$job instanceof Job) {
                    return false;
                }
                if ('test_worker' != $job->getName()) {
                    return false;
                }
                if ($job->getArguments() != [
                        'argument_a' => 'a',
                        'job_tokens' => [
                            'token' => 'aaaaaaaa-bbbb-cccc-dddd-aabbccddeeff',
                            'next_token' => 'aaaaaaaa-bbbb-cccc-dddd-aabbccddeef1',
                        ],
                    ]) {
                    return false;
                }

                return true;
            }));

        $this->registry->expects($this->once())
            ->method('put')
            ->withConsecutive([
                PeriodicWorker::class,
                $this->callback(function ($arguments) {
                    $this->assertCount(6, $arguments);
                    $this->assertArrayHasKey('name', $arguments);
                    $this->assertEquals('test_worker', $arguments['name']);
                    $this->assertArrayHasKey('arguments', $arguments);
                    $this->assertCount(1, $arguments['arguments']);

                    $this->assertArrayHasKey('argument_a', $arguments['arguments']);
                    $this->assertEquals('a', $arguments['arguments']['argument_a']);

                    $this->assertArrayHasKey('job_tokens', $arguments);
                    $this->assertCount(2, $arguments['job_tokens']);
                    $this->assertArrayHasKey('token', $arguments['job_tokens']);
                    $this->assertNotEmpty($arguments['job_tokens']['token']);
                    $this->assertArrayHasKey('next_token', $arguments['job_tokens']);
                    $this->assertNotEmpty($arguments['job_tokens']['next_token']);

                    $this->assertArrayHasKey('period', $arguments);
                    $this->assertEquals(3600, $arguments['period']);
                    $this->assertArrayHasKey('delay_options', $arguments);
                    $this->assertCount(1, $arguments['delay_options']);
                    $this->assertArrayHasKey('delay_manager_option_a', $arguments['delay_options']);
                    $this->assertEquals('a', $arguments['delay_options']['delay_manager_option_a']);
                    $this->assertArrayHasKey('delay_manager', $arguments);
                    $this->assertEquals('delay', $arguments['delay_manager']);

                    return true;
                }),
                $this->callback(function ($options) {
                    if (!\is_array($options)) {
                        return false;
                    }
                    if (!isset($options['delay_manager_option_a']) || 'a' != $options['delay_manager_option_a']) {
                        return false;
                    }
                    if (!isset($options['time']) || !$options['time'] instanceof \DateTime) {
                        return false;
                    }

                    return true;
                }),
                'delay',
            ])
            ->willReturnOnConsecutiveCalls($this->getMockBuilder(Job::class)->getMock());

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
     * @expectedException \Mcfedr\QueueManagerBundle\Exception\UnrecoverableJobException
     */
    public function testExecuteThrows()
    {
        $this->executor->expects($this->once())
            ->method('executeJob')
            ->with($this->callback(function ($job) {
                if (!$job instanceof Job) {
                    return false;
                }
                if ('test_worker' != $job->getName()) {
                    return false;
                }
                if ($job->getArguments() != [
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
            ->willThrowException(new UnrecoverableJobException('Fail'));

        $this->registry->expects($this->never())
            ->method('put');

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
    public function testNextRun($length)
    {
        Carbon::setTestNow(Carbon::now());
        list($startOfNextPeriod, $endOfNextPeriod) = PeriodicWorker::nextPeriod($length);
        $start = new Carbon("@$startOfNextPeriod");
        $end = new Carbon("@$endOfNextPeriod");

        $test = PeriodicWorker::nextRun($length);

        $this->assertGreaterThanOrEqual($start, $test);
        $this->assertLessThanOrEqual($end, $test);
    }

    /**
     * @dataProvider length
     */
    public function testNextPeriod($length)
    {
        $timeObj = Carbon::createFromTime(12, 0, 0);
        $time = $timeObj->getTimestamp();
        Carbon::setTestNow($timeObj);

        list($startOfNextPeriod, $endOfNextPeriod) = PeriodicWorker::nextPeriod($length);

        $this->assertEquals($time + 1, $startOfNextPeriod);
        $this->assertEquals($time + $length, $endOfNextPeriod);

        Carbon::setTestNow(Carbon::createFromTimestamp($time + 1));

        list($startOfNextPeriod, $endOfNextPeriod) = PeriodicWorker::nextPeriod($length);

        $this->assertEquals($time + $length + 1, $startOfNextPeriod);
        $this->assertEquals($time + $length + $length, $endOfNextPeriod);

        Carbon::setTestNow(Carbon::createFromTimestamp($time + 15));

        list($startOfNextPeriod, $endOfNextPeriod) = PeriodicWorker::nextPeriod($length);

        $this->assertEquals($time + $length + 1, $startOfNextPeriod);
        $this->assertEquals($time + $length + $length, $endOfNextPeriod);
    }

    public function length()
    {
        return [
            [3600],
            [100],
            [50],
        ];
    }
}
